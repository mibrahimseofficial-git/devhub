-- Task 4 Filing Status Tables
-- Run this in phpMyAdmin if plugin activation doesn't auto-create the tables
-- Replace 'wp_' with your table prefix if different

CREATE TABLE IF NOT EXISTS `wp_tqb_question_sets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g. Individual, Individual_MFJ, Business',
  `return_type` VARCHAR(20) NOT NULL COMMENT 'individual or business',
  `filing_status` VARCHAR(50) NULL COMMENT 'NULL for base; single/mfj/mfs/hoh for variants',
  `parent_set_id` BIGINT UNSIGNED NULL COMMENT 'FK to base set for inheritance',
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_type_filing` (`return_type`, `filing_status`),
  KEY `return_type` (`return_type`),
  KEY `filing_status` (`filing_status`),
  KEY `parent_set_id` (`parent_set_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_tqb_question_set_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_set_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to question_sets',
  `line_item_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to line_items',
  `sort_order` INT NOT NULL DEFAULT 0,
  `override_label` VARCHAR(255) NULL COMMENT 'Filing-status-specific wording; NULL = use base',
  `override_followup_label` VARCHAR(255) NULL COMMENT 'Custom quantity field label',
  `override_fee` DECIMAL(10,2) NULL COMMENT 'Filing-status-specific pricing; NULL = use base',
  `override_reveal_followup` TINYINT(1) NULL COMMENT '1/0; NULL = use base',
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = hide this question for this filing status',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `set_item` (`question_set_id`, `line_item_id`),
  KEY `question_set_id` (`question_set_id`),
  KEY `line_item_id` (`line_item_id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add filing_status column to submissions if it doesn't exist
ALTER TABLE `wp_tqb_submissions` ADD COLUMN `filing_status` VARCHAR(50) NULL COMMENT 'single, mfj, mfs, hoh for individual; NULL for business' AFTER `quote_type`;

-- Seed question sets: Base Individual + 4 filing status variants + Business
INSERT IGNORE INTO `wp_tqb_question_sets` 
(`name`, `return_type`, `filing_status`, `parent_set_id`, `description`, `is_active`) 
VALUES 
('Individual', 'individual', NULL, NULL, 'Base Individual return set (inherited by all filing statuses)', 1),
('Individual_single', 'individual', 'single', 1, 'Single filer (inherits from Individual base)', 1),
('Individual_mfj', 'individual', 'mfj', 1, 'Married Filing Jointly (inherits from Individual base)', 1),
('Individual_mfs', 'individual', 'mfs', 1, 'Married Filing Separately (inherits from Individual base)', 1),
('Individual_hoh', 'individual', 'hoh', 1, 'Head of Household (inherits from Individual base)', 1),
('Business', 'business', NULL, NULL, 'Business return set (no filing status variations)', 1);

-- Populate base Individual set with all individual line items
INSERT IGNORE INTO `wp_tqb_question_set_items` 
(`question_set_id`, `line_item_id`, `sort_order`)
SELECT 
  (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual' LIMIT 1) as question_set_id,
  `id` as line_item_id,
  `sort_order`
FROM `wp_tqb_line_items`
WHERE `quote_type` = 'individual'
ORDER BY `sort_order` ASC;

-- Populate Business set with all business line items
INSERT IGNORE INTO `wp_tqb_question_set_items` 
(`question_set_id`, `line_item_id`, `sort_order`)
SELECT 
  (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Business' LIMIT 1) as question_set_id,
  `id` as line_item_id,
  `sort_order`
FROM `wp_tqb_line_items`
WHERE `quote_type` = 'business'
ORDER BY `sort_order` ASC;

-- Populate MFJ set with inherited items
INSERT IGNORE INTO `wp_tqb_question_set_items` 
(`question_set_id`, `line_item_id`, `sort_order`)
SELECT 
  (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_mfj' LIMIT 1) as question_set_id,
  `id` as line_item_id,
  `sort_order`
FROM `wp_tqb_line_items`
WHERE `quote_type` = 'individual'
ORDER BY `sort_order` ASC;

-- Populate Single set with inherited items
INSERT IGNORE INTO `wp_tqb_question_set_items` 
(`question_set_id`, `line_item_id`, `sort_order`)
SELECT 
  (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_single' LIMIT 1) as question_set_id,
  `id` as line_item_id,
  `sort_order`
FROM `wp_tqb_line_items`
WHERE `quote_type` = 'individual'
ORDER BY `sort_order` ASC;

-- Populate MFS set with inherited items
INSERT IGNORE INTO `wp_tqb_question_set_items` 
(`question_set_id`, `line_item_id`, `sort_order`)
SELECT 
  (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_mfs' LIMIT 1) as question_set_id,
  `id` as line_item_id,
  `sort_order`
FROM `wp_tqb_line_items`
WHERE `quote_type` = 'individual'
ORDER BY `sort_order` ASC;

-- Populate HOH set with inherited items
INSERT IGNORE INTO `wp_tqb_question_set_items` 
(`question_set_id`, `line_item_id`, `sort_order`)
SELECT 
  (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_hoh' LIMIT 1) as question_set_id,
  `id` as line_item_id,
  `sort_order`
FROM `wp_tqb_line_items`
WHERE `quote_type` = 'individual'
ORDER BY `sort_order` ASC;

-- Add MFJ wording overrides (example: change "anyone" to "you or your spouse")
UPDATE `wp_tqb_question_set_items` 
SET `override_label` = 'Did you or your spouse receive W-2 income from an employer?'
WHERE `question_set_id` = (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_mfj' LIMIT 1)
AND `line_item_id` = (SELECT id FROM `wp_tqb_line_items` WHERE item_key = 'w2_wages' LIMIT 1);

UPDATE `wp_tqb_question_set_items` 
SET `override_label` = 'Did you or your spouse live or work in more than one state during the year?'
WHERE `question_set_id` = (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_mfj' LIMIT 1)
AND `line_item_id` = (SELECT id FROM `wp_tqb_line_items` WHERE item_key = 'multi_state' LIMIT 1);

UPDATE `wp_tqb_question_set_items` 
SET `override_label` = 'Did you or your spouse earn interest or dividends from a bank or investment account?'
WHERE `question_set_id` = (SELECT id FROM `wp_tqb_question_sets` WHERE name = 'Individual_mfj' LIMIT 1)
AND `line_item_id` = (SELECT id FROM `wp_tqb_line_items` WHERE item_key = 'interest_dividends' LIMIT 1);

-- Verify
SELECT 'Question Sets:' as 'Info';
SELECT * FROM `wp_tqb_question_sets`;
SELECT COUNT(*) as 'Total Question Set Items:' FROM `wp_tqb_question_set_items`;
