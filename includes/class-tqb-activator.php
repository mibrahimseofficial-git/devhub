<?php
/**
 * Fired during plugin activation.
 * Creates the custom database tables this plugin needs, instead of relying
 * on wp_posts. See PROJECT_SPEC.md Section 2 (Architecture) for rationale.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Activator {

	/**
	 * Runs on plugin activation. Creates/updates all custom tables using
	 * dbDelta(), which is safe to run repeatedly (it diffs against existing
	 * schema rather than dropping/recreating).
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Create all tables using dbDelta
		self::create_submissions_table( $wpdb, $charset_collate );
		self::create_line_items_table( $wpdb, $charset_collate );
		self::create_rate_bands_table( $wpdb, $charset_collate );
		self::create_question_sets_table( $wpdb, $charset_collate );
		self::create_question_set_items_table( $wpdb, $charset_collate );

		// Verify all critical tables were created; if not, create them with direct SQL
		$line_items_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;
		$question_sets_table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;
		$question_set_items_table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$line_items_table}'" ) !== $line_items_table ) {
			error_log( 'TQB: line_items table not created, using SQL fallback' );
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$line_items_table}` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`quote_type` VARCHAR(20) NOT NULL,
				`item_key` VARCHAR(100) NOT NULL,
				`label` VARCHAR(255) NOT NULL,
				`fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
				`pricing_pattern` VARCHAR(20) NOT NULL DEFAULT 'qty_times_fee',
				`hardcoded_value` DECIMAL(10,2) NULL,
				`is_custom_quote_trigger` TINYINT(1) NOT NULL DEFAULT 0,
				`threshold_rules` LONGTEXT NULL,
				`reveal_followup` TINYINT(1) NOT NULL DEFAULT 1,
				`is_active` TINYINT(1) NOT NULL DEFAULT 1,
				`sort_order` INT NOT NULL DEFAULT 0,
				`tooltip` TEXT NULL,
				`notes` TEXT NULL,
				PRIMARY KEY  (`id`),
				UNIQUE KEY `item_key_type` (`item_key`, `quote_type`),
				KEY `quote_type` (`quote_type`),
				KEY `sort_order` (`sort_order`)
			) {$charset_collate}" );
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$question_sets_table}'" ) !== $question_sets_table ) {
			error_log( 'TQB: question_sets table not created, using SQL fallback' );
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$question_sets_table}` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(100) NOT NULL UNIQUE,
				`return_type` VARCHAR(20) NOT NULL,
				`filing_status` VARCHAR(50) NULL,
				`parent_set_id` BIGINT UNSIGNED NULL,
				`description` TEXT NULL,
				`is_active` TINYINT(1) NOT NULL DEFAULT 1,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (`id`),
				UNIQUE KEY `return_type_filing` (`return_type`, `filing_status`),
				KEY `return_type` (`return_type`),
				KEY `filing_status` (`filing_status`),
				KEY `parent_set_id` (`parent_set_id`)
			) {$charset_collate}" );
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$question_set_items_table}'" ) !== $question_set_items_table ) {
			error_log( 'TQB: question_set_items table not created, using SQL fallback' );
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$question_set_items_table}` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`question_set_id` BIGINT UNSIGNED NOT NULL,
				`line_item_id` BIGINT UNSIGNED NOT NULL,
				`sort_order` INT NOT NULL DEFAULT 0,
				`override_label` VARCHAR(255) NULL,
				`override_followup_label` VARCHAR(255) NULL,
				`override_fee` DECIMAL(10,2) NULL,
				`override_reveal_followup` TINYINT(1) NULL,
				`is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (`id`),
				UNIQUE KEY `set_item` (`question_set_id`, `line_item_id`),
				KEY `question_set_id` (`question_set_id`),
				KEY `line_item_id` (`line_item_id`),
				KEY `sort_order` (`sort_order`)
			) {$charset_collate}" );
		}

		// Now seed the data
		self::seed_default_data();
		self::seed_default_question_sets();
		self::seed_default_settings();

		// Recovery: If question sets table exists but is empty (partial failure on previous activation),
		// force re-seed to ensure data integrity on new sites
		$sets_table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;
		$existing_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$sets_table}" );
		if ( empty( $existing_count ) ) {
			self::seed_default_question_sets();
		}

		// Schedule cron jobs for HubSpot retry (hourly)
		if ( ! wp_next_scheduled( 'tqb_retry_hubspot_syncs' ) ) {
			wp_schedule_event( time(), 'tqb_hourly', 'tqb_retry_hubspot_syncs' );
		}

		// Schedule daily admin notification for HubSpot failures
		if ( ! wp_next_scheduled( 'tqb_notify_hubspot_failures' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'tqb_notify_hubspot_failures' );
		}

		update_option( 'tqb_db_version', TQB_VERSION );
	}

	/**
	 * Runs on plugin upgrade to fix existing data.
	 */
	public static function upgrade() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		// Safety: Ensure all tables exist first (in case of partial installation)
		self::create_submissions_table( $wpdb, $charset_collate );
		self::create_line_items_table( $wpdb, $charset_collate );
		self::create_rate_bands_table( $wpdb, $charset_collate );
		self::create_question_sets_table( $wpdb, $charset_collate );
		self::create_question_set_items_table( $wpdb, $charset_collate );

		// Safety check: ensure tables exist before trying to upgrade
		$submissions_table = $wpdb->prefix . 'tqb_submissions';
		$line_items_table = $wpdb->prefix . 'tqb_line_items';
		$rate_bands_table = $wpdb->prefix . 'tqb_rate_bands';

		// Check if submissions table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$submissions_table}'" ) !== $submissions_table ) {
			return; // Tables don't exist yet - activate() will create them
		}

		// Check if line_items table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$line_items_table}'" ) !== $line_items_table ) {
			return; // Tables don't exist yet
		}

		// --- Remove duplicate line items (0.7.4 patch) ---
		self::cleanup_duplicate_line_items( $line_items_table );

		// --- Remove duplicate rate bands (0.7.4 patch) ---
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rate_bands_table}'" ) === $rate_bands_table ) {
			self::cleanup_duplicate_rate_bands( $rate_bands_table );
		}

		// --- Ensure UNIQUE constraints exist and are enforced ---
		self::ensure_unique_constraints( $line_items_table, $rate_bands_table );

		// --- Line items table: add new threshold and reveal columns (0.7.5 patch) ---
		self::add_threshold_rules_column( $line_items_table );
		self::add_reveal_followup_column( $line_items_table );
		self::backfill_threshold_rules( $line_items_table );

		// --- Line items table: add legacy threshold columns (for backward compat) ---
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$line_items_table}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );

		if ( ! in_array( 'threshold_qty', $column_names, true ) ) {
			$after = in_array( 'is_custom_quote_trigger', $column_names, true ) ? 'is_custom_quote_trigger' : null;
			$sql = "ALTER TABLE {$line_items_table} ADD COLUMN threshold_qty DECIMAL(14,2) NULL";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
		}

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$line_items_table}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );

		if ( ! in_array( 'threshold_trigger', $column_names, true ) ) {
			$after = in_array( 'threshold_qty', $column_names, true ) ? 'threshold_qty' : null;
			$sql = "ALTER TABLE {$line_items_table} ADD COLUMN threshold_trigger VARCHAR(10) NULL";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
		}

		// Refresh column list after potential additions
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$line_items_table}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );

		// Update crypto item: use qty_times_fee with threshold
		$update_data = array(
			'pricing_pattern'    => 'qty_times_fee',
			'is_custom_quote_trigger' => 0,
		);
		$update_format = array( '%s', '%d' );
		if ( in_array( 'threshold_qty', $column_names, true ) ) {
			$update_data['threshold_qty'] = 100;
			$update_format[] = '%s';
		}
		if ( in_array( 'threshold_trigger', $column_names, true ) ) {
			$update_data['threshold_trigger'] = 'above';
			$update_format[] = '%s';
		}

		$wpdb->update(
			$line_items_table,
			$update_data,
			array( 'item_key' => 'crypto' ),
			$update_format,
			array( '%s' )
		);

		// Update tuition item: change to flat pricing
		$wpdb->update(
			$line_items_table,
			array( 'pricing_pattern' => 'flat' ),
			array( 'item_key' => 'tuition' ),
			array( '%s' ),
			array( '%s' )
		);

		// --- Submissions table: add filing_status column (Task 4) ---
		$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
		if ( ! is_array( $sub_columns ) ) {
			$sub_columns = array();
		}
		$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );

		if ( ! in_array( 'filing_status', $sub_column_names, true ) ) {
			$after = in_array( 'quote_type', $sub_column_names, true ) ? 'quote_type' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN filing_status VARCHAR(50) NULL COMMENT 'single, mfj, mfs, hoh for individual; NULL for business'";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
		}

		// Re-fetch columns after adding filing_status
		$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
		$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );

		// --- Submissions table: add abandoned quote columns ---

		// Add status column
		if ( ! in_array( 'status', $sub_column_names, true ) ) {
			$after = in_array( 'custom_quote_reason', $sub_column_names, true ) ? 'custom_quote_reason' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'in_progress'";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add last_completed_step column
		if ( ! in_array( 'last_completed_step', $sub_column_names, true ) ) {
			$after = in_array( 'status', $sub_column_names, true ) ? 'status' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN last_completed_step INT NOT NULL DEFAULT 0";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add reminder_email_sent column
		if ( ! in_array( 'reminder_email_sent', $sub_column_names, true ) ) {
			$after = in_array( 'confirmation_email_sent', $sub_column_names, true ) ? 'confirmation_email_sent' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN reminder_email_sent TINYINT(1) NOT NULL DEFAULT 0";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add reminder_email_sent_at column
		if ( ! in_array( 'reminder_email_sent_at', $sub_column_names, true ) ) {
			$after = in_array( 'reminder_email_sent', $sub_column_names, true ) ? 'reminder_email_sent' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN reminder_email_sent_at DATETIME NULL";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add followup_email_sent column
		if ( ! in_array( 'followup_email_sent', $sub_column_names, true ) ) {
			$after = in_array( 'reminder_email_sent_at', $sub_column_names, true ) ? 'reminder_email_sent_at' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN followup_email_sent TINYINT(1) NOT NULL DEFAULT 0";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add followup_email_sent_at column
		if ( ! in_array( 'followup_email_sent_at', $sub_column_names, true ) ) {
			$after = in_array( 'followup_email_sent', $sub_column_names, true ) ? 'followup_email_sent' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN followup_email_sent_at DATETIME NULL";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add final_email_sent column
		if ( ! in_array( 'final_email_sent', $sub_column_names, true ) ) {
			$after = in_array( 'followup_email_sent_at', $sub_column_names, true ) ? 'followup_email_sent_at' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN final_email_sent TINYINT(1) NOT NULL DEFAULT 0";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add final_email_sent_at column
		if ( ! in_array( 'final_email_sent_at', $sub_column_names, true ) ) {
			$after = in_array( 'final_email_sent', $sub_column_names, true ) ? 'final_email_sent' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN final_email_sent_at DATETIME NULL";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add updated_at column
		if ( ! in_array( 'updated_at', $sub_column_names, true ) ) {
			$after = in_array( 'created_at', $sub_column_names, true ) ? 'created_at' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
		}

		// Add user_ip column
		if ( ! in_array( 'user_ip', $sub_column_names, true ) ) {
			$after = in_array( 'updated_at', $sub_column_names, true ) ? 'updated_at' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN user_ip VARCHAR(45) NULL";
			if ( $after ) { $sql .= " AFTER {$after}"; }
			$wpdb->query( $sql );
		}

			// Add hubspot_sync_failed column for retry tracking
			$sub_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$submissions_table}", ARRAY_A );
			$sub_column_names = wp_list_pluck( $sub_columns, 'Field' );
			if ( ! in_array( 'hubspot_sync_failed', $sub_column_names, true ) ) {
				$after = in_array( 'hubspot_deal_id', $sub_column_names, true ) ? 'hubspot_deal_id' : null;
				$sql = "ALTER TABLE {$submissions_table} ADD COLUMN hubspot_sync_failed TINYINT(1) NOT NULL DEFAULT 0";
				if ( $after ) { $sql .= " AFTER {$after}"; }
				$wpdb->query( $sql );
			}

		// --- Add abandoned quote email settings ---
		if ( false === get_option( 'tqb_enable_abandoned_emails', false ) ) {
			add_option( 'tqb_enable_abandoned_emails', '1' );
		}
		if ( false === get_option( 'tqb_reminder_email_hours', false ) ) {
			add_option( 'tqb_reminder_email_hours', '24' );
		}
		if ( false === get_option( 'tqb_followup_email_hours', false ) ) {
			add_option( 'tqb_followup_email_hours', '72' );
		}
		if ( false === get_option( 'tqb_final_email_hours', false ) ) {
			add_option( 'tqb_final_email_hours', '168' );
		}
		if ( false === get_option( 'tqb_office_address', false ) ) {
			add_option( 'tqb_office_address', "939 W North Ave, Suite 750,\nChicago, IL 60642" );
		}

		update_option( 'tqb_db_version', TQB_VERSION );
	}

	/**
	 * Default values for plugin-wide settings, editable later from the admin
	 * dashboard's General Settings tab. Uses add_option (not update_option)
	 * so re-activating never overwrites a value James has already changed.
	 */
	private static function seed_default_settings() {
		add_option( 'tqb_disclaimer_text', 'This quote is an estimate and is subject to change based on your specific facts and circumstances. For example, if the number of properties, accounts, or services involved is different from what was entered, or if additional work is needed once we review your documents, the final price may be adjusted.' );
		add_option( 'tqb_scheduling_link', 'https://scheduler.zoom.us/matt-schumacher/personal-tax-return-consultation' );
		add_option( 'tqb_team_notification_email', get_option( 'admin_email' ) );
		add_option( 'tqb_hubspot_service_key', '' ); // Pasted in by the client/developer via General Settings — never hardcoded.
		add_option( 'tqb_hubspot_pipeline_id', '' ); // Optional — leave blank to use the HubSpot account's default pipeline.
		add_option( 'tqb_hubspot_stage_new', '' ); // Stage for instant-quote submissions.
		add_option( 'tqb_hubspot_stage_custom', '' ); // Stage for custom-quote-required submissions.
		add_option( 'tqb_hubspot_deal_stage_id', '' ); // Legacy single-stage field, kept as a fallback for backward compatibility.

		// Abandoned quote email settings
		add_option( 'tqb_enable_abandoned_emails', '1' ); // 1 = enabled, 0 = disabled
		add_option( 'tqb_reminder_email_hours', '24' ); // Hours after abandonment to send reminder
		add_option( 'tqb_followup_email_hours', '72' ); // Hours after abandonment to send follow-up with call offer
		add_option( 'tqb_final_email_hours', '168' ); // Hours after abandonment to send final email
		add_option( 'tqb_office_address', "939 W North Ave, Suite 750,\nChicago, IL 60642" );
	}

	/**
	 * Stores every form submission: contact info, raw answers (JSON),
	 * calculated result, and whether it triggered the custom-quote path.
	 * This is what powers email notifications, HubSpot sync, and reporting.
	 */
	private static function create_submissions_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . TQB_TABLE_SUBMISSIONS;

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_type VARCHAR(20) NOT NULL COMMENT 'individual or business',
			contact_name VARCHAR(255) NOT NULL,
			contact_email VARCHAR(255) NOT NULL,
			contact_phone VARCHAR(50) NOT NULL,
			answers LONGTEXT NOT NULL COMMENT 'JSON: raw question answers as submitted',
			calculated_total DECIMAL(10,2) NULL COMMENT 'NULL when is_custom_quote = 1',
			is_custom_quote TINYINT(1) NOT NULL DEFAULT 0,
			custom_quote_reason VARCHAR(255) NULL COMMENT 'e.g. crypto, foreign_accounts, assets_over_5m',
			status VARCHAR(20) NOT NULL DEFAULT 'completed' COMMENT 'completed, in_progress, abandoned',
			last_completed_step INT NOT NULL DEFAULT 0 COMMENT '1-5 for tracking partial submissions',
			user_ip VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address for tracking',
			hubspot_synced TINYINT(1) NOT NULL DEFAULT 0,
			hubspot_contact_id VARCHAR(100) NULL,
			hubspot_deal_id VARCHAR(100) NULL,
			confirmation_email_sent TINYINT(1) NOT NULL DEFAULT 0,
			reminder_email_sent TINYINT(1) NOT NULL DEFAULT 0,
			reminder_email_sent_at DATETIME NULL,
			followup_email_sent TINYINT(1) NOT NULL DEFAULT 0,
			followup_email_sent_at DATETIME NULL,
			final_email_sent TINYINT(1) NOT NULL DEFAULT 0,
			final_email_sent_at DATETIME NULL,
			team_notified TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY quote_type (quote_type),
			KEY status (status),
			KEY created_at (created_at),
			KEY is_custom_quote (is_custom_quote),
			KEY reminder_email_sent (reminder_email_sent),
			KEY followup_email_sent (followup_email_sent),
			KEY user_ip (user_ip)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * The editable "checklist" line items — covers both the Individual sheet
	 * in full, and Part B (extras) of the Business sheet. This is what the
	 * admin dashboard repeater UI will read from and write to (Phase 3).
	 *
	 * pricing_pattern values map directly to the 3 formula patterns found in
	 * the client's real Excel calculator (see PROJECT_SPEC.md Section 3):
	 *   'qty_times_fee' → IF(Yes, Qty*Fee, 0)
	 *   'flat'          → IF(Yes, Fee, 0)  — qty ignored
	 *   'hardcoded'     → IF(Yes, hardcoded_value, 0) — ignores fee column
	 */
	private static function create_line_items_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_type VARCHAR(20) NOT NULL COMMENT 'individual or business',
			item_key VARCHAR(100) NOT NULL COMMENT 'stable slug, e.g. rental_property',
			label VARCHAR(255) NOT NULL,
			fee DECIMAL(10,2) NOT NULL DEFAULT 0,
			pricing_pattern VARCHAR(20) NOT NULL DEFAULT 'qty_times_fee',
			hardcoded_value DECIMAL(10,2) NULL COMMENT 'used only when pricing_pattern = hardcoded',
			is_custom_quote_trigger TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = any Yes answer routes to custom-quote path instead of pricing (e.g. crypto, FBAR)',
			threshold_qty DECIMAL(14,2) NULL COMMENT 'DEPRECATED: use threshold_rules JSON instead',
			threshold_trigger VARCHAR(10) NULL COMMENT 'DEPRECATED: use threshold_rules JSON instead',
			threshold_rules LONGTEXT NULL COMMENT 'JSON: structured threshold logic with logic (AND/OR) and conditions array',
			reveal_followup TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = hide quantity/dollar field until checkbox checked; 0 = always show (legacy behavior)',
			is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = hidden from public form (e.g. audit, meetings)',
			sort_order INT NOT NULL DEFAULT 0,
			tooltip TEXT NULL COMMENT 'Customer-facing help text shown on hover',
			notes TEXT NULL COMMENT 'Internal notes for admin reference',
			PRIMARY KEY  (id),
			UNIQUE KEY item_key_type (item_key, quote_type),
			KEY quote_type (quote_type),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * The Business "Rate Reference" data backbone: Schedule L thresholds,
	 * asset-band price grid, and revenue add-ons. See PROJECT_SPEC.md
	 * Section 4 (Part A) and Section 5. This table does the same job the
	 * Rate Reference sheet does in the client's Excel calculator.
	 */
	private static function create_rate_bands_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . TQB_TABLE_RATE_BANDS;

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			band_type VARCHAR(30) NOT NULL COMMENT 'asset_band, revenue_addon, or schedule_l_threshold',
			entity_group VARCHAR(30) NULL COMMENT 'c_s_corp or partnership — NULL for revenue_addon rows (shared)',
			band_label VARCHAR(100) NOT NULL COMMENT 'e.g. Under $250K, $250K-$500K',
			band_min DECIMAL(14,2) NULL,
			band_max DECIMAL(14,2) NULL COMMENT 'NULL = no upper limit (Over $10M etc.)',
			price DECIMAL(10,2) NULL COMMENT 'NULL when is_custom = 1',
			is_custom TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = this band routes to custom-quote path (5M-10M, Over 10M)',
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY band_unique (band_type, entity_group, band_label),
			KEY band_type (band_type),
			KEY entity_group (entity_group)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Question Sets table — stores base and filing-status-specific question configurations.
	 * Uses inheritance model: base set (filing_status=NULL) + overrides per filing status.
	 */
	private static function create_question_sets_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g. Individual, Individual_MFJ, Business',
			return_type VARCHAR(20) NOT NULL COMMENT 'individual or business',
			filing_status VARCHAR(50) NULL COMMENT 'NULL for base; single/mfj/mfs/hoh for variants',
			parent_set_id BIGINT UNSIGNED NULL COMMENT 'FK to base set for inheritance',
			description TEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY return_type_filing (return_type, filing_status),
			KEY return_type (return_type),
			KEY filing_status (filing_status),
			KEY parent_set_id (parent_set_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Question Set Items table — maps line items to question sets with optional overrides.
	 * Supports inheritance: NULL override values mean "use parent/base value".
	 */
	private static function create_question_set_items_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_set_id BIGINT UNSIGNED NOT NULL COMMENT 'FK to question_sets',
			line_item_id BIGINT UNSIGNED NOT NULL COMMENT 'FK to line_items',
			sort_order INT NOT NULL DEFAULT 0,
			override_label VARCHAR(255) NULL COMMENT 'Filing-status-specific wording; NULL = use base',
			override_followup_label VARCHAR(255) NULL COMMENT 'Custom quantity field label',
			override_fee DECIMAL(10,2) NULL COMMENT 'Filing-status-specific pricing; NULL = use base',
			override_reveal_followup TINYINT(1) NULL COMMENT '1/0; NULL = use base',
			is_hidden TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = hide this question for this filing status',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY set_item (question_set_id, line_item_id),
			KEY question_set_id (question_set_id),
			KEY line_item_id (line_item_id),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Seeds the tables with the exact values from the client's actual Excel
	 * calculator (per PROJECT_SPEC.md), so the plugin is testable immediately
	 * after activation without manual data entry. Uses INSERT IGNORE-style
	 * checks so re-activating doesn't duplicate rows.
	 */
	private static function seed_default_data() {
		global $wpdb;

		$line_items_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		// Only seed if table is empty — avoids overwriting admin edits on reactivation.
		$existing_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$line_items_table}" );
		if ( $existing_count > 0 ) {
			return;
		}

		$individual_items = array(
			array( 'w2_wages', 'Did anyone in your household receive W-2 income from an employer?', 350, 'qty_times_fee', null, 0, null, 1, 0, 'This includes wages, salaries, bonuses, commissions, and other employment income reported on a W-2.' ),
			array( 'multi_state', 'Did anyone in your household live or work in more than one state during the year?', 150, 'qty_times_fee', null, 0, null, 1, 10, 'This helps determine whether multiple state tax returns may be required.' ),
			array( 'interest_dividends', 'Did anyone in your household earn interest or dividends from a bank or investment account?', 25, 'flat', null, 0, null, 1, 20, 'Look for Forms 1099-INT or 1099-DIV from your bank or brokerage.' ),
			array( 'brokerage_sales', 'Did anyone in your household sell stocks, ETFs, mutual funds, or other investments?', 25, 'qty_times_fee', null, 0, null, 1, 30, 'You can usually find this information on Form 1099-B or your year-end brokerage statement.' ),
			array( 'rental_property', 'Did anyone in your household own a rental property during the year?', 200, 'qty_times_fee', null, 0, null, 1, 40, 'Include long-term, short-term, or vacation rentals.' ),
			array( 'self_employed', 'Was anyone in your household self-employed or the owner of a sole proprietorship or single-member LLC?', 200, 'qty_times_fee', null, 0, null, 1, 50, 'Include freelance work, consulting, side businesses, or gig economy income (1099 income).' ),
			array( 'farm_income', 'Did anyone in your household receive farm income?', 275, 'qty_times_fee', null, 0, null, 1, 60, 'Include income and expenses from farming or agricultural operations.' ),
			array( 'k1_received', 'Did anyone in your household receive a Schedule K-1?', 50, 'qty_times_fee', null, 0, null, 1, 70, 'K-1s are commonly issued by partnerships, S corporations, estates, or trusts.' ),
			array( 'foreign_accounts', 'Did anyone in your household have foreign bank accounts or earn foreign income?', 250, 'qty_times_fee', null, 1, null, 1, 80, 'This helps determine whether FBAR or other international reporting requirements apply.' ),
			array( 'crypto', 'Did anyone in your household buy, sell, or trade cryptocurrency?', 250, 'qty_times_fee', null, 0, null, 1, 90, 'Include Bitcoin, Ethereum, NFTs, or any other digital assets. If over 100 transactions or $100K, custom quote required.' ),
			array( 'tuition', 'Did anyone in your household pay qualified college tuition?', 25, 'flat', null, 0, null, 1, 100, 'Look for Form 1098-T from the educational institution.' ),
			array( 'childcare', 'Did anyone in your household pay for childcare or dependent care?', 25, 'flat', null, 0, null, 1, 110, 'Include daycare, preschool, before/after-school care, or summer day camps.' ),
			array( 'hsa', 'Did anyone in your household contribute to or receive distributions from a Health Savings Account (HSA)?', 25, 'qty_times_fee', null, 0, null, 1, 120, 'Look for Forms 1099-SA or 5498-SA.' ),
			array( 'home_sale', 'Did anyone in your household sell a home during the year?', 150, 'qty_times_fee', null, 0, null, 1, 130, 'Look for Form 1099-S or include the sale of your primary residence or investment property.' ),
			array( 'retirement_distributions', 'Did anyone in your household receive retirement distributions?', 25, 'hardcoded', 100, 0, null, 1, 140, 'Include withdrawals from a 401(k), IRA, Roth IRA, pension, annuity, or similar retirement account.' ),
			array( 'additional_personal', 'Do you also need a quote for additional personal tax returns?', 0, 'flat', 0, 0, null, 1, 160, 'Select Yes if you need quotes for more than one personal tax return.' ),
			array( 'additional_business', 'Do you also need a quote for any business tax returns?', 0, 'flat', 0, 0, null, 1, 170, 'Select Yes if you need quotes for business tax returns in addition to what you have already selected.' ),
			array( 'meetings', 'Meetings (end of year recap, tax return review, misc.)', 250, 'qty_times_fee', null, 0, null, 0, 150, 'Internal use only.' ),
		);

		foreach ( $individual_items as $item ) {
			// Special handling for crypto: use threshold_rules JSON
			$threshold_rules = null;
			if ( $item[0] === 'crypto' ) {
				$threshold_rules = wp_json_encode( array(
					'logic'      => 'OR',
					'conditions' => array(
						array(
							'type'     => 'transactions',
							'operator' => 'above',
							'value'    => 100,
						),
						array(
							'type'     => 'dollar_value',
							'operator' => 'above',
							'value'    => 100000,
						),
					),
				) );
			}

			$wpdb->insert(
				$line_items_table,
				array(
					'quote_type'              => 'individual',
					'item_key'                => $item[0],
					'label'                   => $item[1],
					'fee'                     => $item[2],
					'pricing_pattern'         => $item[3],
					'hardcoded_value'         => $item[4],
					'is_custom_quote_trigger' => $item[5],
					'threshold_rules'         => $threshold_rules,
					'reveal_followup'         => $item[7],
					'is_active'               => $item[8],
					'sort_order'              => $item[9],
					'tooltip'                 => $item[10],
				)
			);
		}

		$business_items = array(
			array( 'extra_k1s', 'Does your business have more than one owner or partner?', 25, 'qty_times_fee', null, 0, null, 1, 10, 'Include any business where ownership is shared with another individual or entity. This helps us determine whether additional Schedule K-1s will need to be prepared.' ),
			array( 'multi_state', 'Does your business operate or file taxes in more than one state?', 250, 'qty_times_fee', null, 0, null, 1, 20, 'This includes having employees, offices, property, or business activity in multiple states that may require additional state tax filings.' ),
			array( 'depreciation_schedule', 'Do you need us to create or maintain a fixed asset and depreciation schedule?', 250, 'qty_times_fee', null, 0, null, 1, 30, 'Select "Yes" if your business has purchased equipment, furniture, vehicles, buildings, or other assets that need to be tracked and depreciated.' ),
			array( 'foreign_partner', 'Does your business have any foreign owners or partners?', 350, 'qty_times_fee', null, 0, null, 1, 40, 'This includes individuals or entities that are not U.S. persons and may require additional tax reporting.' ),
			array( 'books_dont_match', 'Do your accounting records differ from what was reported on your prior tax returns?', 250, 'qty_times_fee', null, 0, null, 1, 50, 'For example, if your QuickBooks balance doesn\'t match your last filed tax return or if prior accountant adjustments haven\'t been recorded.' ),
			array( 'excess_equipment', 'Does your business own more than 25 fixed assets or pieces of equipment?', 250, 'qty_times_fee', null, 0, null, 1, 60, 'Include machinery, vehicles, computers, furniture, buildings, and other depreciable business assets. This helps us estimate the complexity of maintaining your depreciation schedule.' ),
			array( 'audit_support', 'Under IRS audit / needs audit support', 350, 'qty_times_fee', null, 0, null, 0, 70, 'Audit representation is not included in standard engagement.' ),
		);

		foreach ( $business_items as $item ) {
			$wpdb->insert(
				$line_items_table,
				array(
					'quote_type'              => 'business',
					'item_key'                => $item[0],
					'label'                   => $item[1],
					'fee'                     => $item[2],
					'pricing_pattern'         => $item[3],
					'hardcoded_value'         => $item[4],
					'is_custom_quote_trigger' => $item[5],
					'reveal_followup'         => $item[7],
					'is_active'               => $item[8],
					'sort_order'              => $item[9],
					'tooltip'                 => $item[10],
				)
			);
		}

		self::seed_rate_bands();
	}

	/**
	 * Seeds the Business rate reference tables: asset bands (by entity group)
	 * and revenue add-ons. Matches PROJECT_SPEC.md Section 4, Part A, Step 3.
	 * Schedule L thresholds are handled in code (rules engine, Phase 2) rather
	 * than as DB rows, since they're conditional logic, not a lookup table —
	 * but are documented here for reference:
	 *   C-Corp / S-Corp: receipts < $250K AND assets < $250K → $999 flat
	 *   Partnership:     receipts < $250K AND assets <= $1M  → $999 flat
	 */
	private static function seed_rate_bands() {
		global $wpdb;
		$table = $wpdb->prefix . TQB_TABLE_RATE_BANDS;

		$asset_bands = array(
			// label, min, max, c_s_corp price, partnership price, is_custom
			array( 'Under $250K', 0, 250000, 1250, 1250, 0 ),
			array( '$250K-$500K', 250000, 500000, 1250, 1250, 0 ),
			array( '$500K-$1M', 500000, 1000000, 1500, 1250, 0 ),
			array( '$1M-$2M', 1000000, 2000000, 1500, 1500, 0 ),
			array( '$2M-$5M', 2000000, 5000000, 1750, 1700, 0 ),
			array( '$5M-$10M', 5000000, 10000000, null, null, 1 ),
			array( 'Over $10M', 10000000, null, null, null, 1 ),
		);

		$sort = 0;
		foreach ( $asset_bands as $band ) {
			list( $label, $min, $max, $c_s_price, $p_price, $is_custom ) = $band;

			$wpdb->insert( $table, array(
				'band_type'    => 'asset_band',
				'entity_group' => 'c_s_corp',
				'band_label'   => $label,
				'band_min'     => $min,
				'band_max'     => $max,
				'price'        => $c_s_price,
				'is_custom'    => $is_custom,
				'sort_order'   => $sort,
			) );

			$wpdb->insert( $table, array(
				'band_type'    => 'asset_band',
				'entity_group' => 'partnership',
				'band_label'   => $label,
				'band_min'     => $min,
				'band_max'     => $max,
				'price'        => $p_price,
				'is_custom'    => $is_custom,
				'sort_order'   => $sort,
			) );

			$sort += 10;
		}

		$revenue_addons = array(
			array( 'Under $250K', 0, 250000, 0 ),
			array( '$250K-$1M', 250000, 1000000, 0 ),
			array( 'Over $1M', 1000000, null, 200 ),
		);

		$sort = 0;
		foreach ( $revenue_addons as $band ) {
			list( $label, $min, $max, $addon ) = $band;

			$wpdb->insert( $table, array(
				'band_type'    => 'revenue_addon',
				'entity_group' => null,
				'band_label'   => $label,
				'band_min'     => $min,
				'band_max'     => $max,
				'price'        => $addon,
				'is_custom'    => 0,
				'sort_order'   => $sort,
			) );

			$sort += 10;
		}
	}

	/**
	 * Idempotent cleanup: removes duplicate line items, keeping the lowest ID.
	 * Duplicates are identified by (item_key, quote_type) pairs.
	 * Safe to run multiple times — if no duplicates exist, does nothing.
	 *
	 * @param string $table_name Full table name (with prefix)
	 */
	private static function cleanup_duplicate_line_items( $table_name ) {
		global $wpdb;

		// Find all (item_key, quote_type) pairs that appear more than once
		$duplicates = $wpdb->get_results(
			"SELECT item_key, quote_type, MIN(id) as keep_id, COUNT(*) as cnt
			 FROM {$table_name}
			 GROUP BY item_key, quote_type
			 HAVING cnt > 1",
			ARRAY_A
		);

		if ( ! $duplicates || count( $duplicates ) === 0 ) {
			return; // No duplicates found
		}

		foreach ( $duplicates as $dup ) {
			// Delete all rows except the one with the lowest ID
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE item_key = %s AND quote_type = %s AND id > %d",
					$dup['item_key'],
					$dup['quote_type'],
					$dup['keep_id']
				)
			);
		}
	}

	/**
	 * Idempotent cleanup: removes duplicate rate band rows, keeping the lowest ID.
	 * Duplicates are identified by (band_type, entity_group, band_label) triplets.
	 * Safe to run multiple times — if no duplicates exist, does nothing.
	 *
	 * @param string $table_name Full table name (with prefix)
	 */
	private static function cleanup_duplicate_rate_bands( $table_name ) {
		global $wpdb;

		// Find all (band_type, entity_group, band_label) triplets that appear more than once
		$duplicates = $wpdb->get_results(
			"SELECT band_type, entity_group, band_label, MIN(id) as keep_id, COUNT(*) as cnt
			 FROM {$table_name}
			 GROUP BY band_type, entity_group, band_label
			 HAVING cnt > 1",
			ARRAY_A
		);

		if ( ! $duplicates || count( $duplicates ) === 0 ) {
			return; // No duplicates found
		}

		foreach ( $duplicates as $dup ) {
			// Delete all rows except the one with the lowest ID
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE band_type = %s AND entity_group <=> %s AND band_label = %s AND id > %d",
					$dup['band_type'],
					$dup['entity_group'],
					$dup['band_label'],
					$dup['keep_id']
				)
			);
		}
	}

	/**
	 * Ensures UNIQUE constraints exist on both tables and are actually enforced.
	 * If a constraint doesn't exist or isn't being enforced, this adds/rebuilds it.
	 * Idempotent — safe to call multiple times.
	 *
	 * @param string $line_items_table Full table name (with prefix)
	 * @param string $rate_bands_table Full table name (with prefix)
	 */
	private static function ensure_unique_constraints( $line_items_table, $rate_bands_table ) {
		global $wpdb;

		// --- Line items: ensure UNIQUE (item_key, quote_type) ---
		$line_items_keys = $wpdb->get_results(
			"SHOW KEYS FROM {$line_items_table} WHERE Key_name = 'item_key_type'",
			ARRAY_A
		);

		if ( ! $line_items_keys || count( $line_items_keys ) === 0 ) {
			// Constraint doesn't exist — add it
			$wpdb->query( "ALTER TABLE {$line_items_table} ADD UNIQUE KEY item_key_type (item_key, quote_type)" );
		}

		// --- Rate bands: no built-in UNIQUE constraint yet, but we should add one ---
		// (band_type, entity_group, band_label) should be unique
		$rate_bands_keys = $wpdb->get_results(
			"SHOW KEYS FROM {$rate_bands_table} WHERE Key_name = 'band_unique'",
			ARRAY_A
		);

		if ( ! $rate_bands_keys || count( $rate_bands_keys ) === 0 ) {
			// Constraint doesn't exist — add it
			$wpdb->query( "ALTER TABLE {$rate_bands_table} ADD UNIQUE KEY band_unique (band_type, entity_group, band_label)" );
		}
	}

	/**
	 * Adds threshold_rules column if it doesn't exist.
	 * Idempotent — safe to call multiple times.
	 *
	 * @param string $table_name Full table name (with prefix)
	 */
	private static function add_threshold_rules_column( $table_name ) {
		global $wpdb;

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );

		if ( ! in_array( 'threshold_rules', $column_names, true ) ) {
			$after = in_array( 'threshold_trigger', $column_names, true ) ? 'threshold_trigger' : null;
			$sql = "ALTER TABLE {$table_name} ADD COLUMN threshold_rules LONGTEXT NULL COMMENT 'JSON: structured threshold logic'";
			if ( $after ) {
				$sql .= " AFTER {$after}";
			}
			$wpdb->query( $sql );
		}
	}

	/**
	 * Adds reveal_followup column if it doesn't exist.
	 * Idempotent — safe to call multiple times.
	 *
	 * @param string $table_name Full table name (with prefix)
	 */
	private static function add_reveal_followup_column( $table_name ) {
		global $wpdb;

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
		$column_names = wp_list_pluck( $columns, 'Field' );

		if ( ! in_array( 'reveal_followup', $column_names, true ) ) {
			$after = in_array( 'threshold_rules', $column_names, true ) ? 'threshold_rules' : 'threshold_trigger';
			$sql = "ALTER TABLE {$table_name} ADD COLUMN reveal_followup TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = hide until checked; 0 = always show'";
			if ( $after ) {
				$sql .= " AFTER {$after}";
			}
			$wpdb->query( $sql );
		}
	}

	/**
	 * Backfills threshold_rules JSON from existing threshold_qty/threshold_trigger.
	 * For items with old-format thresholds, builds the new JSON structure.
	 * Idempotent — only updates rows where threshold_rules is NULL but old columns have data.
	 *
	 * @param string $table_name Full table name (with prefix)
	 */
	private static function backfill_threshold_rules( $table_name ) {
		global $wpdb;

		// Find items with old-format thresholds (threshold_qty or threshold_trigger set)
		// but NO new-format threshold_rules yet
		$items_to_backfill = $wpdb->get_results(
			"SELECT id, threshold_qty, threshold_trigger
			 FROM {$table_name}
			 WHERE threshold_rules IS NULL
			 AND (threshold_qty IS NOT NULL OR threshold_trigger IS NOT NULL)",
			ARRAY_A
		);

		foreach ( $items_to_backfill as $item ) {
			$threshold_rules = array(
				'logic'      => 'AND',
				'conditions' => array(
					array(
						'type'     => 'qty',
						'operator' => $item['threshold_trigger'] ?? 'above',
						'value'    => (int) $item['threshold_qty'],
					),
				),
			);

			$wpdb->update(
				$table_name,
				array( 'threshold_rules' => wp_json_encode( $threshold_rules ) ),
				array( 'id' => $item['id'] ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Seeds the default question sets for filing status support (Task 4).
	 * Creates base Individual set + 4 filing status variants (Single, MFJ, MFS, HOH),
	 * plus Business set.
	 *
	 * Only runs if question_sets table is empty to avoid duplicates on reactivation.
	 */
	private static function seed_default_question_sets() {
		global $wpdb;

		$sets_table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;
		$items_table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;
		$line_items_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		// Only seed if table is empty
		$existing_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$sets_table}" );
		if ( $existing_count > 0 ) {
			return;
		}

		// Create base Individual set
		$wpdb->insert(
			$sets_table,
			array(
				'name'           => 'Individual',
				'return_type'    => 'individual',
				'filing_status'  => null,
				'parent_set_id'  => null,
				'description'    => 'Base Individual return set (inherited by all filing statuses)',
				'is_active'      => 1,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		$base_set_id = $wpdb->insert_id;

		// Create filing status variant sets (inherit from base)
		$filing_statuses = array( 'single', 'mfj', 'mfs', 'hoh' );
		$set_ids = array();

		foreach ( $filing_statuses as $status ) {
			$label = TQB_FILING_STATUS_LABELS[ $status ] ?? ucfirst( $status );
			$wpdb->insert(
				$sets_table,
				array(
					'name'           => 'Individual_' . $status,
					'return_type'    => 'individual',
					'filing_status'  => $status,
					'parent_set_id'  => $base_set_id,
					'description'    => $label . ' return set (inherits from Individual base)',
					'is_active'      => 1,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%d' )
			);
			$set_ids[ $status ] = $wpdb->insert_id;
		}

		// Create Business set
		$wpdb->insert(
			$sets_table,
			array(
				'name'           => 'Business',
				'return_type'    => 'business',
				'filing_status'  => null,
				'parent_set_id'  => null,
				'description'    => 'Business return set (no filing status variations)',
				'is_active'      => 1,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		$business_set_id = $wpdb->insert_id;

		// Populate base Individual set with all line items
		$individual_items = $wpdb->get_results(
			"SELECT id, sort_order FROM {$line_items_table} WHERE quote_type = 'individual' ORDER BY sort_order ASC",
			ARRAY_A
		);

		foreach ( $individual_items as $item ) {
			$wpdb->insert(
				$items_table,
				array(
					'question_set_id' => $base_set_id,
					'line_item_id'    => $item['id'],
					'sort_order'      => $item['sort_order'],
				),
				array( '%d', '%d', '%d' )
			);
		}

		// Populate Business set with all business line items
		$business_items = $wpdb->get_results(
			"SELECT id, sort_order FROM {$line_items_table} WHERE quote_type = 'business' ORDER BY sort_order ASC",
			ARRAY_A
		);

		foreach ( $business_items as $item ) {
			$wpdb->insert(
				$items_table,
				array(
					'question_set_id' => $business_set_id,
					'line_item_id'    => $item['id'],
					'sort_order'      => $item['sort_order'],
				),
				array( '%d', '%d', '%d' )
			);
		}

		// Add wording overrides for MFJ (change "anyone" to "you or your spouse")
		// This is an example — the admin will add more overrides via the UI
		$mfj_overrides = array(
			'w2_wages'          => 'Did you or your spouse receive W-2 income from an employer?',
			'multi_state'       => 'Did you or your spouse live or work in more than one state during the year?',
			'interest_dividends' => 'Did you or your spouse earn interest or dividends from a bank or investment account?',
			'brokerage_sales'   => 'Did you or your spouse sell stocks, ETFs, mutual funds, or other investments?',
			'rental_property'   => 'Did you or your spouse own a rental property during the year?',
			'k1_received'       => 'Did you or your spouse receive a Schedule K-1?',
			'foreign_accounts'  => 'Did you or your spouse have foreign bank accounts or earn foreign income?',
			'crypto'            => 'Did you or your spouse buy, sell, or trade cryptocurrency?',
			'tuition'           => 'Did you or your spouse pay qualified college tuition?',
			'childcare'         => 'Did you or your spouse pay for childcare or dependent care?',
			'hsa'               => 'Did you or your spouse contribute to or receive distributions from a Health Savings Account (HSA)?',
			'home_sale'         => 'Did you or your spouse sell a home during the year?',
			'retirement_distributions' => 'Did you or your spouse receive retirement distributions?',
		);

		foreach ( $mfj_overrides as $item_key => $override_label ) {
			$line_item_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$line_items_table} WHERE item_key = %s AND quote_type = 'individual'",
					$item_key
				)
			);

			if ( $line_item_id ) {
				$wpdb->update(
					$items_table,
					array( 'override_label' => $override_label ),
					array(
						'question_set_id' => $set_ids['mfj'],
						'line_item_id'    => $line_item_id,
					),
					array( '%s' ),
					array( '%d', '%d' )
				);
			}
		}
	}

	/**
	 * Checks if all required tables exist. For debugging/recovery.
	 * Can be called manually via WP-CLI or admin settings page.
	 *
	 * @return array Status array with 'all_exist' (bool) and 'missing_tables' (array)
	 */
	public static function verify_tables_exist() {
		global $wpdb;

		$tables_to_check = array(
			TQB_TABLE_SUBMISSIONS => $wpdb->prefix . TQB_TABLE_SUBMISSIONS,
			TQB_TABLE_LINE_ITEMS => $wpdb->prefix . TQB_TABLE_LINE_ITEMS,
			TQB_TABLE_RATE_BANDS => $wpdb->prefix . TQB_TABLE_RATE_BANDS,
			TQB_TABLE_QUESTION_SETS => $wpdb->prefix . TQB_TABLE_QUESTION_SETS,
			TQB_TABLE_QUESTION_SET_ITEMS => $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS,
		);

		$missing = array();
		foreach ( $tables_to_check as $label => $table_name ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
				$missing[] = $label;
			}
		}

		return array(
			'all_exist' => empty( $missing ),
			'missing_tables' => $missing,
		);
	}
}
