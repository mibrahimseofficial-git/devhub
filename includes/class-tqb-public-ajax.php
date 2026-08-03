<?php
/**
 * AJAX Handler: Load Questions by Return Type & Filing Status
 * Add this to class-tqb-public.php in __construct()
 *
 * Action: wp_ajax_tqb_load_questions
 * Returns: JSON array of question objects with personalization
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Public_AJAX {

	/**
	 * Registers AJAX handlers.
	 * Call this from TQB_Public::__construct()
	 */
	public static function register_handlers() {
		add_action( 'wp_ajax_tqb_load_questions', array( __CLASS__, 'ajax_load_questions' ) );
	}

	/**
	 * AJAX: Load questions for a given return type and filing status.
	 * 
	 * POST params:
	 *   - return_type: 'individual' or 'business'
	 *   - filing_status: 'single', 'mfj', 'mfs', 'hoh' (or empty for business)
	 * 
	 * Returns JSON:
	 *   {
	 *     'success': true,
	 *     'data': [
	 *       {
	 *         'id': 1,
	 *         'item_key': 'w2_wages',
	 *         'label': 'Did anyone in your household...',
	 *         'fee': 350.0,
	 *         'pricing_pattern': 'qty_times_fee',
	 *         'is_custom_quote_trigger': 0,
	 *         'reveal_followup': 1,
	 *         'tooltip': 'This includes wages...',
	 *         'followup_label': 'How many states?',
	 *         ...
	 *       },
	 *       ...
	 *     ]
	 *   }
	 */
	public static function ajax_load_questions() {
		// Check nonce
		check_ajax_referer( 'tqb_nonce', 'security' );

		// Get parameters
		$return_type = isset( $_POST['return_type'] ) ? sanitize_text_field( $_POST['return_type'] ) : 'individual';
		$filing_status = isset( $_POST['filing_status'] ) ? sanitize_text_field( $_POST['filing_status'] ) : null;

		// Validate return type
		if ( ! in_array( $return_type, array( 'individual', 'business' ), true ) ) {
			wp_send_json_error( 'Invalid return type' );
		}

		// For business, ignore filing status
		if ( 'business' === $return_type ) {
			$filing_status = null;
		}

		// Load questions using the Question Sets class
		$questions = TQB_Question_Sets::get_questions( $return_type, $filing_status );

		// Transform for frontend
		$response_data = array();
		foreach ( $questions as $q ) {
			$response_data[] = array(
				'id'                     => (int) $q['id'],
				'item_key'               => $q['item_key'],
				'label'                  => $q['label'],
				'fee'                    => (float) $q['fee'],
				'pricing_pattern'        => $q['pricing_pattern'],
				'is_custom_quote_trigger' => (int) $q['is_custom_quote_trigger'],
				'threshold_rules'        => $q['threshold_rules'], // JSON string or null
				'reveal_followup'        => (int) $q['reveal_followup'],
				'is_active'              => (int) $q['is_active'],
				'tooltip'                => $q['tooltip'],
				'followup_label'         => $q['followup_label'],
			);
		}

		wp_send_json_success( $response_data );
	}
}

// Register on init
add_action( 'init', array( 'TQB_Public_AJAX', 'register_handlers' ) );
