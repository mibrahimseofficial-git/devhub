<?php
/**
 * Question Sets Manager — handles filing-status-aware question loading with inheritance.
 * Supports base sets + variant overrides (Option C architecture).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Question_Sets {

	/**
	 * Loads a complete question set with inheritance merging.
	 * For filing status variants, loads base set + overrides and merges them.
	 *
	 * @param string $return_type 'individual' or 'business'
	 * @param string $filing_status Optional filing status (single/mfj/mfs/hoh), NULL for business
	 * @return array Array of question items with all values resolved
	 */
	public static function get_questions( $return_type, $filing_status = null ) {
		global $wpdb;

		$sets_table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;
		$items_table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;
		$line_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		// Get the appropriate question set
		if ( 'individual' === $return_type && ! empty( $filing_status ) ) {
			// Variant set — load base + this variant
			$base_set = self::get_set_by_name( 'Individual' );
			$variant_set = self::get_set_by_name( 'Individual_' . $filing_status );

			if ( ! $base_set || ! $variant_set ) {
				return array(); // Fallback if sets don't exist
			}

			// Load base questions
			$base_questions = self::get_set_questions( $base_set['id'] );

			// Load variant overrides
			$variant_overrides = self::get_set_questions( $variant_set['id'] );

			// Merge (variant overrides base, rest inherit)
			return self::merge_questions( $base_questions, $variant_overrides, $filing_status );
		} else {
			// Get base set
			$set_name = 'individual' === $return_type ? 'Individual' : 'Business';
			$set = self::get_set_by_name( $set_name );

			if ( ! $set ) {
				return array();
			}

			return self::get_set_questions( $set['id'], $filing_status );
		}
	}

	/**
	 * Gets a question set by name.
	 *
	 * @param string $name Set name (e.g. 'Individual', 'Individual_MFJ', 'Business')
	 * @return array Set record or empty array
	 */
	private static function get_set_by_name( $name ) {
		global $wpdb;

		$table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE name = %s AND is_active = 1",
				$name
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Gets all questions in a set with line item data merged in.
	 *
	 * @param int $set_id Question set ID
	 * @param string $filing_status Optional filing status for context
	 * @return array Questions with full data
	 */
	private static function get_set_questions( $set_id, $filing_status = null ) {
		global $wpdb;

		$items_table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;
		$line_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		// Get set items with overrides and line item data
		$query = $wpdb->prepare(
			"SELECT 
				si.id,
				si.question_set_id,
				si.line_item_id,
				si.sort_order,
				si.override_label,
				si.override_followup_label,
				si.override_fee,
				si.override_reveal_followup,
				si.is_hidden,
				li.id as line_id,
				li.item_key,
				li.label,
				li.fee,
				li.pricing_pattern,
				li.is_custom_quote_trigger,
				li.threshold_rules,
				li.reveal_followup,
				li.is_active,
				li.tooltip,
				li.notes
			FROM {$items_table} si
			JOIN {$line_table} li ON li.id = si.line_item_id
			WHERE si.question_set_id = %d AND si.is_hidden = 0
			ORDER BY si.sort_order ASC",
			$set_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		// Resolve overrides (NULL = use base value)
		$questions = array();
		foreach ( $rows as $row ) {
			$questions[] = array(
				'id'                   => $row['line_id'],
				'item_key'             => $row['item_key'],
				'label'                => $row['override_label'] ?? $row['label'], // Use override if set
				'fee'                  => $row['override_fee'] ?? $row['fee'],
				'pricing_pattern'      => $row['pricing_pattern'],
				'is_custom_quote_trigger' => $row['is_custom_quote_trigger'],
				'threshold_rules'      => $row['threshold_rules'],
				'reveal_followup'      => (int) ( $row['override_reveal_followup'] ?? $row['reveal_followup'] ),
				'is_active'            => $row['is_active'],
				'tooltip'              => $row['tooltip'],
				'followup_label'       => $row['override_followup_label'] ?? null,
				'set_item_id'          => $row['id'],
			);
		}

		return $questions;
	}

	/**
	 * Merges base questions with variant overrides.
	 * Variant questions override base, rest inherit from base.
	 *
	 * @param array $base_questions Base set questions
	 * @param array $variant_questions Variant set questions (contains overrides)
	 * @param string $filing_status Filing status for context
	 * @return array Merged questions
	 */
	private static function merge_questions( $base_questions, $variant_questions, $filing_status ) {
		// Build a map of variant overrides by item_key
		$variant_map = array();
		foreach ( $variant_questions as $vq ) {
			$variant_map[ $vq['item_key'] ] = $vq;
		}

		// Merge: variant overrides base
		$merged = array();
		foreach ( $base_questions as $bq ) {
			if ( isset( $variant_map[ $bq['item_key'] ] ) ) {
				// Use variant (which has overrides applied in get_set_questions)
				$merged[] = $variant_map[ $bq['item_key'] ];
			} else {
				// Use base
				$merged[] = $bq;
			}
		}

		return $merged;
	}

	/**
	 * Gets filing status pricing surcharge.
	 *
	 * @param string $filing_status Filing status (single/mfj/mfs/hoh)
	 * @return float Surcharge amount
	 */
	public static function get_filing_status_surcharge( $filing_status ) {
		$surcharges = TQB_FILING_STATUS_PRICES;
		return isset( $surcharges[ $filing_status ] ) ? (float) $surcharges[ $filing_status ] : 0;
	}

	/**
	 * Gets all filing status options as array.
	 *
	 * @return array Array of filing status => label
	 */
	public static function get_filing_statuses() {
		return TQB_FILING_STATUS_LABELS;
	}

	/**
	 * Calculates total price with filing status surcharge.
	 *
	 * @param float $base_price Base price from questions
	 * @param string $filing_status Filing status
	 * @return float Total price with surcharge
	 */
	public static function apply_filing_status_price( $base_price, $filing_status ) {
		$surcharge = self::get_filing_status_surcharge( $filing_status );
		return $base_price + $surcharge;
	}
}
