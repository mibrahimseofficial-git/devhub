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

	/**
	 * Rate limiting settings.
	 * MAX_SUBMISSIONS: Maximum number of submissions allowed within the time window.
	 * TIME_WINDOW: Time window in seconds (1 hour).
	 */
	const RATE_LIMIT_MAX = 5;
	const RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

	public function __construct() {
		add_shortcode( 'tavola_quote_builder', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_tqb_submit_quote', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_tqb_submit_quote', array( $this, 'handle_submit' ) );
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
			'individualItems' => $this->format_items_for_js( TQB_DB::get_line_items( 'individual', true ) ),
			'businessItems'   => $this->format_items_for_js( TQB_DB::get_line_items( 'business', true ) ),
			'assetBands'      => array(
				'c_s_corp'    => $this->format_bands_for_js( TQB_DB::get_asset_bands( 'c_s_corp' ) ),
				'partnership' => $this->format_bands_for_js( TQB_DB::get_asset_bands( 'partnership' ) ),
			),
			'revenueBands'    => $this->format_bands_for_js( TQB_DB::get_revenue_addons() ),
			'schedulingLink'  => get_option( 'tqb_scheduling_link', '' ),
		) );
	}

	/**
	 * Includes pricing data (fee, pattern, hardcoded value) so the front-end
	 * can calculate a LIVE PREVIEW total in JS as the user checks boxes —
	 * per developer's explicit request (PROJECT_SPEC.md Section 9.1), chosen
	 * over calling the server on every change for responsiveness.
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
				'hardcodedValue'       => null !== $item['hardcoded_value'] ? (float) $item['hardcoded_value'] : null,
				'showQty'              => ( 'qty_times_fee' === $item['pricing_pattern'] ),
				'isCustomQuoteTrigger' => (bool) $item['is_custom_quote_trigger'],
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

		$quote_type = isset( $_POST['quote_type'] ) ? sanitize_key( $_POST['quote_type'] ) : '';

		$contact = array(
			'name'  => isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '',
			'email' => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
			'phone' => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
		);

		if ( empty( $contact['name'] ) || ! is_email( $contact['email'] ) || empty( $contact['phone'] ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid name, email, and phone number.' ), 400 );
		}

		$answers = $this->sanitize_answers( isset( $_POST['answers'] ) ? (array) $_POST['answers'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		try {
			if ( 'individual' === $quote_type ) {

				$result = TQB_Quote_Handler::process_individual_quote( $contact, $answers );

			} elseif ( 'business' === $quote_type ) {

				$entity_type   = isset( $_POST['entity_type'] ) ? sanitize_key( $_POST['entity_type'] ) : '';
				$asset_band    = isset( $_POST['asset_band'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_band'] ) ) : '';
				$revenue_band  = isset( $_POST['revenue_band'] ) ? sanitize_text_field( wp_unslash( $_POST['revenue_band'] ) ) : '';

				if ( empty( $entity_type ) || empty( $asset_band ) || empty( $revenue_band ) ) {
					wp_send_json_error( array( 'message' => 'Please complete all business detail fields.' ), 400 );
				}

				$result = TQB_Quote_Handler::process_business_quote( $contact, $entity_type, $asset_band, $revenue_band, $answers );

			} else {
				wp_send_json_error( array( 'message' => 'Invalid quote type.' ), 400 );
				return;
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
