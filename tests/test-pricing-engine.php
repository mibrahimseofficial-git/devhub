<?php
/**
 * Standalone test for TQB_Pricing_Engine — no WordPress required.
 * Run with: php tests/test-pricing-engine.php
 *
 * Validates the engine against the ACTUAL example values the client shared
 * from their live Excel calculator (see conversation history / PROJECT_SPEC.md):
 *   - Individual example → $1,075
 *   - Business example (S-Corp, small) → $1,074
 * Plus a handful of edge cases (custom-quote triggers, Schedule L boundary,
 * large-asset custom routing) to lock in behavior before this touches a
 * real WordPress site.
 */

require_once __DIR__ . '/../includes/class-tqb-pricing-engine.php';

$pass = 0;
$fail = 0;

function tqb_assert( $label, $actual, $expected ) {
	global $pass, $fail;
	$ok = ( $actual === $expected );
	if ( $ok ) {
		$pass++;
		echo "  PASS: {$label}\n";
	} else {
		$fail++;
		echo "  FAIL: {$label}\n";
		echo "        expected: " . var_export( $expected, true ) . "\n";
		echo "        actual:   " . var_export( $actual, true ) . "\n";
	}
}

// ---------------------------------------------------------------------
// Shared line item definitions (mirrors the seed data in
// includes/class-tqb-activator.php)
// ---------------------------------------------------------------------

$individual_items = array(
	array( 'item_key' => 'w2_wages', 'fee' => 350, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'multi_state', 'fee' => 150, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'interest_dividends', 'fee' => 25, 'pricing_pattern' => 'flat', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'brokerage_sales', 'fee' => 25, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'rental_property', 'fee' => 200, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'self_employed', 'fee' => 200, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'farm_income', 'fee' => 275, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'k1_received', 'fee' => 50, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'foreign_accounts', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 1 ),
	array( 'item_key' => 'crypto', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 1 ),
	array( 'item_key' => 'tuition', 'fee' => 25, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'childcare', 'fee' => 25, 'pricing_pattern' => 'flat', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'hsa', 'fee' => 25, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'home_sale', 'fee' => 150, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'retirement_distributions', 'fee' => 25, 'pricing_pattern' => 'hardcoded', 'hardcoded_value' => 100, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'meetings', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
);

$business_extra_items = array(
	array( 'item_key' => 'extra_k1s', 'fee' => 25, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'multi_state', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'depreciation_schedule', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'foreign_partner', 'fee' => 350, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'books_dont_match', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
	array( 'item_key' => 'excess_equipment', 'fee' => 250, 'pricing_pattern' => 'qty_times_fee', 'hardcoded_value' => null, 'is_custom_quote_trigger' => 0 ),
);

// ---------------------------------------------------------------------
// TEST 1: Individual — client's real example → expected $1,075
// (W-2 $350, brokerage 1x$25, rental 1x$200, K-1 selected but qty 0,
//  HSA selected but qty 0, Meetings 2x$250 = $500)
// ---------------------------------------------------------------------
echo "TEST 1: Individual — client example (expect \$1,075)\n";

$answers_1 = array(
	'w2_wages'                 => array( 'selected' => true, 'qty' => 1 ),
	'multi_state'               => array( 'selected' => false ),
	'interest_dividends'        => array( 'selected' => false ),
	'brokerage_sales'           => array( 'selected' => true, 'qty' => 1 ),
	'rental_property'           => array( 'selected' => true, 'qty' => 1 ),
	'k1_received'               => array( 'selected' => true, 'qty' => 0 ),
	'hsa'                       => array( 'selected' => true, 'qty' => 0 ),
	'meetings'                  => array( 'selected' => true, 'qty' => 2 ),
);

$result_1 = TQB_Pricing_Engine::calculate_individual( $individual_items, $answers_1 );
tqb_assert( 'total = 1075.0', $result_1['total'], 1075.0 );
tqb_assert( 'not a custom quote', $result_1['is_custom_quote'], false );

// ---------------------------------------------------------------------
// TEST 2: Business — client's real example → S-Corp, Under $250K assets,
// Under $250K revenue, 3 extra K-1s → Schedule L not required → $999 + $75 = $1,074
// ---------------------------------------------------------------------
echo "\nTEST 2: Business — client example, S-Corp small (expect \$1,074)\n";

$asset_band_under_250k = array( 'band_label' => 'Under $250K', 'band_min' => 0, 'band_max' => 250000, 'price' => 1250, 'is_custom' => 0 );
$revenue_band_under_250k = array( 'band_label' => 'Under $250K', 'band_min' => 0, 'band_max' => 250000, 'price' => 0 );

$extra_answers_2 = array(
	'extra_k1s' => array( 'selected' => true, 'qty' => 3 ),
);

$result_2 = TQB_Pricing_Engine::calculate_business(
	's_corp',
	$asset_band_under_250k,
	$revenue_band_under_250k,
	$business_extra_items,
	$extra_answers_2
);
tqb_assert( 'base fee = 999.0 (Schedule L not required)', $result_2['base_fee'], 999.0 );
tqb_assert( 'total = 1074.0', $result_2['total'], 1074.0 );
tqb_assert( 'not a custom quote', $result_2['is_custom_quote'], false );

// ---------------------------------------------------------------------
// TEST 3: Individual — crypto selected → must route to custom quote,
// no total calculated even if other items are present
// ---------------------------------------------------------------------
echo "\nTEST 3: Individual — crypto triggers custom quote\n";

$answers_3 = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 1 ),
);
$result_3 = TQB_Pricing_Engine::calculate_individual( $individual_items, $answers_3 );
tqb_assert( 'total is null', $result_3['total'], null );
tqb_assert( 'is_custom_quote = true', $result_3['is_custom_quote'], true );
tqb_assert( 'reason = crypto', $result_3['custom_quote_reason'], 'crypto' );

