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
	 * Cached column existence checks to avoid repeated INFORMATION_SCHEMA queries.
	 */
	private static $column_cache = array();

	/**
	 * Get cached column existence check.
	 */
	private static function column_exists( $column_name ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		$cache_key = $table . '.' . $column_name;

		if ( isset( self::$column_cache[ $cache_key ] ) ) {
			return self::$column_cache[ $cache_key ];
		}

		// Use INFORMATION_SCHEMA - more reliable across different MySQL configurations
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				$table,
				$column_name
			)
		);

		self::$column_cache[ $cache_key ] = (bool) $exists;
		return (bool) $exists;
	}

	/**
	 * Clear the column cache (useful for testing).
	 */
	public static function clear_column_cache() {
		self::$column_cache = array();
	}

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
	 * Calculates the combined total across every selected individual filer
	 * and every selected business, and returns a per-section breakdown.
	 * Shared by process_combined_quote() and complete_partial_submission()
	 * so both code paths get the same (correct) pricing logic.
	 *
	 * IMPORTANT: loops by the actual length of $individuals / $businesses,
	 * not by counting occurrences of the strings 'individual'/'business' in
	 * $quote_types — that string only ever appears once per type regardless
	 * of how many instances were added via "Add Another Business/Return",
	 * which previously silently limited pricing to instance #1 only.
	 *
	 * @param array $quote_types  ['individual', 'business'] — which types are active at all
	 * @param array $businesses   [ ['name'=>, 'entity_type'=>, 'asset_band'=>, 'revenue_band'=>], ... ]
	 * @param array $individuals  [ ['name'=>, 'filing_status'=>], ... ]
	 * @param array $answers      Flat map: 'individual-0-w2_wages' => ['selected'=>bool,'qty'=>int], ...
	 * @return array [ 'total' => float, 'is_custom_quote' => bool, 'results' => array ]
	 */
	private static function calculate_combined_pricing(
		array $quote_types,
		array $businesses,
		array $individuals,
		array $answers
	) {
		$total = 0.0;
		$is_custom_quote = false;
		$all_results = array();

		if ( in_array( 'individual', $quote_types, true ) ) {
			foreach ( $individuals as $index => $individual ) {
				$prefix = 'individual-' . $index . '-';
				$section_answers = self::filter_answers_with_prefix( $answers, $prefix );
				$line_items = TQB_DB::get_line_items( 'individual', false );

				// Filter to items that apply to this filer's own filing status
				// (NULL/empty filing_status on the item = applies to everyone).
				$filing_status = $individual['filing_status'] ?? '';
				$filtered_items = array_filter( $line_items, function ( $item ) use ( $filing_status ) {
					return empty( $item['filing_status'] ) || $item['filing_status'] === $filing_status;
				} );

				$result = TQB_Pricing_Engine::calculate_individual( array_values( $filtered_items ), $section_answers );

				// Filing status surcharge (Single/MFJ/MFS/HOH admin-configured
				// add-on) — previously shown in the frontend preview but never
				// actually added to the real server-calculated total.
				$surcharge = (float) get_option( 'tqb_filing_status_price_' . $filing_status, 0 );
				if ( ! $result['is_custom_quote'] && null !== $result['total'] ) {
					$result['total'] = round( $result['total'] + $surcharge, 2 );
				}

				$all_results[] = array(
					'type'       => 'individual',
					'index'      => $index,
					'individual' => $individual,
					'result'     => $result,
				);

				if ( $result['is_custom_quote'] ) {
					$is_custom_quote = true;
				} else {
					$total += (float) $result['total'];
				}
			}
		}

		if ( in_array( 'business', $quote_types, true ) ) {
			foreach ( $businesses as $index => $business ) {
				$prefix = 'business-' . $index . '-';
				$section_answers = self::filter_answers_with_prefix( $answers, $prefix );

				$entity_group  = ( 'partnership' === $business['entity_type'] ) ? 'partnership' : 'c_s_corp';
				$asset_bands   = TQB_DB::get_asset_bands( $entity_group );
				$asset_band    = self::find_band_by_label( $asset_bands, $business['asset_band'] );
				$revenue_bands = TQB_DB::get_revenue_addons();
				$revenue_band  = self::find_band_by_label( $revenue_bands, $business['revenue_band'] );

				if ( ! $asset_band || ! $revenue_band ) {
					throw new InvalidArgumentException( 'Invalid asset or revenue band label for business ' . ( $index + 1 ) );
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
					'type'     => 'business',
					'index'    => $index,
					'business' => $business,
					'result'   => $result,
				);

				if ( $result['is_custom_quote'] ) {
					$is_custom_quote = true;
				} else {
					$total += (float) $result['total'];
				}
			}
		}

		return array(
			'total'           => $is_custom_quote ? null : round( $total, 2 ),
			'is_custom_quote' => $is_custom_quote,
			'results'         => $all_results,
		);
	}

	/**
	 * Process a combined quote (multiple types: individual + business(es)).
	 *
	 * @param array $contact     [ 'name' => ..., 'email' => ..., 'phone' => ... ]
	 * @param array $quote_types Array of quote types present: ['individual', 'business']
	 * @param array $businesses  Array of business data, one entry per business instance
	 * @param array $individuals Array of individual filer data, one entry per filer instance
	 * @param array $answers     Answers with composite keys: [type-sectionIndex-key => [selected, qty], ...]
	 * @return array [ 'submission_id' => int, 'result' => combined result ]
	 */
	public static function process_combined_quote(
		array $contact,
		array $quote_types,
		array $businesses,
		array $individuals,
		array $answers
	) {
		$pricing = self::calculate_combined_pricing( $quote_types, $businesses, $individuals, $answers );
		$total = $pricing['total'];
		$is_custom_quote = $pricing['is_custom_quote'];
		$all_results = $pricing['results'];

		// Store combined answers
		$answers_to_store = array(
			'quote_types' => $quote_types,
			'businesses'  => $businesses,
			'individuals' => $individuals,
			'answers'     => $answers,
		);

		$submission_id = TQB_DB::insert_submission( array(
			'quote_type'          => 'combined',
			'contact_name'        => $contact['name'],
			'contact_email'       => $contact['email'],
			'contact_phone'       => $contact['phone'],
			// Legacy single-value column — kept populated with the first
			// business's name for quick-glance display; the full per-business
			// names for multi-business quotes live in the JSON above.
			'business_name'       => ! empty( $businesses[0]['name'] ) ? $businesses[0]['name'] : null,
			'filing_status'       => ! empty( $individuals[0]['filing_status'] ) ? $individuals[0]['filing_status'] : null,
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
	 * Check if email already has a completed submission.
	 *
	 * @param string $email Contact email
	 * @return array|false Submission data if exists and completed, false otherwise
	 */
	public static function check_existing_submission( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if status column exists
		$has_status_column = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		if ( $has_status_column ) {
			// Check for completed submission with this email
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, contact_name, calculated_total, created_at FROM {$table} WHERE contact_email = %s AND status = 'completed' ORDER BY created_at DESC LIMIT 1",
					$email
				),
				ARRAY_A
			);
		} else {
			// No status column - check if there's a submission with a calculated total (completed)
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, contact_name, calculated_total, created_at FROM {$table} WHERE contact_email = %s AND calculated_total IS NOT NULL ORDER BY created_at DESC LIMIT 1",
					$email
				),
				ARRAY_A
			);
		}

		return $result;
	}

	/**
	 * Get existing partial submission by IP address.
	 * Used to auto-populate form when user returns from same device.
	 *
	 * @param string $ip User's IP address
	 * @return array|false Partial submission data if exists, false otherwise
	 */
	public static function get_partial_by_ip( $ip ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if user_ip column exists
		$has_status_column = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'user_ip'" );

		if ( ! $has_status_column ) {
			return false;
		}

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, contact_email, contact_name, contact_phone, answers, last_completed_step FROM {$table} WHERE user_ip = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
				$ip
			),
			ARRAY_A
		);

		return $result;
	}

	/**
	 * Check for existing partial submission using contact info (name + email + phone).
	 *
	 * @param string $name Contact name
	 * @param string $email Contact email
	 * @param string $phone Contact phone
	 * @return array|false Partial submission data or false
	 */
	public static function check_partial_for_resume( $name = '', $email = '', $phone = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		if ( empty( $email ) || empty( $name ) || empty( $phone ) ) {
			return false;
		}

		$partial = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, contact_email, contact_name, contact_phone, answers, last_completed_step FROM {$table} WHERE contact_email = %s AND contact_name = %s AND contact_phone = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
				$email,
				$name,
				$phone
			),
			ARRAY_A
		);

		return $partial;
	}

	/**
	 * Check if IP already has a partial submission with DIFFERENT email.
	 * This helps detect when someone is trying to submit from same IP but different email.
	 *
	 * @param string $ip         User's IP address
	 * @param string $email      User's email
	 * @return array|false Array with 'exists' and optional 'message', or false if no conflict
	 */
	public static function check_ip_email_conflict( $ip, $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if user_ip column exists
		$has_status_column = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'user_ip'" );

		if ( ! $has_status_column ) {
			return false;
		}

		// Find partial with this IP but different email
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT contact_email, contact_name FROM {$table} WHERE user_ip = %s AND status = 'in_progress' AND contact_email != %s ORDER BY created_at DESC LIMIT 1",
				$ip,
				$email
			),
			ARRAY_A
		);

		if ( $result ) {
			return array(
				'exists'  => true,
				'message'  => sprintf(
					'This device already has a quote in progress for %s. Please use the same email (%s) or contact us directly.',
					$result['contact_email'],
					$result['contact_email']
				),
			);
		}

		return false;
	}

	/**
	 * Get existing partial submission ID for an email.
	 *
	 * @param string $email Contact email
	 * @return int|false Partial submission ID if exists, false otherwise
	 */
	public static function get_existing_partial_id( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if status column exists
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );
		$has_status_column = in_array( 'status', $column_names, true );

		// First, try to find a record with status = 'in_progress'
		if ( $has_status_column ) {
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE contact_email = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
					$email
				)
			);
		} else {
			// Fallback for old databases without status column
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE contact_email = %s AND calculated_total IS NULL ORDER BY created_at DESC LIMIT 1",
					$email
				)
			);
		}

		return $result ? (int) $result : false;
	}

	/**
	 * Complete an existing partial submission with final data.
	 * Also cleans up any other partial submissions for the same email.
	 *
	 * @param int   $partial_id   Partial submission ID to complete
	 * @param array $contact      Contact info
	 * @param array $quote_types  Quote types
	 * @param array $businesses   Business data
	 * @param array $individuals  Individual filer data
	 * @param array $answers      Answers
	 *
	 * @return array Result with submission_id and calculation result
	 */
	public static function complete_partial_submission(
		$partial_id,
		array $contact,
		array $quote_types,
		array $businesses,
		array $individuals,
		array $answers
	) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		$pricing = self::calculate_combined_pricing( $quote_types, $businesses, $individuals, $answers );
		$total = $pricing['total'];
		$is_custom_quote = $pricing['is_custom_quote'];
		$all_results = $pricing['results'];

		// Store combined answers
		$answers_to_store = array(
			'quote_types' => $quote_types,
			'businesses'  => $businesses,
			'individuals' => $individuals,
			'answers'     => $answers,
		);

		$business_name = ! empty( $businesses[0]['name'] ) ? $businesses[0]['name'] : null;
		$filing_status = ! empty( $individuals[0]['filing_status'] ) ? $individuals[0]['filing_status'] : null;

		// Update the partial submission to completed
		$now = current_time( 'mysql' );
		$has_status_column = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		if ( $has_status_column ) {
			$wpdb->update(
				$table,
				array(
					'quote_type'          => 'combined',
					'contact_name'        => $contact['name'],
					'contact_email'       => $contact['email'],
					'contact_phone'       => $contact['phone'],
					'business_name'       => $business_name,
					'filing_status'       => $filing_status,
					'answers'             => wp_json_encode( $answers_to_store ),
					'calculated_total'     => $total,
					'is_custom_quote'     => $is_custom_quote,
					'custom_quote_reason' => $is_custom_quote ? 'Multiple items requiring custom quote' : null,
					'status'              => 'completed',
					'last_completed_step' => 5,
					'updated_at'          => $now,
				),
				array( 'id' => $partial_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			// Delete other partial submissions for this email (cleanup old duplicates)
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE contact_email = %s AND id != %d AND status = 'in_progress'",
					$contact['email'],
					$partial_id
				)
			);
		} else {
			$wpdb->update(
				$table,
				array(
					'quote_type'          => 'combined',
					'contact_name'        => $contact['name'],
					'contact_email'       => $contact['email'],
					'contact_phone'       => $contact['phone'],
					'business_name'       => $business_name,
					'filing_status'       => $filing_status,
					'answers'             => wp_json_encode( $answers_to_store ),
					'calculated_total'    => $total,
					'is_custom_quote'     => $is_custom_quote,
					'custom_quote_reason' => $is_custom_quote ? 'Multiple items requiring custom quote' : null,
				),
				array( 'id' => $partial_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s' ),
				array( '%d' )
			);

			// Delete other partial submissions for this email (cleanup old duplicates)
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE contact_email = %s AND id != %d AND calculated_total IS NULL",
					$contact['email'],
					$partial_id
				)
			);
		}

		return array(
			'submission_id' => $partial_id,
			'result' => array(
				'total' => $total,
				'is_custom_quote' => $is_custom_quote,
				'results' => $all_results,
			),
		);
	}

	/**
	 * Save partial form progress for abandoned quote follow-up.
	 * Creates a new partial submission or updates existing one by email.
	 * 
	 * SECURITY: Only allows updates if contact info matches.
	 * Also tracks IP to detect conflicts when same device tries different email.
	 *
	 * @param string $email        Contact email
	 * @param int    $step         Current step (1-4)
	 * @param string $quote_types  JSON-encoded array of quote types
	 * @param string $name         Contact name (used for verification)
	 * @param string $phone        Contact phone (used for verification)
	 * @param array  $answers      Answers array
	 * @param array  $businesses   Businesses array
	 * @param string $user_ip      User's IP address
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
		$businesses = array(),
		$user_ip = ''
	) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Use cached column existence checks
		$has_status_column = self::column_exists( 'status' );
		$has_ip_column = self::column_exists( 'user_ip' );

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
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, contact_name, contact_phone FROM {$table} WHERE contact_email = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
					$email
				),
				ARRAY_A
			);

			// If not found, try to find a record with NULL calculated_total AND NOT abandoned
			// This handles old records that might have incorrect status values
			if ( ! $existing && $has_status_column ) {
				$existing = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, contact_name, contact_phone FROM {$table} WHERE contact_email = %s AND calculated_total IS NULL AND status != 'abandoned' ORDER BY created_at DESC LIMIT 1",
						$email
					),
					ARRAY_A
				);
			}

			// Fallback for databases without status column
			if ( ! $existing && ! $has_status_column ) {
				$existing = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, contact_name, contact_phone FROM {$table} WHERE contact_email = %s AND calculated_total IS NULL ORDER BY created_at DESC LIMIT 1",
						$email
					),
					ARRAY_A
				);
			}

			if ( $existing ) {
				// SECURITY: Verify contact info matches before allowing update
				// This prevents someone from modifying another person's form
				$name_match = strcasecmp( trim( $existing['contact_name'] ), trim( $name ) ) === 0;
				$phone_match = strcasecmp( trim( $existing['contact_phone'] ), trim( $phone ) ) === 0;

				// If contact info doesn't match, don't allow updates
				if ( ! $name_match || ! $phone_match ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 
						'contact_mismatch', 
						'Contact information does not match existing submission. Please use the original name and phone.' 
					);
				}

				// Contact info matches - safe to update
				$submission_id = $existing['id'];

			$update_data = array(
				'answers'        => $answers_json,
				'last_completed_step' => $step,
				'updated_at' => current_time( 'mysql' ),
			);
			
			// Update status to in_progress if the column exists (handles old records with incorrect status)
			if ( $has_status_column ) {
				$update_data['status'] = 'in_progress';
			}

			// Update IP if column exists and we have an IP
			if ( $has_ip_column && ! empty( $user_ip ) ) {
				$update_data['user_ip'] = $user_ip;
			}

			$update_format = array( '%s', '%d', '%s' );
			if ( $has_status_column ) {
				$update_format[] = '%s';
			}
			if ( $has_ip_column && ! empty( $user_ip ) ) {
				$update_format[] = '%s';
			}

			$wpdb->update(
				$table,
				$update_data,
				array( 'id' => $submission_id ),
				$update_format,
				array( '%d' )
			);

			return $submission_id;
		}

		// Create new partial submission (first time with contact info)
		$insert_data = array(
			'quote_type'          => $quote_type,
			'contact_name'        => $name,
			'contact_email'       => $email,
			'contact_phone'       => $phone,
			'answers'             => $answers_json,
			'calculated_total'    => null,
			'is_custom_quote'    => 0,
		);

		$insert_format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

		if ( $has_status_column ) {
			$insert_data['status'] = 'in_progress';
			$insert_data['last_completed_step'] = $step;
			$insert_format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' );
		}

		if ( $has_ip_column && ! empty( $user_ip ) ) {
			$insert_data['user_ip'] = $user_ip;
			$insert_format[] = '%s';
		}

		$result = $wpdb->insert( $table, $insert_data, $insert_format );

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to save partial submission: ' . $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}
}
