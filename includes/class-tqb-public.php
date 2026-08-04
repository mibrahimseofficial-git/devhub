<?php
/**
 * TQB_Public
 *
 * Registers the [tavola_quote_builder] shortcode, enqueues the front-end
 * assets, and handles the AJAX submission that runs the pricing engine and
 * saves a submission. See PROJECT_SPEC.md Section 2 for architecture notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Public {

	const NONCE_ACTION = 'tqb_quote_nonce';
	const NONCE_ACTION_SAVE_PARTIAL = 'tqb_save_partial_nonce';
	const NONCE_ACTION_CHECK_PARTIAL = 'tqb_check_partial_nonce';
	const NONCE_ACTION_DISMISS_PARTIAL = 'tqb_dismiss_partial_nonce';

	/**
	 * Rate limiting settings.
	 * MAX_SUBMISSIONS: Maximum number of submissions allowed within the time window.
	 * TIME_WINDOW: Time window in seconds (1 hour).
	 */
	const RATE_LIMIT_MAX = 5;
	const RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Cached column existence checks to avoid repeated INFORMATION_SCHEMA queries.
	 */
	private static $column_cache = array();

	public function __construct() {
		add_shortcode( 'tavola_quote_builder', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_tqb_submit_quote', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_tqb_submit_quote', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_tqb_save_partial', array( $this, 'handle_save_partial' ) );
		add_action( 'wp_ajax_nopriv_tqb_save_partial', array( $this, 'handle_save_partial' ) );
		add_action( 'wp_ajax_tqb_check_partial_by_ip', array( $this, 'handle_check_partial_by_ip' ) );
		add_action( 'wp_ajax_nopriv_tqb_check_partial_by_ip', array( $this, 'handle_check_partial_by_ip' ) );
		add_action( 'wp_ajax_tqb_dismiss_partial', array( $this, 'handle_dismiss_partial' ) );
		add_action( 'wp_ajax_nopriv_tqb_dismiss_partial', array( $this, 'handle_dismiss_partial' ) );
	}

	/**
	 * Renders the shortcode output. Assets are enqueued here (not on every
	 * page load) so the plugin only loads its CSS/JS on pages that actually
	 * use the shortcode.
	 */
	public function render_shortcode( $atts ) {
		$this->enqueue_assets();

		ob_start();
		include TQB_PLUGIN_DIR . 'public/views/form-template.php';
		return ob_get_clean();
	}

	private function enqueue_assets() {
		wp_enqueue_style(
			'tqb-public',
			TQB_PLUGIN_URL . 'public/css/tqb-public.css',
			array(),
			tqb_asset_version( 'public/css/tqb-public.css' )
		);

		wp_enqueue_script(
			'tqb-public',
			TQB_PLUGIN_URL . 'public/js/tqb-public.js',
			array(),
			tqb_asset_version( 'public/js/tqb-public.js' ),
			true
		);

		wp_localize_script( 'tqb-public', 'tqbData', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
			'nonceSavePartial' => wp_create_nonce( self::NONCE_ACTION_SAVE_PARTIAL ),
			'nonceCheckPartial' => wp_create_nonce( self::NONCE_ACTION_CHECK_PARTIAL ),
			'nonceDismissPartial' => wp_create_nonce( self::NONCE_ACTION_DISMISS_PARTIAL ),
			'questions'       => $this->get_all_questions(),
			'individualItems' => $this->format_items_for_js( TQB_DB::get_line_items( 'individual', true ) ),
			'businessItems'   => $this->format_items_for_js( TQB_DB::get_line_items( 'business', true ) ),
			'assetBands'      => array(
				'c_s_corp'    => $this->format_bands_for_js( TQB_DB::get_asset_bands( 'c_s_corp' ) ),
				'partnership' => $this->format_bands_for_js( TQB_DB::get_asset_bands( 'partnership' ) ),
			),
			'revenueBands'    => $this->format_bands_for_js( TQB_DB::get_revenue_addons() ),
			'schedulingLink'  => get_option( 'tqb_scheduling_link', '' ),
			'filing_status_prices' => array(
				'single' => (int) get_option( 'tqb_filing_status_price_single', 0 ),
				'mfj'    => (int) get_option( 'tqb_filing_status_price_mfj', 200 ),
				'mfs'    => (int) get_option( 'tqb_filing_status_price_mfs', 300 ),
				'hoh'    => (int) get_option( 'tqb_filing_status_price_hoh', 150 )
			),
			'filing_status_labels' => array(
				'single' => get_option( 'tqb_filing_status_label_single', 'Single' ),
				'mfj'    => get_option( 'tqb_filing_status_label_mfj', 'Married Filing Jointly' ),
				'mfs'    => get_option( 'tqb_filing_status_label_mfs', 'Married Filing Separately' ),
				'hoh'    => get_option( 'tqb_filing_status_label_hoh', 'Head of Household' )
			),
		) );
	}

	/**
	 * Get all active questions from database with filing status filtering capability
	 */
	private function get_all_questions() {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_line_items';
		
		$questions = $wpdb->get_results( "
			SELECT 
				id, item_key, label, quote_type, tooltip, 
				reveal_followup, threshold_rules, sort_order,
				is_custom_quote_trigger, fee, filing_status,
				pricing_pattern
			FROM $table
			WHERE is_active = 1
			ORDER BY sort_order ASC, id ASC
		" );
		
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * Includes pricing data (fee, pattern) so the front-end can calculate a
	 * LIVE PREVIEW total in JS as the user checks boxes — per developer's
	 * explicit request (PROJECT_SPEC.md Section 9.1), chosen over calling
	 * the server on every change for responsiveness.
	 *
	 * IMPORTANT — accepted tradeoff, documented here and in the JS: this
	 * means the pricing logic now exists in TWO places (PHP engine +
	 * JS mirror in tqb-public.js). The server (TQB_Pricing_Engine) is still
	 * the only thing that calculates the REAL submitted quote — this data
	 * only feeds the on-screen preview. If pricing logic ever changes, both
	 * places need updating or the live preview can drift from the real price
	 * (the final submitted price will still always be correct either way,
	 * since it's recalculated server-side on submit regardless of what the
	 * preview showed).
	 */
	private function format_items_for_js( $items ) {
		$formatted = array();
		foreach ( $items as $item ) {
			$formatted[] = array(
				'key'                  => $item['item_key'],
				'label'                => $item['label'],
				'tooltip'              => ! empty( $item['tooltip'] ) ? $item['tooltip'] : '',
				'fee'                  => (float) $item['fee'],
				'pricingPattern'       => $item['pricing_pattern'],
				'showQty'              => ( 'qty_times_fee' === $item['pricing_pattern'] ),
				'isCustomQuoteTrigger' => (bool) $item['is_custom_quote_trigger'],
				'thresholdQty'         => null !== $item['threshold_qty'] ? (float) $item['threshold_qty'] : null,
				'thresholdTrigger'     => ! empty( $item['threshold_trigger'] ) ? $item['threshold_trigger'] : null,
				'thresholdRules'       => ! empty( $item['threshold_rules'] ) ? $item['threshold_rules'] : null,
				'reveal_followup'      => (int) ( $item['reveal_followup'] ?? 1 ), // 1 = reveal, 0 = always show
				'filing_status'        => ! empty( $item['filing_status'] ) ? $item['filing_status'] : null,
			);
		}
		return $formatted;
	}

	private function format_bands_for_js( $bands ) {
		$formatted = array();
		foreach ( $bands as $band ) {
			$formatted[] = array(
				'label'    => $band['band_label'],
				'price'    => null !== $band['price'] ? (float) $band['price'] : null,
				'bandMax'  => null !== $band['band_max'] ? (float) $band['band_max'] : null,
				'isCustom' => ! empty( $band['is_custom'] ),
			);
		}
		return $formatted;
	}

	/**
	 * AJAX handler for form submission. Validates input, delegates to
	 * TQB_Quote_Handler (which runs the pricing engine and saves the
	 * submission), and returns JSON for the JS to render the result screen.
	 *
	 * Rate limiting: Each IP address is allowed a maximum number of submissions
	 * per hour to prevent abuse. The limit is tracked via WordPress transients.
	 */
	public function handle_submit() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		// Check rate limit before processing
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			wp_send_json_error( array(
				'message'   => $rate_limit->get_error_message(),
				'retryAfter' => $rate_limit->get_error_data( 'retry_after' ),
			), 429 );
			return;
		}

		// Handle multi-select quote types (JSON array)
		$quote_types_json = isset( $_POST['quote_types'] ) ? sanitize_text_field( wp_unslash( $_POST['quote_types'] ) ) : '[]';
		$quote_types = json_decode( $quote_types_json, true );
		if ( ! is_array( $quote_types ) || empty( $quote_types ) ) {
			wp_send_json_error( array( 'message' => 'Please select at least one quote type.' ), 400 );
			return;
		}

		$contact = array(
			'name'  => isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '',
			'email' => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
			'phone' => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
		);

		if ( empty( $contact['name'] ) || ! is_email( $contact['email'] ) || empty( $contact['phone'] ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid name, email, and phone number.' ), 400 );
			return;
		}

		// 'answers' arrives as a JSON string (built client-side via
		// JSON.stringify), NOT as WordPress bracket-notation POST fields —
		// so it must be json_decode()'d, not cast with (array). Casting a
		// string to an array in PHP just wraps it as one throwaway element
		// rather than parsing it, which silently discarded every answer on
		// every submission until this fix.
		$answers_raw     = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$answers_decoded = json_decode( $answers_raw, true );
		$answers         = $this->sanitize_answers( is_array( $answers_decoded ) ? $answers_decoded : array() );

		// Gather business data by actual array length, not by counting how
		// many times the string 'business' appears in $quote_types (that
		// string only ever appears once regardless of how many businesses
		// were added via "Add Another Business", which silently limited
		// pricing to business #1 only — fixed by reading the real count).
		$businesses = array();
		$business_count = isset( $_POST['business_count'] ) ? absint( $_POST['business_count'] ) : ( in_array( 'business', $quote_types, true ) ? 1 : 0 );

		for ( $i = 0; $i < $business_count; $i++ ) {
			$entity_type  = isset( $_POST['businesses'][ $i ]['entity_type'] ) ? sanitize_key( $_POST['businesses'][ $i ]['entity_type'] ) : '';
			$asset_band   = isset( $_POST['businesses'][ $i ]['asset_band'] ) ? sanitize_text_field( wp_unslash( $_POST['businesses'][ $i ]['asset_band'] ) ) : '';
			$revenue_band = isset( $_POST['businesses'][ $i ]['revenue_band'] ) ? sanitize_text_field( wp_unslash( $_POST['businesses'][ $i ]['revenue_band'] ) ) : '';
			$business_name = isset( $_POST['businesses'][ $i ]['name'] ) ? sanitize_text_field( wp_unslash( $_POST['businesses'][ $i ]['name'] ) ) : '';

			if ( empty( $entity_type ) || empty( $asset_band ) || empty( $revenue_band ) ) {
				wp_send_json_error( array( 'message' => 'Please complete all business detail fields.' ), 400 );
				return;
			}

			$businesses[] = array(
				'name'         => $business_name,
				'entity_type'  => $entity_type,
				'asset_band'   => $asset_band,
				'revenue_band' => $revenue_band,
			);
		}

		// Gather individual (personal filer) data the same way — index 0 is
		// the primary filer (name from $contact, filing status from Step 2);
		// index > 0 are additional filers added via "Add Another Personal
		// Return", each with their own name + filing status.
		$individuals = array();
		$individual_count = isset( $_POST['individual_count'] ) ? absint( $_POST['individual_count'] ) : ( in_array( 'individual', $quote_types, true ) ? 1 : 0 );

		for ( $i = 0; $i < $individual_count; $i++ ) {
			$filer_name    = isset( $_POST['individuals'][ $i ]['name'] ) ? sanitize_text_field( wp_unslash( $_POST['individuals'][ $i ]['name'] ) ) : '';
			$filing_status = isset( $_POST['individuals'][ $i ]['filing_status'] ) ? sanitize_key( $_POST['individuals'][ $i ]['filing_status'] ) : '';

			if ( empty( $filing_status ) ) {
				wp_send_json_error( array( 'message' => 'Please select a filing status for each personal return.' ), 400 );
				return;
			}

			$individuals[] = array(
				'name'          => $filer_name ? $filer_name : $contact['name'],
				'filing_status' => $filing_status,
			);
		}

		try {
			// Check if there's an existing partial submission for this email
			// If so, update it to completed instead of creating a new record
			$existing_partial_id = TQB_Quote_Handler::get_existing_partial_id( $contact['email'] );

			if ( $existing_partial_id ) {
				// Complete the existing partial submission
				$result = TQB_Quote_Handler::complete_partial_submission(
					$existing_partial_id,
					$contact,
					$quote_types,
					$businesses,
					$individuals,
					$answers
				);
			} else {
				// No existing partial - create new submission
				$result = TQB_Quote_Handler::process_combined_quote( $contact, $quote_types, $businesses, $individuals, $answers );
			}
		} catch ( InvalidArgumentException $e ) {
			wp_send_json_error( array( 'message' => 'There was a problem with your submission. Please try again or contact us directly.' ), 400 );
			return;
		}

		$engine_result = $result['result'];

		if ( ! empty( $result['submission_id'] ) ) {
			TQB_Email::send_submission_emails( $result['submission_id'] );
			TQB_Hubspot::sync_submission( $result['submission_id'] );
		}

		// Increment rate limit counter after successful submission
		$this->increment_rate_limit();

		wp_send_json_success( array(
			'isCustomQuote' => $engine_result['is_custom_quote'],
			'total'         => $engine_result['total'],
			'disclaimer'    => get_option( 'tqb_disclaimer_text', '' ),
			'schedulingLink'=> get_option( 'tqb_scheduling_link', '' ),
		) );
	}

	/**
	 * Saves partial form progress for abandoned quote follow-up.
	 * Called via AJAX when user completes each step.
	 */
	public function handle_save_partial() {
		// Verify CSRF token
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION_SAVE_PARTIAL ) ) {
			wp_send_json_error( array( 'message' => 'Security verification failed. Please refresh the page and try again.' ), 403 );
			return;
		}

		// Input length limits to prevent abuse
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		// Enforce length limits
		$name = mb_substr( $name, 0, 100 );
		$phone = mb_substr( $phone, 0, 20 );

		// Validate required fields
		$errors = array();

		if ( empty( $name ) ) {
			$errors['name'] = 'Full name is required';
		} elseif ( strlen( trim( $name ) ) < 2 ) {
			$errors['name'] = 'Please enter a valid name';
		}

		if ( empty( $email ) ) {
			$errors['email'] = 'Email address is required';
		} elseif ( ! is_email( $email ) ) {
			$errors['email'] = 'Please enter a valid email address';
		}

		if ( empty( $phone ) ) {
			$errors['phone'] = 'Phone number is required';
		}

		// Return validation errors if any
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => 'Please fill in all required fields correctly.',
				'validation_errors' => $errors,
			), 400 );
			return;
		}

		// Check if this email already has a completed submission
		$existing_completed = TQB_Quote_Handler::check_existing_submission( $email );
		if ( $existing_completed ) {
			wp_send_json_error( array( 
				'message' => 'This email already has a completed quote submission.',
				'duplicate' => true,
			), 400 );
			return;
		}

		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		$quote_types = isset( $_POST['quote_types'] ) ? sanitize_text_field( wp_unslash( $_POST['quote_types'] ) ) : '';
		$quote_types = mb_substr( $quote_types, 0, 500 ); // Limit size
		$answers = isset( $_POST['answers'] ) ? (array) $_POST['answers'] : array();
		$businesses = isset( $_POST['businesses'] ) ? (array) $_POST['businesses'] : array();
		$user_ip = $this->get_client_ip();

		$result = TQB_Quote_Handler::save_partial_submission(
			$email,
			$step,
			$quote_types,
			$name,
			$phone,
			$answers,
			$businesses,
			$user_ip
		);

		if ( is_wp_error( $result ) ) {
			$error_code = $result->get_error_code();
			$message = $result->get_error_message();
			
			if ( $error_code === 'ip_conflict' ) {
				wp_send_json_error( array( 
					'message' => $message,
					'ip_conflict' => true,
				), 400 );
				return;
			}
			
			if ( $error_code === 'db_error' ) {
				// Log detailed error for debugging
				error_log( 'TQB_Email: Database error in save_partial: ' . $message );
			}
			
			wp_send_json_error( array( 'message' => $message ), 400 );
			return;
		}

		wp_send_json_success( array(
			'saved' => true,
			'submission_id' => $result,
		) );
	}

	/**
	 * Check for existing partial by IP and return data for auto-population.
	 * Called via AJAX when page loads.
	 */
	public function handle_check_partial_by_ip() {
		// Verify CSRF token
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION_CHECK_PARTIAL ) ) {
			wp_send_json_error( array( 'message' => 'Security verification failed.' ), 403 );
			return;
		}

		$user_ip = $this->get_client_ip();

		if ( empty( $user_ip ) ) {
			wp_send_json_success( array( 'has_partial' => false ) );
			return;
		}

		$partial = TQB_Quote_Handler::get_partial_by_ip( $user_ip );

		if ( ! $partial ) {
			wp_send_json_success( array( 'has_partial' => false ) );
			return;
		}

		// Decode answers JSON
		$answers_data = json_decode( $partial['answers'], true );

		wp_send_json_success( array(
			'has_partial' => true,
			'submission_id' => $partial['id'],
			'contact_email' => $partial['contact_email'],
			'contact_name' => $partial['contact_name'],
			'contact_phone' => $partial['contact_phone'],
			'last_step' => $partial['last_completed_step'],
			'quote_types' => $answers_data['quote_types'] ?? array(),
			'answers' => $answers_data['answers'] ?? array(),
			'businesses' => $answers_data['businesses'] ?? array(),
		) );
	}

	/**
	 * Dismisses/abandons the current partial submission so user can start fresh.
	 */
	public function handle_dismiss_partial() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION_DISMISS_PARTIAL ) ) {
			wp_send_json_error( array( 'message' => 'Security verification failed.' ), 403 );
			return;
		}

		$user_ip = $this->get_client_ip();

		if ( empty( $user_ip ) ) {
			wp_send_json_success();
			return;
		}

		// Mark the partial as abandoned instead of deleting (for audit purposes)
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if status column exists
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );
		$has_status_column = in_array( 'status', $column_names, true );

		if ( $has_status_column ) {
			$wpdb->update(
				$table,
				array( 'status' => 'abandoned' ),
				array( 'user_ip' => $user_ip, 'status' => 'in_progress' ),
				array( '%s' ),
				array( '%s', '%s' )
			);
		} else {
			// Fallback: delete the partial if no status column
			$wpdb->delete(
				$table,
				array( 'user_ip' => $user_ip, 'calculated_total' => null ),
				array( '%s', 'NULL' )
			);
		}

		wp_send_json_success();
	}

	/**
	 * Sanitizes the raw $_POST['answers'] array into the shape the pricing
	 * engine expects: [ item_key => [ 'selected' => bool, 'qty' => int ] ].
	 */
	private function sanitize_answers( array $raw_answers ) {
		$clean = array();
		foreach ( $raw_answers as $item_key => $fields ) {
			$item_key = sanitize_key( $item_key );
			if ( ! is_array( $fields ) ) {
				continue;
			}
			$clean[ $item_key ] = array(
				'selected' => ! empty( $fields['selected'] ),
				'qty'      => isset( $fields['qty'] ) ? absint( $fields['qty'] ) : 1,
			);
		}
		return $clean;
	}

	/**
	 * Gets the client IP address, handling proxied requests properly.
	 *
	 * @return string The sanitized IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// X-Forwarded-For can contain multiple IPs, take the first one
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Checks if the current client has exceeded the rate limit.
	 *
	 * @return true|WP_Error True if allowed, WP_Error with message if rate limited.
	 */
	private function check_rate_limit() {
		$ip = $this->get_client_ip();
		$transient_key = 'tqb_rate_limit_' . md5( $ip );

		$submissions = get_transient( $transient_key );

		if ( false === $submissions ) {
			// First submission in the window
			return true;
		}

		if ( $submissions >= self::RATE_LIMIT_MAX ) {
			$ttl = get_transient( $transient_key . '_ttl' );
			$retry_after = $ttl ? ( $ttl - time() ) : self::RATE_LIMIT_WINDOW;

			return new WP_Error(
				'rate_limited',
				sprintf(
					'Too many submissions. Please wait about %d minutes before trying again.',
					(int) ceil( $retry_after / 60 )
				),
				array( 'retry_after' => max( 1, (int) $retry_after ) )
			);
		}

		return true;
	}

	/**
	 * Increments the rate limit counter for the current client.
	 */
	private function increment_rate_limit() {
		$ip = $this->get_client_ip();
		$transient_key = 'tqb_rate_limit_' . md5( $ip );
		$ttl_key = $transient_key . '_ttl';

		$submissions = get_transient( $transient_key );

		if ( false === $submissions ) {
			// First submission - set both transient and TTL marker
			set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
			set_transient( $ttl_key, time() + self::RATE_LIMIT_WINDOW, self::RATE_LIMIT_WINDOW );
		} else {
			// Increment existing counter
			set_transient( $transient_key, $submissions + 1, self::RATE_LIMIT_WINDOW );
		}
	}
}
