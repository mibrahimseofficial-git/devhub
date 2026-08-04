<?php
/**
 * TQB_Pricing_Engine
 *
 * Pure calculation logic — deliberately has ZERO WordPress dependencies
 * ($wpdb, get_option, etc. never appear here). It takes plain PHP arrays in
 * and returns plain PHP arrays out. This means:
 *   1. It can be unit tested standalone (see tests/test-pricing-engine.php)
 *   2. The WordPress-facing code (TQB_DB, front-end form handler) is
 *      responsible for fetching data from the DB and shaping it into the
 *      arrays this class expects — this class never touches the database.
 *
 * See PROJECT_SPEC.md Sections 3 & 4 for the business rules this implements.
 */

if ( ! class_exists( 'TQB_Pricing_Engine' ) ) {

class TQB_Pricing_Engine {

	/**
	 * Calculate an Individual return quote.
	 *
	 * @param array $line_items  All individual line item config rows, each:
	 *   [
	 *     'item_key' => 'rental_property',
	 *     'fee' => 200.0,
	 *     'pricing_pattern' => 'qty_times_fee' | 'flat',
	 *     'is_custom_quote_trigger' => 0 | 1,
	 *     'threshold_qty' => 100.0 | null,        // Quantity threshold (e.g. 100 = $100K)
	 *     'threshold_trigger' => 'above' | null,  // 'above' = qty > threshold triggers custom; 'below' = qty < threshold triggers custom
	 *   ]
	 * @param array $answers  Keyed by item_key:
	 *   [ 'rental_property' => [ 'selected' => true, 'qty' => 2 ], ... ]
	 *   The 'w2_wages' key should always be selected (it's the mandatory base).
	 *
	 * @return array {
	 *   @type float|null $total               Null if is_custom_quote is true
	 *   @type bool       $is_custom_quote
	 *   @type string|null $custom_quote_reason  item_key of the trigger, if any
	 *   @type array      $line_item_breakdown  item_key => calculated amount, for transparency/debugging
	 * }
	 */
	public static function calculate_individual( array $line_items, array $answers ) {
		$breakdown         = array();
		$is_custom_quote   = false;
		$custom_reason     = null;
		$total             = 0.0;

		foreach ( $line_items as $item ) {
			$key = $item['item_key'];

			if ( empty( $answers[ $key ]['selected'] ) ) {
				continue;
			}

			// Check if this item has a threshold-based custom quote trigger
			// Pass full answer object (qty, dollar_value, etc.)
			if ( self::evaluate_thresholds( $item, $answers[ $key ] ?? array() ) ) {
				$is_custom_quote = true;
				$custom_reason   = $key;
				continue;
			}

			// A hard custom-quote trigger (crypto, foreign accounts) short-circuits
			// pricing entirely — per PROJECT_SPEC.md Section 3, these never
			// get an auto-calculated number.
			if ( ! empty( $item['is_custom_quote_trigger'] ) ) {
				$is_custom_quote = true;
				$custom_reason   = $key;
				continue;
			}

			$qty    = isset( $answers[ $key ]['qty'] ) ? (int) $answers[ $key ]['qty'] : 1;
			$amount = self::calculate_line_amount( $item, $qty );

			$breakdown[ $key ] = $amount;
			$total            += $amount;
		}

		return array(
			'total'                => $is_custom_quote ? null : round( $total, 2 ),
			'is_custom_quote'      => $is_custom_quote,
			'custom_quote_reason'  => $custom_reason,
			'line_item_breakdown'  => $breakdown,
		);
	}

	/**
	 * Evaluates whether an item's threshold conditions are met.
	 * Supports both new JSON-based thresholds and legacy threshold_qty/threshold_trigger format.
	 *
	 * @param array $item     Line item config
	 * @param array $answers  User answers for this item: ['qty' => N, 'dollar_value' => M, 'selected' => true/false]
	 *
	 * @return bool True if threshold conditions are met (should trigger custom quote)
	 */
	private static function evaluate_thresholds( $item, $answers ) {
		// No threshold at all — never triggers
		if ( empty( $item['threshold_rules'] ) && empty( $item['threshold_qty'] ) && empty( $item['threshold_trigger'] ) ) {
			return false;
		}

		// New JSON format: threshold_rules
		if ( ! empty( $item['threshold_rules'] ) ) {
			return self::evaluate_threshold_rules_json( $item['threshold_rules'], $answers );
		}

		// Legacy format: threshold_qty + threshold_trigger (backward compat)
		if ( ! empty( $item['threshold_qty'] ) && ! empty( $item['threshold_trigger'] ) ) {
			$qty = isset( $answers['qty'] ) ? (int) $answers['qty'] : 1;
			return self::evaluate_legacy_threshold( $item['threshold_qty'], $item['threshold_trigger'], $qty );
		}

		return false;
	}

	/**
	 * Evaluates new JSON-based threshold rules.
	 *
	 * @param string $json_rules  JSON string: {"logic":"AND|OR","conditions":[...]}
	 * @param array  $answers     User answers: ['qty' => N, 'dollar_value' => M]
	 *
	 * @return bool True if conditions are met
	 */
	private static function evaluate_threshold_rules_json( $json_rules, $answers ) {
		$rules = json_decode( $json_rules, true );

		if ( ! is_array( $rules ) || empty( $rules['conditions'] ) ) {
			return false;
		}

		$logic      = isset( $rules['logic'] ) ? $rules['logic'] : 'AND';
		$conditions = $rules['conditions'];
		$results    = array();

		foreach ( $conditions as $condition ) {
			$type     = isset( $condition['type'] ) ? $condition['type'] : 'qty';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'above';
			$value    = isset( $condition['value'] ) ? (float) $condition['value'] : 0;

			$condition_met = false;

			if ( 'qty' === $type ) {
				$qty           = isset( $answers['qty'] ) ? (int) $answers['qty'] : 1;
				$condition_met = ( 'above' === $operator ) ? ( $qty > $value ) : ( $qty < $value );
			} elseif ( 'dollar_value' === $type ) {
				$dollar_value  = isset( $answers['dollar_value'] ) ? (float) $answers['dollar_value'] : 0;
				$condition_met = ( 'above' === $operator ) ? ( $dollar_value > $value ) : ( $dollar_value < $value );
			}

			$results[] = $condition_met;
		}

		// Combine with AND or OR logic
		if ( 'AND' === $logic ) {
			return ! in_array( false, $results, true ); // All must be true
		} else { // OR
			return in_array( true, $results, true ); // At least one must be true
		}
	}

	/**
	 * Evaluates legacy threshold format for backward compatibility.
	 *
	 * @param float  $threshold_qty  The threshold value
	 * @param string $trigger        'above' or 'below'
	 * @param int    $qty            User-selected quantity
	 *
	 * @return bool True if threshold is triggered
	 */
	private static function evaluate_legacy_threshold( $threshold_qty, $trigger, $qty ) {
		$threshold = (float) $threshold_qty;

		if ( 'above' === $trigger ) {
			return $qty > $threshold;
		} elseif ( 'below' === $trigger ) {
			return $qty < $threshold;
		}

		return false;
	}

	/**
	 * Check if an item should trigger a custom quote based on quantity threshold.
	 * DEPRECATED: Use evaluate_thresholds() instead.
	 * Kept for backward compatibility with client-side preview code.
	 *
	 * @param array $item  Line item config
	 * @param int   $qty   User-selected quantity
	 *
	 * @return bool True if custom quote should be triggered
	 */
	private static function should_trigger_custom_quote( $item, $qty ) {
		if ( empty( $item['threshold_trigger'] ) || empty( $item['threshold_qty'] ) ) {
			return false;
		}

		$threshold = (float) $item['threshold_qty'];
		$trigger   = $item['threshold_trigger'];

		if ( $trigger === 'above' ) {
			return $qty > $threshold;
		} elseif ( $trigger === 'below' ) {
			return $qty < $threshold;
		}

		return false;
	}

	/**
	 * Calculate a Business return quote.
	 *
	 * Implements the exact 3-step priority order from PROJECT_SPEC.md
	 * Section 4, Part A:
	 *   Step 1: asset band = Custom → route to custom quote, stop.
	 *   Step 2: Schedule L not required (entity-specific asset+revenue
	 *           thresholds) → flat $999 base, skip asset-band lookup.
	 *   Step 3: otherwise → asset-band price + revenue add-on.
	 * Then adds Part B extras (same line-item pattern as Individual) on top,
	 * unless Step 1 already triggered a custom quote.
	 *
	 * @param string $entity_type   'c_corp' | 's_corp' | 'partnership'
	 * @param array  $asset_band    The single matched row from wp_tqb_rate_bands
	 *                              for band_type=asset_band, this entity_group:
	 *                              [ 'band_label', 'band_min', 'band_max', 'price', 'is_custom' ]
	 * @param array  $revenue_band  The matched row for band_type=revenue_addon:
	 *                              [ 'band_label', 'band_min', 'band_max', 'price' ]
	 *                              ('price' here is the add-on amount, e.g. 0 or 200)
	 * @param array  $extra_line_items  Business Part B line item configs (same shape as Individual)
	 * @param array  $extra_answers     Same shape as Individual $answers
	 *
	 * @return array  Same shape as calculate_individual(), plus:
	 *   @type float|null $base_fee   The Part A result, before extras
	 */
	public static function calculate_business(
		string $entity_type,
		array $asset_band,
		array $revenue_band,
		array $extra_line_items,
		array $extra_answers
	) {
		$entity_group = self::entity_group_for( $entity_type );

		// --- Step 1: asset band itself is a "Custom" band (5M-10M, Over 10M) ---
		if ( ! empty( $asset_band['is_custom'] ) ) {
			return array(
				'total'               => null,
				'base_fee'            => null,
				'is_custom_quote'     => true,
				'custom_quote_reason' => 'assets_over_5m',
				'line_item_breakdown' => array(),
			);
		}

		// --- Step 2: Schedule L threshold check ---
		// Uses the selected band's upper bound as the comparison value, since
		// the front-end form captures a band selection (dropdown), not a raw
		// dollar figure. See PROJECT_SPEC.md Section 4 for the exact thresholds.
		$asset_threshold   = ( 'partnership' === $entity_group ) ? 1000000 : 250000;
		$revenue_threshold = 250000;

		$schedule_l_not_required =
			self::band_max_within( $asset_band, $asset_threshold ) &&
			self::band_max_within( $revenue_band, $revenue_threshold );

		if ( $schedule_l_not_required ) {
			$base_fee = 999.00;
		} else {
			// --- Step 3: asset-band price + revenue add-on ---
			$base_fee = (float) $asset_band['price'] + (float) $revenue_band['price'];
		}

		// --- Part B: extras (same engine as Individual line items) ---
		$extras_result = self::calculate_individual( $extra_line_items, $extra_answers );

		// Note: per PROJECT_SPEC.md, extras themselves are not expected to
		// contain a custom-quote-trigger item on the Business side today, but
		// the check is included for safety/future-proofing.
		if ( $extras_result['is_custom_quote'] ) {
			return array(
				'total'               => null,
				'base_fee'            => round( $base_fee, 2 ),
				'is_custom_quote'     => true,
				'custom_quote_reason' => $extras_result['custom_quote_reason'],
				'line_item_breakdown' => $extras_result['line_item_breakdown'],
			);
		}

		$total = $base_fee + $extras_result['total'];

		return array(
			'total'               => round( $total, 2 ),
			'base_fee'            => round( $base_fee, 2 ),
			'is_custom_quote'     => false,
			'custom_quote_reason' => null,
			'line_item_breakdown' => $extras_result['line_item_breakdown'],
		);
	}

	/**
	 * Calculates a single line item's dollar amount based on its pricing_pattern.
	 * This directly encodes the 3 formula patterns discovered in the client's
	 * real Excel calculator (PROJECT_SPEC.md Section 3).
	 */
	private static function calculate_line_amount( array $item, int $qty ) {
		switch ( $item['pricing_pattern'] ) {
			case 'flat':
				// IF(Yes, Fee, 0) — qty is ignored entirely.
				return (float) $item['fee'];

			case 'qty_times_fee':
			default:
				// IF(Yes, Qty*Fee, 0)
				return $qty * (float) $item['fee'];
		}
	}

	/**
	 * Maps a UI entity_type value to the entity_group used in the rate_bands
	 * table ('c_s_corp' or 'partnership') — C-Corp and S-Corp share the same
	 * price grid, per PROJECT_SPEC.md Section 4.
	 */
	private static function entity_group_for( string $entity_type ) {
		return ( 'partnership' === $entity_type ) ? 'partnership' : 'c_s_corp';
	}

	/**
	 * True if a band's upper bound falls at-or-under a threshold. A band with
	 * no upper limit (band_max === null, e.g. "Over $10M") never qualifies.
	 */
	private static function band_max_within( array $band, $threshold ) {
		if ( ! isset( $band['band_max'] ) || null === $band['band_max'] ) {
			return false;
		}
		return (float) $band['band_max'] <= (float) $threshold;
	}
}

}
