<?php
/**
 * TQB_Quote_Handler
 *
 * The WordPress-facing bridge between raw form input, the database (via
 * TQB_DB), and the pure calculation logic (TQB_Pricing_Engine). This is
 * where $wpdb-backed data gets shaped into the plain arrays the engine
 * expects — the engine itself never touches WordPress or the database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Quote_Handler {

	/**
	 * Process an Individual quote submission end to end: fetch config,
	 * calculate, save to DB. Does NOT send emails or sync HubSpot — those
	 * are separate phases, hooked in afterward via the returned submission ID.
	 *
	 * @param array $contact  [ 'name' => ..., 'email' => ..., 'phone' => ... ]
	 * @param array $answers  [ item_key => [ 'selected' => bool, 'qty' => int ], ... ]
	 * @return array [ 'submission_id' => int, 'result' => <engine result array> ]
	 */
	public static function process_individual_quote( array $contact, array $answers ) {
		$line_items = TQB_DB::get_line_items( 'individual', false );

		$result = TQB_Pricing_Engine::calculate_individual( $line_items, $answers );

		$submission_id = TQB_DB::insert_submission( array(
			'quote_type'          => 'individual',
			'contact_name'        => $contact['name'],
			'contact_email'       => $contact['email'],
			'contact_phone'       => $contact['phone'],
			'answers'             => $answers,
			'calculated_total'    => $result['total'],
			'is_custom_quote'     => $result['is_custom_quote'],
			'custom_quote_reason' => $result['custom_quote_reason'],
		) );

		return array(
			'submission_id' => $submission_id,
			'result'        => $result,
		);
	}

	/**
	 * Process a Business quote submission end to end.
	 *
	 * @param array  $contact       Same shape as above
	 * @param string $entity_type   'c_corp' | 's_corp' | 'partnership'
	 * @param string $asset_band_label    e.g. 'Under $250K' — must match a
	 *                                    band_label in wp_tqb_rate_bands
	 * @param string $revenue_band_label  e.g. 'Under $250K'
	 * @param array  $extra_answers       Same shape as Individual $answers,
	 *                                    for the Part B extras
	 * @return array [ 'submission_id' => int, 'result' => <engine result array> ]
	 * @throws InvalidArgumentException if the asset/revenue band labels don't
	 *         match any row in the rate bands table (front-end should only
	 *         ever send values from the dropdowns populated by TQB_DB, so
	 *         this should not happen in normal operation — it's a safety net).
	 */
	public static function process_business_quote(
		array $contact,
		string $entity_type,
		string $asset_band_label,
		string $revenue_band_label,
		array $extra_answers
	) {
		$entity_group = ( 'partnership' === $entity_type ) ? 'partnership' : 'c_s_corp';

		$asset_bands = TQB_DB::get_asset_bands( $entity_group );
		$asset_band  = self::find_band_by_label( $asset_bands, $asset_band_label );

		$revenue_bands = TQB_DB::get_revenue_addons();
		$revenue_band  = self::find_band_by_label( $revenue_bands, $revenue_band_label );

		if ( ! $asset_band || ! $revenue_band ) {
			throw new InvalidArgumentException( 'Invalid asset or revenue band label — does not match any configured rate band.' );
		}

		$extra_items = TQB_DB::get_line_items( 'business', false );

		$result = TQB_Pricing_Engine::calculate_business(
			$entity_type,
			$asset_band,
			$revenue_band,
			$extra_items,
			$extra_answers
		);

		$answers_to_store = array_merge(
			array(
				'entity_type'   => $entity_type,
				'asset_band'    => $asset_band_label,
				'revenue_band'  => $revenue_band_label,
			),
			$extra_answers
		);

		$submission_id = TQB_DB::insert_submission( array(
			'quote_type'          => 'business',
			'contact_name'        => $contact['name'],
			'contact_email'       => $contact['email'],
			'contact_phone'       => $contact['phone'],
			'answers'             => $answers_to_store,
			'calculated_total'    => $result['total'],
			'is_custom_quote'     => $result['is_custom_quote'],
			'custom_quote_reason' => $result['custom_quote_reason'],
		) );

		return array(
			'submission_id' => $submission_id,
			'result'        => $result,
		);
	}

	/**
	 * Process a combined quote (multiple types: individual + business(es)).
	 *
	 * @param array $contact     [ 'name' => ..., 'email' => ..., 'phone' => ... ]
	 * @param array $quote_types Array of quote types: ['individual', 'business', 'business', ...]
	 * @param array $businesses  Array of business data for each business section
	 * @param array $answers     Answers with composite keys: [type-sectionIndex-key => [selected, qty], ...]
	 * @return array [ 'submission_id' => int, 'result' => combined result ]
	 */
	public static function process_combined_quote(
		array $contact,
		array $quote_types,
		array $businesses,
		array $answers
	) {
		$total = 0;
		$is_custom_quote = false;
		$all_results = array();
		$business_index = 0;

		foreach ( $quote_types as $type ) {
			if ( 'individual' === $type ) {
				// Filter answers for this individual section (type-0-itemkey)
				$prefix = 'individual-0-';
				$section_answers = self::filter_answers_with_prefix( $answers, $prefix );
				$line_items = TQB_DB::get_line_items( 'individual', false );
				$result = TQB_Pricing_Engine::calculate_individual( $line_items, $section_answers );

				$all_results[] = array(
					'type' => 'individual',
					'result' => $result,
				);

				$total += $result['total'];
				$is_custom_quote = $is_custom_quote || $result['is_custom_quote'];
			} elseif ( 'business' === $type ) {
				if ( ! isset( $businesses[ $business_index ] ) ) {
					throw new InvalidArgumentException( 'Missing business data for business index: ' . $business_index );
				}

				$business = $businesses[ $business_index ];
				$prefix = 'business-' . $business_index . '-';
				$section_answers = self::filter_answers_with_prefix( $answers, $prefix );

				$entity_group = ( 'partnership' === $business['entity_type'] ) ? 'partnership' : 'c_s_corp';
				$asset_bands = TQB_DB::get_asset_bands( $entity_group );
				$asset_band = self::find_band_by_label( $asset_bands, $business['asset_band'] );
				$revenue_bands = TQB_DB::get_revenue_addons();
				$revenue_band = self::find_band_by_label( $revenue_bands, $business['revenue_band'] );

				if ( ! $asset_band || ! $revenue_band ) {
					throw new InvalidArgumentException( 'Invalid asset or revenue band label for business ' . ( $business_index + 1 ) );
				}

				$extra_items = TQB_DB::get_line_items( 'business', false );
				$result = TQB_Pricing_Engine::calculate_business(
					$business['entity_type'],
					$asset_band,
					$revenue_band,
					$extra_items,
					$section_answers
				);

				$all_results[] = array(
					'type' => 'business',
					'index' => $business_index,
					'business' => $business,
					'result' => $result,
				);

				$total += $result['total'];
				$is_custom_quote = $is_custom_quote || $result['is_custom_quote'];
				$business_index++;
			}
		}

		// Store combined answers
		$answers_to_store = array(
			'quote_types' => $quote_types,
			'businesses' => $businesses,
			'answers' => $answers,
		);

		$submission_id = TQB_DB::insert_submission( array(
			'quote_type'          => 'combined',
			'contact_name'        => $contact['name'],
			'contact_email'       => $contact['email'],
			'contact_phone'       => $contact['phone'],
			'answers'             => $answers_to_store,
			'calculated_total'    => $total,
			'is_custom_quote'     => $is_custom_quote,
			'custom_quote_reason' => $is_custom_quote ? 'Multiple items requiring custom quote' : null,
		) );

		return array(
			'submission_id' => $submission_id,
			'result' => array(
				'total' => $total,
				'is_custom_quote' => $is_custom_quote,
				'results' => $all_results,
			),
		);
	}

	/**
	 * Filter answers to only include those with a specific prefix.
	 *
	 * @param array  $answers Full answers array
	 * @param string $prefix Key prefix to filter by (e.g., 'individual-0-' or 'business-1-')
	 * @return array Filtered answers with prefix removed from keys
	 */
	private static function filter_answers_with_prefix( array $answers, string $prefix ) {
		$filtered = array();
		foreach ( $answers as $key => $value ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				// Remove prefix from key
				$new_key = substr( $key, strlen( $prefix ) );
				$filtered[ $new_key ] = $value;
			}
		}
		return $filtered;
	}

	private static function find_band_by_label( array $bands, string $label ) {
		foreach ( $bands as $band ) {
			if ( $band['band_label'] === $label ) {
				return $band;
			}
		}
		return null;
	}

	/**
	 * Save partial form progress for abandoned quote follow-up.
	 * Creates a new partial submission or updates existing one by email.
	 *
	 * @param string $email        Contact email
	 * @param int    $step         Current step (1-4)
	 * @param string $quote_types  JSON-encoded array of quote types
	 * @param string $name         Contact name
	 * @param string $phone        Contact phone
	 * @param array  $answers      Answers array
	 * @param array  $businesses   Businesses array
	 *
	 * @return int|WP_Error Submission ID or error
	 */
	public static function save_partial_submission(
		$email,
		$step,
		$quote_types = '',
		$name = '',
		$phone = '',
		$answers = array(),
		$businesses = array()
	) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if status column exists
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		// Build answers JSON
		$answers_json = wp_json_encode( array(
			'quote_types' => $quote_types,
			'answers'     => $answers,
			'businesses'  => $businesses,
		) );

		// Determine quote type
		$quote_type = 'individual';
		if ( ! empty( $quote_types ) ) {
			$types = json_decode( $quote_types, true );
			if ( is_array( $types ) && ! empty( $types ) ) {
				$quote_type = $types[0];
			}
		}

		// Check for existing partial submission by email
		if ( $column_exists ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE contact_email = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
					$email
				),
				ARRAY_A
			);
		} else {
			// No status column yet - just get the most recent by email
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE contact_email = %s ORDER BY created_at DESC LIMIT 1",
					$email
				),
				ARRAY_A
			);
		}

		if ( $existing ) {
			// Update existing partial submission
			$submission_id = $existing['id'];

			if ( $column_exists ) {
				$wpdb->update(
					$table,
					array(
						'contact_name'   => $name,
						'contact_phone'  => $phone,
						'answers'        => $answers_json,
						'last_completed_step' => $step,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $submission_id ),
					array( '%s', '%s', '%s', '%d', '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->update(
					$table,
					array(
						'contact_name'   => $name,
						'contact_phone'  => $phone,
						'answers'        => $answers_json,
					),
					array( 'id' => $submission_id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			}

			return $submission_id;
		}

		// Create new partial submission
		if ( $column_exists ) {
			$result = $wpdb->insert(
				$table,
				array(
					'quote_type'          => $quote_type,
					'contact_name'        => $name,
					'contact_email'       => $email,
					'contact_phone'       => $phone,
					'answers'             => $answers_json,
					'calculated_total'    => null,
					'is_custom_quote'    => 0,
					'status'             => 'in_progress',
					'last_completed_step'=> $step,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
			);
		} else {
			// Fallback for old schema without status/last_completed_step
			$result = $wpdb->insert(
				$table,
				array(
					'quote_type'          => $quote_type,
					'contact_name'        => $name,
					'contact_email'       => $email,
					'contact_phone'       => $phone,
					'answers'             => $answers_json,
					'calculated_total'    => null,
					'is_custom_quote'    => 0,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to save partial submission: ' . $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}
}