// ---------------------------------------------------------------------
// TEST 4: Business — assets Over $10M → must route to custom quote
// regardless of anything else (Step 1 short-circuits before Schedule L check)
// ---------------------------------------------------------------------
echo "\nTEST 4: Business — assets Over \$10M triggers custom quote\n";

$asset_band_over_10m = array( 'band_label' => 'Over $10M', 'band_min' => 10000000, 'band_max' => null, 'price' => null, 'is_custom' => 1 );

$result_4 = TQB_Pricing_Engine::calculate_business(
	'c_corp',
	$asset_band_over_10m,
	$revenue_band_under_250k,
	$business_extra_items,
	array()
);
tqb_assert( 'total is null', $result_4['total'], null );
tqb_assert( 'is_custom_quote = true', $result_4['is_custom_quote'], true );
tqb_assert( 'reason = assets_over_5m', $result_4['custom_quote_reason'], 'assets_over_5m' );

// ---------------------------------------------------------------------
// TEST 5: Business — C-Corp, $250K-$500K assets, Under $250K revenue.
// This is the "small assets but Schedule L still required" edge case:
// C-Corp/S-Corp threshold requires assets under $250K specifically, so a
// $250K-$500K band should NOT qualify for the $999 flat fee, even though
// revenue is small. Expect asset-band lookup: $1,250 + $0 revenue addon.
// ---------------------------------------------------------------------
echo "\nTEST 5: Business — C-Corp \$250K-\$500K assets (Schedule L required, expect \$1,250 base)\n";

$asset_band_250_500k = array( 'band_label' => '$250K-$500K', 'band_min' => 250000, 'band_max' => 500000, 'price' => 1250, 'is_custom' => 0 );

