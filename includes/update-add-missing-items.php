<?php
/**
 * Run this once to add missing individual line items to the database.
 * Access via: WordPress admin > Plugins > Editor > Select TQB > update-add-missing-items.php
 * Or run via WP-CLI: wp eval-file includes/update-add-missing-items.php
 * Safe to run multiple times - skips existing items.
 */
if (!defined('ABSPATH')) {
    die('Direct access not permitted');
}

global $wpdb;
$table = $wpdb->prefix . 'tqb_line_items';

// Missing items to add (in correct sort order)
$items_to_add = array(
    // sort_order 10 - after w2_wages
    array('multi_state', 'Lived or worked in more than one state', 150, 'qty_times_fee', null, 0, null, null, 1, 10, 'If you earned income or worked in a state other than your primary residence, additional state filings may be required.'),
    
    // sort_order 15 - interest/dividends
    array('interest_dividends', 'Bank or investment account interest/dividend statements (1099-INT/1099-DIV)', 25, 'flat', null, 0, null, null, 1, 15, 'Look for 1099-INT (interest) and 1099-DIV (dividends) forms from your banks and investment accounts.'),
    
    // sort_order 20 - brokerage
    array('brokerage_sales', 'Brokerage statement showing stock or investment sales (1099-B)', 25, 'qty_times_fee', null, 0, null, null, 1, 20, 'If you sold stocks, bonds, or other investments, you should receive a 1099-B form from your brokerage.'),
    
    // sort_order 70 - K-1
    array('k1_received', 'Received a K-1 (from a partnership, S-corp, trust, or estate)', 50, 'qty_times_fee', null, 0, null, null, 1, 70, 'A K-1 form reports income from partnerships, S-corporations, or estates/trusts. Per K-1.'),
    
    // sort_order 110 - childcare
    array('childcare', 'Paid for childcare or dependent care (per child)', 25, 'flat', null, 0, null, null, 1, 110, 'Child and dependent care expenses may qualify for a tax credit. You will need the provider\'s name and tax ID.'),
    
    // sort_order 120 - HSA
    array('hsa', 'Has an HSA - Health Savings Account (1099-SA/5498-SA)', 25, 'qty_times_fee', null, 0, null, null, 1, 120, 'Health Savings Account contributions and distributions are reported on Form 8889.'),
    
    // sort_order 130 - home sale
    array('home_sale', 'Sold any home during the year (1099-S)', 150, 'qty_times_fee', null, 0, null, null, 1, 130, 'If you sold a home, you should receive a 1099-S form. There may be capital gains implications.'),
);

$added = 0;
foreach ($items_to_add as $item) {
    // Check if already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE item_key = %s AND quote_type = 'individual'",
        $item[0]
    ));
    
    if ($exists == 0) {
        $result = $wpdb->insert($table, array(
            'quote_type' => 'individual',
            'item_key' => $item[0],
            'label' => $item[1],
            'fee' => $item[2],
            'pricing_pattern' => $item[3],
            'hardcoded_value' => $item[4],
            'is_custom_quote_trigger' => $item[5],
            'threshold_qty' => $item[6],
            'threshold_trigger' => $item[7],
            'is_active' => $item[8],
            'sort_order' => $item[9],
            'tooltip' => $item[10],
        ));
        
        if ($result !== false) {
            $added++;
            echo "Added: {$item[0]}\n";
        }
    } else {
        echo "Already exists: {$item[0]}\n";
    }
}

echo "\nDone! Added {$added} new items.\n";
