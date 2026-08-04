-- Tavola Quote Builder: Database Migration for Full Feature Rebuild
-- This script adds missing columns and populates tooltips from client feedback

-- 1. Add business_name column to submissions table
ALTER TABLE `wp_tqb_submissions` 
ADD COLUMN `business_name` varchar(255) DEFAULT NULL AFTER `contact_phone`;

-- 1b. Add filing_status column to line_items table for question filtering by filing status
ALTER TABLE `wp_tqb_line_items` 
ADD COLUMN `filing_status` VARCHAR(50) DEFAULT NULL;

-- 2. Populate tooltips/help text for Personal questions (Individual return type)
UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'This includes wages, salaries, bonuses, commissions, and other employment income reported on a W-2.'
WHERE `item_key` = 'w2_wages';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'This helps determine whether multiple state tax returns may be required.'
WHERE `item_key` = 'multi_state' AND `quote_type` = 'individual';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Look for Forms 1099-INT or 1099-DIV from your bank or brokerage.'
WHERE `item_key` = 'interest_dividends';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'You can usually find this information on Form 1099-B or your year-end brokerage statement.'
WHERE `item_key` = 'brokerage_sales';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include long-term, short-term, or vacation rentals.'
WHERE `item_key` = 'rental_property';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include freelance work, consulting, side businesses, or gig economy income (1099 income).'
WHERE `item_key` = 'self_employed';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include income and expenses from farming or agricultural operations.'
WHERE `item_key` = 'farm_income';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'K-1s are commonly issued by partnerships, S corporations, estates, or trusts.'
WHERE `item_key` = 'k1_received';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'This helps determine whether FBAR or other international reporting requirements apply.'
WHERE `item_key` = 'foreign_accounts';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include Bitcoin, Ethereum, NFTs, or any other digital assets.'
WHERE `item_key` = 'crypto';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Look for Form 1098-T from the educational institution.'
WHERE `item_key` = 'tuition';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include daycare, preschool, before/after-school care, or summer day camps.'
WHERE `item_key` = 'childcare';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Look for Forms 1099-SA or 5498-SA.'
WHERE `item_key` = 'hsa';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Look for Form 1099-S or include the sale of your primary residence or investment property.'
WHERE `item_key` = 'home_sale';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include withdrawals from a 401(k), IRA, Roth IRA, pension, annuity, or similar retirement account.'
WHERE `item_key` = 'retirement_distributions';

-- 3. Populate tooltips for Business questions
UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include any business where ownership is shared with another individual or entity. This helps us determine whether additional Schedule K-1s will need to be prepared.'
WHERE `item_key` = 'extra_k1s';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'This includes having employees, offices, property, or business activity in multiple states that may require additional state tax filings.'
WHERE `item_key` = 'multi_state' AND `quote_type` = 'business';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Select "Yes" if your business has purchased equipment, furniture, vehicles, buildings, or other assets that need to be tracked and depreciated.'
WHERE `item_key` = 'depreciation_schedule';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'This includes individuals or entities that are not U.S. persons and may require additional tax reporting.'
WHERE `item_key` = 'foreign_partner';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'For example, if your QuickBooks balance doesn\'t match your last filed tax return or if prior accountant adjustments haven\'t been recorded.'
WHERE `item_key` = 'books_dont_match';

UPDATE `wp_tqb_line_items` SET 
`tooltip` = 'Include machinery, vehicles, computers, furniture, buildings, and other depreciable business assets. This helps us estimate the complexity of maintaining your depreciation schedule.'
WHERE `item_key` = 'excess_equipment';

-- 4. Set reveal_followup for questions that have conditional followups
UPDATE `wp_tqb_line_items` SET 
`reveal_followup` = 1
WHERE `item_key` IN ('multi_state', 'brokerage_sales', 'crypto', 'depreciation_schedule');

-- 5. Add threshold rules for crypto (if >100 transactions, route to custom quote)
UPDATE `wp_tqb_line_items` SET 
`threshold_rules` = JSON_OBJECT('logic', 'OR', 'conditions', JSON_ARRAY(
  JSON_OBJECT('type', 'qty', 'operator', 'above', 'value', 100),
  JSON_OBJECT('type', 'value', 'operator', 'above', 'value', 100000)
))
WHERE `item_key` = 'crypto';

-- 6. Mark crypto as custom quote trigger
UPDATE `wp_tqb_line_items` SET 
`is_custom_quote_trigger` = 1
WHERE `item_key` = 'crypto';

-- 7. Ensure is_active flags are correct (some items were disabled: 0)
UPDATE `wp_tqb_line_items` SET 
`is_active` = 1
WHERE `quote_type` IN ('individual', 'business') AND `is_active` = 0;

COMMIT;