$result_5 = TQB_Pricing_Engine::calculate_business(
	'c_corp',
	$asset_band_250_500k,
	$revenue_band_under_250k,
	$business_extra_items,
	array()
);
tqb_assert( 'base fee = 1250.0 (Schedule L required, asset-band lookup used)', $result_5['base_fee'], 1250.0 );
tqb_assert( 'total = 1250.0 (no extras selected)', $result_5['total'], 1250.0 );

// ---------------------------------------------------------------------
// TEST 6: Business — Partnership, $500K-$1M assets, Under $250K revenue.
// Partnership's Schedule L threshold allows assets up to $1M (not $250K
// like C/S-Corp), so THIS should qualify for the $999 flat fee.
// ---------------------------------------------------------------------
echo "\nTEST 6: Business — Partnership \$500K-\$1M assets (expect Schedule L NOT required, \$999 base)\n";

$asset_band_500k_1m_partnership = array( 'band_label' => '$500K-$1M', 'band_min' => 500000, 'band_max' => 1000000, 'price' => 1250, 'is_custom' => 0 );

$result_6 = TQB_Pricing_Engine::calculate_business(
	'partnership',
	$asset_band_500k_1m_partnership,
	$revenue_band_under_250k,
	$business_extra_items,
	array()
);
tqb_assert( 'base fee = 999.0 (Partnership Schedule L threshold is $1M, not $250K)', $result_6['base_fee'], 999.0 );

// ---------------------------------------------------------------------
// TEST 7: Business — Over $1M revenue adds the $200 revenue add-on
// ---------------------------------------------------------------------
echo "\nTEST 7: Business — Over \$1M revenue adds \$200 add-on\n";

$asset_band_1m_2m = array( 'band_label' => '$1M-$2M', 'band_min' => 1000000, 'band_max' => 2000000, 'price' => 1500, 'is_custom' => 0 );
$revenue_band_over_1m = array( 'band_label' => 'Over $1M', 'band_min' => 1000000, 'band_max' => null, 'price' => 200 );

$result_7 = TQB_Pricing_Engine::calculate_business(
	'c_corp',
	$asset_band_1m_2m,
	$revenue_band_over_1m,
	$business_extra_items,
	array()
);
tqb_assert( 'base fee = 1700.0 (1500 asset-band + 200 revenue addon)', $result_7['base_fee'], 1700.0 );

// ---------------------------------------------------------------------
// TEST 8: Thresholds (NEW Task 2) — Single qty threshold, above
// Item with threshold_rules: qty > 100 should trigger custom quote
// ---------------------------------------------------------------------
echo "\nTEST 8: Threshold — Single qty condition (above)\n";

$item_with_qty_threshold = array(
	'item_key' => 'crypto',
	'fee' => 250,
	'pricing_pattern' => 'qty_times_fee',
	'hardcoded_value' => null,
	'is_custom_quote_trigger' => 0,
	'threshold_rules' => json_encode( array(
		'logic'      => 'AND',
		'conditions' => array(
			array( 'type' => 'qty', 'operator' => 'above', 'value' => 100 ),
		),
	) ),
);

$answers_8_yes = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 150 ),
);

$answers_8_no = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 50 ),
);

$items_8 = array_map( function ( $item ) {
	return ( 'crypto' === $item['item_key'] ) ? $item_with_qty_threshold : $item;
}, $individual_items );

$result_8_yes = TQB_Pricing_Engine::calculate_individual( $items_8, $answers_8_yes );
tqb_assert( 'qty=150 > threshold=100, should trigger custom quote', $result_8_yes['is_custom_quote'], true );

$result_8_no = TQB_Pricing_Engine::calculate_individual( $items_8, $answers_8_no );
tqb_assert( 'qty=50 < threshold=100, should NOT trigger', $result_8_no['is_custom_quote'], false );

// ---------------------------------------------------------------------
// TEST 9: Thresholds — Dollar value condition
// Item with threshold_rules: dollar_value > $100K should trigger
// (Task 2 Part 2 — new dollar_value support)
// ---------------------------------------------------------------------
echo "\nTEST 9: Threshold — Dollar value condition (above \$100K)\n";

