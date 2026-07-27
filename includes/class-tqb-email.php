<?php
/**
 * TQB_Email
 *
 * Sends the two emails described in the client's original scope:
 *   1. Confirmation to the prospect ("someone will reach out")
 *   2. Notification to the internal team (full submission details)
 * Uses wp_mail() so it respects whatever SMTP plugin/mail service is
 * already configured on the site — this class does not handle SMTP setup
 * itself. See PROJECT_SPEC.md Section 8 (Integration Requirements).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Email {

	/**
	 * Sends both emails for a given submission and updates the DB flags.
	 * Called right after TQB_Quote_Handler saves a submission. Failures are
	 * logged (not thrown) — a mail delivery problem shouldn't block the
	 * prospect from seeing their result on-screen, since the result was
	 * already calculated and saved successfully.
	 */
	public static function send_submission_emails( $submission_id ) {
		$submission = TQB_DB::get_submission( $submission_id );

		if ( ! $submission ) {
			return;
		}

		$confirmation_sent = self::send_confirmation_email( $submission );
		if ( $confirmation_sent ) {
			TQB_DB::mark_confirmation_sent( $submission_id );
		}

		$team_notified = self::send_team_notification( $submission );
		if ( $team_notified ) {
			TQB_DB::mark_team_notified( $submission_id );
		}
	}

	/**
	 * Confirmation email to the prospect. Per the client's original scope:
	 * "let them know someone from our team will be reaching out regarding
	 * their proposal" — kept short and reassuring, not a full re-statement
	 * of every answer they gave.
	 */
	private static function send_confirmation_email( array $submission ) {
		$to      = $submission['contact_email'];
		$name    = $submission['contact_name'];
		$subject = 'We received your quote request — Tavola Group';

		$site_name = get_bloginfo( 'name' );

		$body  = '<p>Hi ' . esc_html( $name ) . ',</p>';
		$body .= '<p>Thanks for reaching out to ' . esc_html( $site_name ) . '. We\'ve received your information';

		if ( $submission['is_custom_quote'] ) {
			$body .= ', and based on what you shared, your situation needs a closer look before we can provide a quote.';
		} else {
			$body .= ' and generated an estimated quote for you.';
		}

		$body .= ' Someone from our team will be reaching out shortly to discuss next steps.</p>';
		$body .= '<p>If you have any questions in the meantime, feel free to reply to this email.</p>';
		$body .= '<p>Best,<br />' . esc_html( $site_name ) . '</p>';

		return self::send( $to, $subject, $body );
	}

	/**
	 * Internal notification to the team, with the full submission detail
	 * so no one has to log into WordPress just to see what was submitted.
	 */
	private static function send_team_notification( array $submission ) {
		$to = get_option( 'tqb_team_notification_email', get_option( 'admin_email' ) );

		if ( empty( $to ) ) {
			return false;
		}

		$quote_type_label = ( 'business' === $submission['quote_type'] ) ? 'Business' : 'Individual';
		$subject = 'New ' . $quote_type_label . ' Quote Submission — ' . $submission['contact_name'];

		$body  = '<p><strong>New quote submission received.</strong></p>';
		$body .= '<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">';
		$body .= self::email_row( 'Type', $quote_type_label );
		$body .= self::email_row( 'Name', $submission['contact_name'] );
		$body .= self::email_row( 'Email', $submission['contact_email'] );
		$body .= self::email_row( 'Phone', $submission['contact_phone'] );

		if ( $submission['is_custom_quote'] ) {
			$body .= self::email_row( 'Result', 'CUSTOM QUOTE REQUIRED (reason: ' . esc_html( $submission['custom_quote_reason'] ) . ')' );
		} else {
			$body .= self::email_row( 'Estimated Quote', '$' . number_format( (float) $submission['calculated_total'], 2 ) );
		}

		$body .= '</table>';

		$body .= '<p><strong>Answers submitted:</strong></p>';
		$body .= '<ul>';
		foreach ( $submission['answers'] as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( empty( $value['selected'] ) ) {
					continue;
				}
				$line = esc_html( $key );
				if ( isset( $value['qty'] ) && $value['qty'] > 1 ) {
					$line .= ' (qty: ' . (int) $value['qty'] . ')';
				}
			} else {
				$line = esc_html( $key ) . ': ' . esc_html( $value );
			}
			$body .= '<li>' . $line . '</li>';
		}
		$body .= '</ul>';

		return self::send( $to, $subject, $body );
	}

	private static function email_row( $label, $value ) {
		return '<tr><td style="color:#666;">' . esc_html( $label ) . '</td><td><strong>' . esc_html( $value ) . '</strong></td></tr>';
	}

	/**
	 * Thin wrapper around wp_mail() with HTML content type set, and a
	 * try/catch-equivalent guard (wp_mail returns bool, doesn't throw) so a
	 * failure here never breaks the calling code's flow.
	 */
	private static function send( $to, $subject, $html_body ) {
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );

		$sent = wp_mail( $to, $subject, $html_body );

		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );

		if ( ! $sent ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: surfaces delivery failures without blocking the user-facing flow.
			error_log( 'TQB_Email: wp_mail failed sending to ' . $to . ' — subject: ' . $subject );
		}

		return $sent;
	}

	public static function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Send abandoned quote follow-up emails.
	 * Called by the cron job.
	 */
	public static function send_abandoned_quote_emails() {
		if ( ! get_option( 'tqb_enable_abandoned_emails', '1' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		$reminder_hours = (int) get_option( 'tqb_reminder_email_hours', 24 );
		$followup_hours = (int) get_option( 'tqb_followup_email_hours', 72 );
		$final_hours = (int) get_option( 'tqb_final_email_hours', 168 );

		$now = current_time( 'mysql' );

		// Find submissions eligible for reminder email
		$reminder_cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$reminder_hours} hours" ) );
		$reminder_eligible = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE status = 'in_progress' 
				AND created_at < %s 
				AND reminder_email_sent = 0
				AND status != 'abandoned'
				LIMIT 50",
				$reminder_cutoff
			),
			ARRAY_A
		);

		foreach ( $reminder_eligible as $submission ) {
			if ( self::send_abandoned_reminder_email( $submission ) ) {
				$wpdb->update(
					$table,
					array(
						'reminder_email_sent' => 1,
						'reminder_email_sent_at' => $now,
					),
					array( 'id' => $submission['id'] ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}
		}

		// Find submissions eligible for follow-up email (with call offer)
		$followup_cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$followup_hours} hours" ) );
		$followup_eligible = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE status = 'in_progress' 
				AND created_at < %s 
				AND followup_email_sent = 0
				AND reminder_email_sent = 1
				LIMIT 50",
				$followup_cutoff
			),
			ARRAY_A
		);

		foreach ( $followup_eligible as $submission ) {
			if ( self::send_abandoned_followup_email( $submission ) ) {
				$wpdb->update(
					$table,
					array(
						'followup_email_sent' => 1,
						'followup_email_sent_at' => $now,
					),
					array( 'id' => $submission['id'] ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}
		}

		// Find submissions eligible for final email
		$final_cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$final_hours} hours" ) );
		$final_eligible = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE status = 'in_progress' 
				AND created_at < %s 
				AND final_email_sent = 0
				AND followup_email_sent = 1
				LIMIT 50",
				$final_cutoff
			),
			ARRAY_A
		);

		foreach ( $final_eligible as $submission ) {
			if ( self::send_abandoned_final_email( $submission ) ) {
				$wpdb->update(
					$table,
					array(
						'final_email_sent' => 1,
						'final_email_sent_at' => $now,
						'status' => 'abandoned',
					),
					array( 'id' => $submission['id'] ),
					array( '%d', '%s', '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Reminder email: "You didn't finish your quote"
	 */
	private static function send_abandoned_reminder_email( array $submission ) {
		$to = $submission['contact_email'];
		$name = $submission['contact_name'];
		$subject = "Complete your tax quote — we're still here to help";

		$site_name = get_bloginfo( 'name' );
		$quote_page_url = get_permalink( get_queried_object_id() );

		$body  = '<p>Hi ' . esc_html( $name ) . ',</p>';
		$body .= '<p>We noticed you started filling out our tax quote form but didn\'t finish. We get it — life gets busy!</p>';
		$body .= '<p>When you\'re ready, you can pick up right where you left off:</p>';
		$body .= '<p><a href="' . esc_url( $quote_page_url ) . '" style="background:#1e3a5f;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">Continue Your Quote</a></p>';
		$body .= '<p>If you have any questions, just reply to this email — we\'re happy to help.</p>';
		$body .= '<p>Best,<br />' . esc_html( $site_name ) . '</p>';

		return self::send( $to, $subject, $body );
	}

	/**
	 * Follow-up email: "Need help? Schedule a call"
	 */
	private static function send_abandoned_followup_email( array $submission ) {
		$to = $submission['contact_email'];
		$name = $submission['contact_name'];
		$subject = "Tax deadline reminders — let's chat";

		$site_name = get_bloginfo( 'name' );
		$quote_page_url = get_permalink( get_queried_object_id() );
		$scheduling_link = get_option( 'tqb_scheduling_link', '' );
		$office_address = nl2br( esc_html( get_option( 'tqb_office_address', '' ) ) );

		$body  = '<p>Hi ' . esc_html( $name ) . ',</p>';
		$body .= '<p>We wanted to check in on your tax quote. Tax season can be stressful, and we\'re here to make it easier.</p>';
		$body .= '<p>If you\'d prefer to talk through your situation, we offer free consultations:</p>';
		
		if ( ! empty( $scheduling_link ) ) {
			$body .= '<p><a href="' . esc_url( $scheduling_link ) . '" style="background:#c9a84c;color:#1e3a5f;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;">Schedule a Free Call</a></p>';
		}

		if ( ! empty( $office_address ) ) {
			$body .= '<p>Or stop by our office:<br />' . $office_address . '</p>';
		}

		$body .= '<p>Of course, you can always <a href="' . esc_url( $quote_page_url ) . '">complete your quote online</a> when it\'s convenient.</p>';
		$body .= '<p>Looking forward to hearing from you,<br />' . esc_html( $site_name ) . '</p>';

		return self::send( $to, $subject, $body );
	}

	/**
	 * Final email: "Last chance — tax deadlines are approaching"
	 */
	private static function send_abandoned_final_email( array $submission ) {
		$to = $submission['contact_email'];
		$name = $submission['contact_name'];
		$subject = "Final reminder: Don't miss out on filing on time";

		$site_name = get_bloginfo( 'name' );
		$quote_page_url = get_permalink( get_queried_object_id() );
		$scheduling_link = get_option( 'tqb_scheduling_link', '' );

		$body  = '<p>Hi ' . esc_html( $name ) . ',</p>';
		$body .= '<p>This is our final reminder about your tax quote. We don\'t want you to miss out on filing on time.</p>';
		$body .= '<p>Take a few minutes to finish your quote:</p>';
		$body .= '<p><a href="' . esc_url( $quote_page_url ) . '" style="background:#1e3a5f;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">Complete Your Quote Now</a></p>';
		
		if ( ! empty( $scheduling_link ) ) {
			$body .= '<p>Or <a href="' . esc_url( $scheduling_link ) . '">schedule a quick call</a> and we\'ll handle everything for you.</p>';
		}

		$body .= '<p>After today, we won\'t send any more reminders. But if you change your mind, you\'re always welcome back!</p>';
		$body .= '<p>Best,<br />' . esc_html( $site_name ) . '</p>';

		return self::send( $to, $subject, $body );
	}
}
