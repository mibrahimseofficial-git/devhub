<?php
/**
 * Central DB access helper. All other classes (rules engine, admin, public
 * form handler) should go through this class rather than querying $wpdb
 * directly — keeps table names/queries in one place if schema changes later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_DB {

	/**
	 * Get all active line items for a given quote type, ordered for display.
	 * Used by both the rules engine (Phase 2) and the front-end form (Phase 4).
	 *
	 * @param string $quote_type 'individual' or 'business'
	 * @param bool   $active_only If false, includes inactive items (for admin UI)
	 * @return array
	 */
	public static function get_line_items( $quote_type, $active_only = true ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		$sql = "SELECT * FROM {$table} WHERE quote_type = %s";
		$params = array( $quote_type );

		if ( $active_only ) {
			$sql .= " AND is_active = 1";
		}

		$sql .= " ORDER BY sort_order ASC";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Get a single line item by its stable key (e.g. 'crypto', 'rental_property').
	 */
	public static function get_line_item( $quote_type, $item_key ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quote_type = %s AND item_key = %s",
				$quote_type,
				$item_key
			),
			ARRAY_A
		);
	}

	/**
	 * Get asset-band rows for a given entity group ('c_s_corp' or 'partnership'),
	 * ordered so the rules engine can walk them band-by-band.
	 */
	public static function get_asset_bands( $entity_group ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_RATE_BANDS;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE band_type = 'asset_band' AND entity_group = %s ORDER BY sort_order ASC",
				$entity_group
			),
			ARRAY_A
		);
	}

	/**
	 * Get the revenue add-on bands (shared across entity types).
	 */
	public static function get_revenue_addons() {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_RATE_BANDS;

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE band_type = 'revenue_addon' ORDER BY sort_order ASC",
			ARRAY_A
		);
	}

	/**
	 * Insert a new submission record. Returns the new row's ID, or false on failure.
	 *
	 * @param array $data {
	 *     @type string $quote_type
	 *     @type string $contact_name
	 *     @type string $contact_email
	 *     @type string $contact_phone
	 *     @type array  $answers            Raw answers, will be JSON-encoded
	 *     @type float|null $calculated_total  Null if custom quote
	 *     @type bool   $is_custom_quote
	 *     @type string|null $custom_quote_reason
	 * }
	 */
	public static function insert_submission( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_SUBMISSIONS;

		$inserted = $wpdb->insert(
			$table,
			array(
				'quote_type'          => $data['quote_type'],
				'contact_name'        => $data['contact_name'],
				'contact_email'       => $data['contact_email'],
				'contact_phone'       => $data['contact_phone'],
				'answers'             => wp_json_encode( $data['answers'] ),
				'calculated_total'    => $data['calculated_total'] ?? null,
				'is_custom_quote'     => ! empty( $data['is_custom_quote'] ) ? 1 : 0,
				'custom_quote_reason' => $data['custom_quote_reason'] ?? null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get a single submission by ID (used by the email handler to build
	 * confirmation/notification content after a submission is saved).
	 */
	public static function get_submission( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_SUBMISSIONS;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( $row && isset( $row['answers'] ) ) {
			$decoded = json_decode( $row['answers'], true );
			$row['answers'] = is_array( $decoded ) ? $decoded : array();
		}

		return $row;
	}

	/**
	 * Records a successful HubSpot sync: stores the contact/deal IDs
	 * HubSpot returned and flips the hubspot_synced flag. Called by
	 * TQB_Hubspot after both API calls succeed.
	 */
	public static function mark_hubspot_synced( $id, $contact_id, $deal_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_SUBMISSIONS;

		$wpdb->update(
			$table,
			array(
				'hubspot_synced'     => 1,
				'hubspot_contact_id' => $contact_id,
				'hubspot_deal_id'    => $deal_id,
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}
	/**
	 * Marks a submission's confirmation-email-sent flag. Called after
	 * TQB_Email successfully sends (or attempts to send) the prospect's
	 * confirmation email, so we have a record of what actually went out.
	 */
	public static function mark_confirmation_sent( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_SUBMISSIONS;
		$wpdb->update( $table, array( 'confirmation_email_sent' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Marks a submission's team-notified flag, same idea as above.
	 */
	public static function mark_team_notified( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_SUBMISSIONS;
		$wpdb->update( $table, array( 'team_notified' => 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Inserts a new line item.
	 *
	 * @param array $data  Array with keys: quote_type, item_key, label, fee, etc.
	 *
	 * @return int|false  Inserted ID or false on failure
	 */
	public static function insert_line_item( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		// Auto-assign sort_order if not provided
		if ( ! isset( $data['sort_order'] ) || empty( $data['sort_order'] ) ) {
			$max_sort = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(sort_order) FROM {$table} WHERE quote_type = %s",
					$data['quote_type']
				)
			);
			$data['sort_order'] = ( $max_sort ? (int) $max_sort : 0 ) + 10;
		}

		// Prepare defaults
		$insert_data = array(
			'quote_type'              => $data['quote_type'] ?? 'individual',
			'item_key'                => $data['item_key'] ?? 'custom_item_' . time(),
			'label'                   => $data['label'] ?? '',
			'fee'                     => $data['fee'] ?? 0,
			'pricing_pattern'         => $data['pricing_pattern'] ?? 'qty_times_fee',
			'hardcoded_value'         => $data['hardcoded_value'] ?? null,
			'is_custom_quote_trigger' => $data['is_custom_quote_trigger'] ?? 0,
			'threshold_qty'           => $data['threshold_qty'] ?? null,
			'threshold_trigger'       => $data['threshold_trigger'] ?? null,
			'threshold_rules'         => $data['threshold_rules'] ?? null,
			'reveal_followup'         => isset( $data['reveal_followup'] ) ? (int) $data['reveal_followup'] : 1,
			'is_active'               => $data['is_active'] ?? 1,
			'sort_order'              => (int) $data['sort_order'],
			'tooltip'                 => $data['tooltip'] ?? '',
			'notes'                   => $data['notes'] ?? '',
		);

		$result = $wpdb->insert(
			$table,
			$insert_data,
			array( '%s', '%s', '%s', '%f', '%s', '%f', '%d', '%f', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Deletes a line item by ID.
	 *
	 * @param int $item_id Item ID to delete
	 *
	 * @return int|false  Number of rows deleted or false on failure
	 */
	public static function delete_line_item( $item_id ) {
		global $wpdb;

		$table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		$result = $wpdb->delete(
			$table,
			array( 'id' => $item_id ),
			array( '%d' )
		);

		return $result;
	}

	/**
	 * Update a single line item's editable fields (used by the admin dashboard).
	 * Updates label, tooltip, fee, pricing_pattern, hardcoded_value, and is_active.
	 * Note: item_key and quote_type stay fixed to avoid orphaning data tied to the
	 * item_key elsewhere (e.g. past submissions reference these keys).
	 *
	 * @param int   $id    Row ID in wp_tqb_line_items
	 * @param array $data  [ 'label' => string, 'tooltip' => string|null,
	 *                       'fee' => float, 'pricing_pattern' => string,
	 *                       'hardcoded_value' => float|null, 'is_active' => 0|1,
	 *                       'threshold_qty' => float|null, 'threshold_trigger' => string|null,
	 *                       'threshold_rules' => string|null, 'reveal_followup' => 0|1,
	 *                       'sort_order' => int ]
	 * @return bool
	 */
	public static function update_line_item( $id, array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		$set = array(
			'label'           => $data['label'],
			'tooltip'         => $data['tooltip'],
			'fee'             => $data['fee'],
			'pricing_pattern' => $data['pricing_pattern'],
			'hardcoded_value' => $data['hardcoded_value'],
			'is_active'       => $data['is_active'],
		);

		$formats = array( '%s', '%s', '%f', '%s', '%f', '%d' );

		// Add threshold fields if present (legacy format)
		if ( array_key_exists( 'threshold_qty', $data ) ) {
			$set['threshold_qty'] = $data['threshold_qty'];
			$formats[] = $data['threshold_qty'] === null ? '%s' : '%f';
		}

		if ( array_key_exists( 'threshold_trigger', $data ) ) {
			$set['threshold_trigger'] = $data['threshold_trigger'];
			$formats[] = $data['threshold_trigger'] === null ? '%s' : '%s';
		}

		// Add new fields (Task 2)
		if ( array_key_exists( 'threshold_rules', $data ) ) {
			$set['threshold_rules'] = $data['threshold_rules'];
			$formats[] = '%s';
		}

		if ( array_key_exists( 'reveal_followup', $data ) ) {
			$set['reveal_followup'] = $data['reveal_followup'];
			$formats[] = '%d';
		}

		if ( array_key_exists( 'sort_order', $data ) ) {
			$set['sort_order'] = $data['sort_order'];
			$formats[] = '%d';
		}

		$updated = $wpdb->update(
			$table,
			$set,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Update a single rate band row's price (used by the admin dashboard for
	 * editing the Business asset-band grid and revenue add-ons). Band
	 * boundaries (min/max/label) are NOT editable here — changing what a
	 * band range means is a bigger structural change than a price tweak and
	 * isn't part of the MVP admin UI.
	 *
	 * @param int   $id     Row ID in wp_tqb_rate_bands
	 * @param float $price  New price (or null, if this band should become "Custom")
	 * @return bool
	 */
	public static function update_rate_band_price( $id, $price ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_RATE_BANDS;

		$updated = $wpdb->update(
			$table,
			array( 'price' => $price ),
			array( 'id' => $id ),
			array( '%f' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get all rate band rows of a given type (asset_band or revenue_addon),
	 * optionally filtered by entity_group. Used by the admin dashboard to
	 * render the editable grid.
	 */
	public static function get_all_rate_bands( $band_type, $entity_group = null ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_RATE_BANDS;

		if ( $entity_group ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE band_type = %s AND entity_group = %s ORDER BY sort_order ASC",
					$band_type,
					$entity_group
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE band_type = %s ORDER BY sort_order ASC",
				$band_type
			),
			ARRAY_A
		);
	}

	/**
	 * Get a question set by return_type and filing_status.
	 * Used by admin to get filing status variant sets.
	 */
	public static function get_question_set_by_return_and_status( $return_type, $filing_status ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE return_type = %s AND filing_status = %s",
				$return_type,
				$filing_status
			),
			ARRAY_A
		);
	}

	/**
	 * Get a question set item (override) by set ID and line item ID.
	 * Used by admin to get filing status overrides for a question.
	 */
	public static function get_question_set_item( $question_set_id, $line_item_id ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE question_set_id = %d AND line_item_id = %d",
				$question_set_id,
				$line_item_id
			),
			ARRAY_A
		);
	}

	/**
	 * Update a question set item (filing status override).
	 * Used by admin AJAX to save override changes.
	 */
	public static function update_question_set_item( $question_set_id, $line_item_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;

		// Check if item already exists
		$existing = self::get_question_set_item( $question_set_id, $line_item_id );

		if ( $existing ) {
			// Update existing
			return $wpdb->update(
				$table,
				$data,
				array(
					'question_set_id' => $question_set_id,
					'line_item_id'    => $line_item_id,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d' ),
				array( '%d', '%d' )
			);
		} else {
			// Insert new
			$data['question_set_id'] = $question_set_id;
			$data['line_item_id']    = $line_item_id;
			return $wpdb->insert( $table, $data );
		}
	}
}