$item_with_dollar_threshold = array(
	'item_key' => 'crypto',
	'fee' => 250,
	'pricing_pattern' => 'qty_times_fee',
	'hardcoded_value' => null,
	'is_custom_quote_trigger' => 0,
	'threshold_rules' => json_encode( array(
		'logic'      => 'AND',
		'conditions' => array(
			array( 'type' => 'dollar_value', 'operator' => 'above', 'value' => 100000 ),
		),
	) ),
);

$answers_9_yes = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 1, 'dollar_value' => 150000 ),
);

$answers_9_no = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 1, 'dollar_value' => 50000 ),
);

$items_9 = array_map( function ( $item ) {
	return ( 'crypto' === $item['item_key'] ) ? $item_with_dollar_threshold : $item;
}, $individual_items );

$result_9_yes = TQB_Pricing_Engine::calculate_individual( $items_9, $answers_9_yes );
tqb_assert( 'dollar_value=$150K > threshold=$100K, should trigger', $result_9_yes['is_custom_quote'], true );

$result_9_no = TQB_Pricing_Engine::calculate_individual( $items_9, $answers_9_no );
tqb_assert( 'dollar_value=$50K < threshold=$100K, should NOT trigger', $result_9_no['is_custom_quote'], false );

// ---------------------------------------------------------------------
// TEST 10: Thresholds — OR logic
// Conditions: (qty > 100) OR (dollar_value > $100K)
// If EITHER is true, should trigger custom quote
// ---------------------------------------------------------------------
echo "\nTEST 10: Threshold — OR logic (qty OR dollar_value)\n";

$item_with_or_threshold = array(
	'item_key' => 'crypto',
	'fee' => 250,
	'pricing_pattern' => 'qty_times_fee',
	'hardcoded_value' => null,
	'is_custom_quote_trigger' => 0,
	'threshold_rules' => json_encode( array(
		'logic'      => 'OR',
		'conditions' => array(
			array( 'type' => 'qty', 'operator' => 'above', 'value' => 100 ),
			array( 'type' => 'dollar_value', 'operator' => 'above', 'value' => 100000 ),
		),
	) ),
);

// qty=50 (false) OR dollar_value=$150K (true) = should trigger
$answers_10_qty_false_dollar_true = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 50, 'dollar_value' => 150000 ),
);

// qty=150 (true) OR dollar_value=$50K (false) = should trigger
$answers_10_qty_true_dollar_false = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 150, 'dollar_value' => 50000 ),
);

// qty=150 (true) OR dollar_value=$150K (true) = should trigger
$answers_10_both_true = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 150, 'dollar_value' => 150000 ),
);

// qty=50 (false) OR dollar_value=$50K (false) = should NOT trigger
$answers_10_both_false = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 50, 'dollar_value' => 50000 ),
);

$items_10 = array_map( function ( $item ) {
	return ( 'crypto' === $item['item_key'] ) ? $item_with_or_threshold : $item;
}, $individual_items );

$result_10_qty_false = TQB_Pricing_Engine::calculate_individual( $items_10, $answers_10_qty_false_dollar_true );
tqb_assert( 'OR: qty=50 (false) OR dollar_value=$150K (true) = should trigger', $result_10_qty_false['is_custom_quote'], true );

$result_10_dollar_false = TQB_Pricing_Engine::calculate_individual( $items_10, $answers_10_qty_true_dollar_false );
tqb_assert( 'OR: qty=150 (true) OR dollar_value=$50K (false) = should trigger', $result_10_dollar_false['is_custom_quote'], true );

$result_10_both_true = TQB_Pricing_Engine::calculate_individual( $items_10, $answers_10_both_true );
tqb_assert( 'OR: both true = should trigger', $result_10_both_true['is_custom_quote'], true );

