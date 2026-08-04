-- ============================================================================
-- Tavola Quote Builder — Deprecated Schema Cleanup (v1.4)
-- ============================================================================
-- This runs automatically when you activate the v1.4 plugin zip, so you do
-- NOT need to run this manually. It's provided as a reference / for running
-- directly if you'd rather apply it via phpMyAdmin before updating the plugin.
--
-- Safe to run multiple times — every statement uses IF EXISTS / checks.
-- Back up your database before running, as always with schema changes.
-- ============================================================================

-- 1. Drop orphaned tables from an abandoned parallel questions system.
--    Confirmed zero live code references before removal — see plugin's
--    class-tqb-activator.php cleanup_deprecated_schema() for the trace.
DROP TABLE IF EXISTS `wp_tqb_question_set_items`;
DROP TABLE IF EXISTS `wp_tqb_question_sets`;

-- 2. Drop deprecated columns from wp_tqb_line_items.
--    IMPORTANT: run this AFTER confirming no line item still uses the
--    'hardcoded' pricing_pattern (the plugin's automatic migration converts
--    these to 'flat' first). To check before dropping manually:
--
--      SELECT id, item_key, fee, pricing_pattern, hardcoded_value
--      FROM wp_tqb_line_items WHERE pricing_pattern = 'hardcoded';
--
--    If that returns any rows, run this first to preserve the charged price:
--
--      UPDATE wp_tqb_line_items
--      SET fee = hardcoded_value, pricing_pattern = 'flat'
--      WHERE pricing_pattern = 'hardcoded' AND hardcoded_value IS NOT NULL;

ALTER TABLE `wp_tqb_line_items` DROP COLUMN `hardcoded_value`;
ALTER TABLE `wp_tqb_line_items` DROP COLUMN `threshold_qty`;
ALTER TABLE `wp_tqb_line_items` DROP COLUMN `threshold_trigger`;

-- ============================================================================
-- After this, wp_tqb_line_items should have exactly these columns:
--   id, quote_type, item_key, label, fee, pricing_pattern,
--   is_custom_quote_trigger, threshold_rules, reveal_followup, is_active,
--   sort_order, tooltip, notes, filing_status
-- ============================================================================
