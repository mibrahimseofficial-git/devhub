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
	 * Check if email already has a completed submission.
	 *
	 * @param string $email Contact email
	 * @return array|false Submission data if exists and completed, false otherwise
	 */
	public static function check_existing_submission( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Check if status column exists
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		if ( $column_exists ) {
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
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'user_ip'" );

		if ( ! $column_exists ) {
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
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'user_ip'" );

		if ( ! $column_exists ) {
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
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		if ( $column_exists ) {
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE contact_email = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
					$email
				)
			);
		} else {
			// No status column - check for record with NULL calculated_total (partial)
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
	 * @param array $answers      Answers
	 *
	 * @return array Result with submission_id and calculation result
	 */
	public static function complete_partial_submission(
		$partial_id,
		array $contact,
		array $quote_types,
		array $businesses,
		array $answers
	) {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Calculate the quote
		$total = 0;
		$is_custom_quote = false;
		$all_results = array();
		$business_index = 0;

		foreach ( $quote_types as $type ) {
			if ( 'individual' === $type ) {
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
					throw new InvalidArgumentException( 'Missing business data' );
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
					throw new InvalidArgumentException( 'Invalid asset or revenue band' );
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

		// Update the partial submission to completed
		$now = current_time( 'mysql' );
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		if ( $column_exists ) {
			$wpdb->update(
				$table,
				array(
					'quote_type'          => 'combined',
					'contact_name'        => $contact['name'],
					'contact_email'       => $contact['email'],
					'contact_phone'       => $contact['phone'],
					'answers'             => $answers_to_store,
					'calculated_total'     => $total,
					'is_custom_quote'     => $is_custom_quote,
					'custom_quote_reason' => $is_custom_quote ? 'Multiple items requiring custom quote' : null,
					'status'              => 'completed',
					'last_completed_step' => 5,
					'updated_at'          => $now,
				),
				array( 'id' => $partial_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%d', '%s' ),
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
					'answers'             => $answers_to_store,
					'calculated_total'    => $total,
					'is_custom_quote'     => $is_custom_quote,
					'custom_quote_reason' => $is_custom_quote ? 'Multiple items requiring custom quote' : null,
				),
				array( 'id' => $partial_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s' ),
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

		// Check if status column exists
		$column_exists = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'status'" );

		// Check if user_ip column exists
		$has_ip_column = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'user_ip'" );

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
					"SELECT id, contact_name, contact_phone FROM {$table} WHERE contact_email = %s AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
					$email
				),
				ARRAY_A
			);
		} else {
			// No status column yet - just get the most recent by email
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, contact_name, contact_phone FROM {$table} WHERE contact_email = %s ORDER BY created_at DESC LIMIT 1",
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
			
			// Update IP if column exists and we have an IP
			if ( $has_ip_column && ! empty( $user_ip ) ) {
				$update_data['user_ip'] = $user_ip;
			}

			$update_format = array( '%s', '%d', '%s' );
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

		// No existing partial by email - check IP conflict
		if ( $has_ip_column && ! empty( $user_ip ) ) {
			$ip_conflict = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT contact_email FROM {$table} WHERE user_ip = %s AND status = 'in_progress' LIMIT 1",
					$user_ip
				)
			);

			if ( $ip_conflict && $ip_conflict->contact_email !== $email ) {
				return new WP_Error(
					'ip_conflict',
					sprintf(
						'This device already has a quote in progress for %s. Please use the same email or contact us directly.',
						$ip_conflict->contact_email
					)
				);
			}
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

		if ( $column_exists ) {
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