$result_10_both_false = TQB_Pricing_Engine::calculate_individual( $items_10, $answers_10_both_false );
tqb_assert( 'OR: both false = should NOT trigger', $result_10_both_false['is_custom_quote'], false );

// ---------------------------------------------------------------------
// TEST 11: Thresholds — AND logic
// Conditions: (qty > 100) AND (dollar_value > $100K)
// BOTH must be true to trigger custom quote
// ---------------------------------------------------------------------
echo "\nTEST 11: Threshold — AND logic (qty AND dollar_value)\n";

$item_with_and_threshold = array(
	'item_key' => 'crypto',
	'fee' => 250,
	'pricing_pattern' => 'qty_times_fee',
	'hardcoded_value' => null,
	'is_custom_quote_trigger' => 0,
	'threshold_rules' => json_encode( array(
		'logic'      => 'AND',
		'conditions' => array(
			array( 'type' => 'qty', 'operator' => 'above', 'value' => 100 ),
			array( 'type' => 'dollar_value', 'operator' => 'above', 'value' => 100000 ),
		),
	) ),
);

// qty=150 (true) AND dollar_value=$150K (true) = should trigger
$answers_11_both_true = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 150, 'dollar_value' => 150000 ),
);

// qty=50 (false) AND dollar_value=$150K (true) = should NOT trigger
$answers_11_qty_false = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 50, 'dollar_value' => 150000 ),
);

// qty=150 (true) AND dollar_value=$50K (false) = should NOT trigger
$answers_11_dollar_false = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 150, 'dollar_value' => 50000 ),
);

$items_11 = array_map( function ( $item ) {
	return ( 'crypto' === $item['item_key'] ) ? $item_with_and_threshold : $item;
}, $individual_items );

$result_11_both_true = TQB_Pricing_Engine::calculate_individual( $items_11, $answers_11_both_true );
tqb_assert( 'AND: both true = should trigger', $result_11_both_true['is_custom_quote'], true );

$result_11_qty_false = TQB_Pricing_Engine::calculate_individual( $items_11, $answers_11_qty_false );
tqb_assert( 'AND: qty=50 (false) AND dollar_value=$150K (true) = should NOT trigger', $result_11_qty_false['is_custom_quote'], false );

$result_11_dollar_false = TQB_Pricing_Engine::calculate_individual( $items_11, $answers_11_dollar_false );
tqb_assert( 'AND: qty=150 (true) AND dollar_value=$50K (false) = should NOT trigger', $result_11_dollar_false['is_custom_quote'], false );

// ---------------------------------------------------------------------
// TEST 12: Thresholds — Backward compatibility with legacy format
// Old threshold_qty + threshold_trigger should still work
// ---------------------------------------------------------------------
echo "\nTEST 12: Threshold — Backward compatibility (legacy format)\n";

$item_with_legacy_threshold = array(
	'item_key' => 'crypto',
	'fee' => 250,
	'pricing_pattern' => 'qty_times_fee',
	'hardcoded_value' => null,
	'is_custom_quote_trigger' => 0,
	'threshold_qty' => 100,
	'threshold_trigger' => 'above',
	'threshold_rules' => null,
);

$answers_12_yes = array(
	'w2_wages' => array( 'selected' => true, 'qty' => 1 ),
	'crypto'   => array( 'selected' => true, 'qty' => 150 ),
);

$items_12 = array_map( function ( $item ) {
	return ( 'crypto' === $item['item_key'] ) ? $item_with_legacy_threshold : $item;
}, $individual_items );

$result_12 = TQB_Pricing_Engine::calculate_individual( $items_12, $answers_12_yes );
tqb_assert( 'Legacy format: qty=150 > threshold=100 (old format) should trigger', $result_12['is_custom_quote'], true );

// ---------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------
echo "\n" . str_repeat( '-', 50 ) . "\n";
echo "TOTAL: {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
