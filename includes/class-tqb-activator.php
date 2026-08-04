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

		// Verify critical table was created; if not, create it with direct SQL
		$line_items_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$line_items_table}'" ) !== $line_items_table ) {
			error_log( 'TQB: line_items table not created, using SQL fallback' );
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$line_items_table}` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`quote_type` VARCHAR(20) NOT NULL,
				`item_key` VARCHAR(100) NOT NULL,
				`label` VARCHAR(255) NOT NULL,
				`fee` DECIMAL(10,2) NOT NULL DEFAULT 0,
				`pricing_pattern` VARCHAR(20) NOT NULL DEFAULT 'qty_times_fee',
				`is_custom_quote_trigger` TINYINT(1) NOT NULL DEFAULT 0,
				`threshold_rules` LONGTEXT NULL,
				`reveal_followup` TINYINT(1) NOT NULL DEFAULT 1,
				`is_active` TINYINT(1) NOT NULL DEFAULT 1,
				`sort_order` INT NOT NULL DEFAULT 0,
				`tooltip` TEXT NULL,
				`notes` TEXT NULL,
				`filing_status` VARCHAR(50) NULL,
				PRIMARY KEY  (`id`),
				UNIQUE KEY `item_key_type` (`item_key`, `quote_type`),
				KEY `quote_type` (`quote_type`),
				KEY `sort_order` (`sort_order`)
			) {$charset_collate}" );
		}

		// Now seed the data
		self::seed_default_data();
		self::seed_default_settings();

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

		// --- Submissions table: add business_name column ---
		// (Existed on some earlier live installs via manual ALTER TABLE
		// outside version control; adding it here properly so fresh installs
		// and any install that never got it end up consistent.)
		if ( ! in_array( 'business_name', $sub_column_names, true ) ) {
			$after = in_array( 'contact_phone', $sub_column_names, true ) ? 'contact_phone' : null;
			$sql = "ALTER TABLE {$submissions_table} ADD COLUMN business_name VARCHAR(255) NULL";
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

		// Migration: Fix status values for existing partial submissions
		// Submissions with status='completed' but NULL calculated_total are actually partial (in_progress)
		if ( in_array( 'status', $sub_column_names, true ) && in_array( 'last_completed_step', $sub_column_names, true ) ) {
			$wpdb->query(
				"UPDATE {$submissions_table} SET status = 'in_progress' WHERE status = 'completed' AND calculated_total IS NULL"
			);
			// Mark submissions with calculated_total as completed
			$wpdb->query(
				"UPDATE {$submissions_table} SET status = 'completed' WHERE calculated_total IS NOT NULL AND status = 'in_progress'"
			);
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

		// --- Remove 'hardcoded' pricing pattern (v1.2 patch) ---
		// Consolidated into 'flat' since both ignore quantity and only differ
		// in which admin field supplies the price. Any row still using
		// 'hardcoded' gets converted: its fee becomes the old hardcoded_value
		// (the number that was actually being charged), and pattern becomes 'flat'.
		self::migrate_hardcoded_pricing_pattern( $line_items_table );

		// --- Remove dead schema (v1.4 patch) ---
		// question_sets / question_set_items were leftover from an abandoned
		// parallel questions system (see class-tqb-question-sets.php, since
		// deleted) that was never actually reachable from the frontend.
		// hardcoded_value / threshold_qty / threshold_trigger on line_items
		// are deprecated columns the admin UI stopped writing to. Must run
		// AFTER migrate_hardcoded_pricing_pattern() above, since that function
		// still needs to read hardcoded_value before this drops it.
		self::cleanup_deprecated_schema( $line_items_table );

		update_option( 'tqb_db_version', TQB_VERSION );
	}

	/**
	 * Converts any line items still using the deprecated 'hardcoded' pricing
	 * pattern to 'flat', copying hardcoded_value into fee so the charged
	 * amount stays identical after the migration.
	 *
	 * @param string $line_items_table Fully-prefixed table name.
	 */
	private static function migrate_hardcoded_pricing_pattern( $line_items_table ) {
		global $wpdb;

		// Column may already be gone from a previous run of
		// cleanup_deprecated_schema() — nothing to migrate in that case.
		if ( ! self::column_exists( $line_items_table, 'hardcoded_value' ) ) {
			return;
		}

		$hardcoded_rows = $wpdb->get_results(
			"SELECT id, fee, hardcoded_value FROM {$line_items_table} WHERE pricing_pattern = 'hardcoded'"
		);

		if ( empty( $hardcoded_rows ) ) {
			return;
		}

		foreach ( $hardcoded_rows as $row ) {
			$new_fee = ( null !== $row->hardcoded_value && '' !== $row->hardcoded_value )
				? (float) $row->hardcoded_value
				: (float) $row->fee; // fallback: keep existing fee if hardcoded_value was never set

			$wpdb->update(
				$line_items_table,
				array(
					'pricing_pattern' => 'flat',
					'fee'             => $new_fee,
				),
				array( 'id' => $row->id ),
				array( '%s', '%f' ),
				array( '%d' )
			);
		}
	}

	/**
	 * One-time cleanup of schema left over from earlier iterations of this
	 * plugin (v1.4). Safe to call on every upgrade — every step checks
	 * existence first, so re-running after the first successful cleanup is
	 * a fast no-op rather than an error.
	 *
	 * Removes:
	 *   - wp_tqb_question_sets, wp_tqb_question_set_items (tables) — belonged
	 *     to a parallel "question sets" questions system that was built but
	 *     never actually wired to the frontend (its AJAX handler was never
	 *     registered). Confirmed zero live references before removal.
	 *   - hardcoded_value, threshold_qty, threshold_trigger (columns on
	 *     line_items) — the admin UI no longer writes to any of these; the
	 *     'hardcoded' pricing pattern was consolidated into 'flat' (see
	 *     migrate_hardcoded_pricing_pattern(), which must run first and does
	 *     via the call order in upgrade()), and threshold_qty/trigger were
	 *     replaced by the JSON threshold_rules column.
	 *
	 * @param string $line_items_table Fully-prefixed line_items table name.
	 */
	private static function cleanup_deprecated_schema( $line_items_table ) {
		global $wpdb;

		// Drop the two orphaned tables, if they still exist.
		$question_sets_table = $wpdb->prefix . 'tqb_question_sets';
		$question_set_items_table = $wpdb->prefix . 'tqb_question_set_items';

		// Items table first (no real FK constraint, but this is the logical order).
		$wpdb->query( "DROP TABLE IF EXISTS `{$question_set_items_table}`" );
		$wpdb->query( "DROP TABLE IF EXISTS `{$question_sets_table}`" );

		// Drop deprecated columns on line_items, one at a time, only if present.
		$deprecated_columns = array( 'hardcoded_value', 'threshold_qty', 'threshold_trigger' );

		foreach ( $deprecated_columns as $column ) {
			if ( self::column_exists( $line_items_table, $column ) ) {
				$wpdb->query( "ALTER TABLE `{$line_items_table}` DROP COLUMN `{$column}`" );
			}
		}
	}

	/**
	 * Checks whether a column exists on a given table. Used to make schema
	 * migrations idempotent — safe to run on every upgrade without erroring
	 * on a column/table that a previous run already removed.
	 *
	 * @param string $table  Fully-prefixed table name.
	 * @param string $column Column name to check for.
	 * @return bool
	 */
	private static function column_exists( $table, $column ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table}` LIKE %s",
				$column
			)
		);

		return ! empty( $result );
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

		// Individual filing status base price + surcharges (Filing Status Configuration panel).
		add_option( 'tqb_individual_base_price', 500 );
		add_option( 'tqb_filing_status_label_single', 'Single' );
		add_option( 'tqb_filing_status_price_single', 0 );
		add_option( 'tqb_filing_status_label_mfj', 'Married Filing Jointly' );
		add_option( 'tqb_filing_status_price_mfj', 200 );
		add_option( 'tqb_filing_status_label_mfs', 'Married Filing Separately' );
		add_option( 'tqb_filing_status_price_mfs', 300 );
		add_option( 'tqb_filing_status_label_hoh', 'Head of Household' );
		add_option( 'tqb_filing_status_price_hoh', 150 );
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
			filing_status VARCHAR(50) NULL COMMENT 'single, mfj, mfs, hoh for individual; NULL for business',
			contact_name VARCHAR(255) NOT NULL,
			contact_email VARCHAR(255) NOT NULL,
			contact_phone VARCHAR(50) NOT NULL,
			business_name VARCHAR(255) NULL,
			answers LONGTEXT NOT NULL COMMENT 'JSON: raw question answers as submitted',
			calculated_total DECIMAL(10,2) NULL COMMENT 'NULL when is_custom_quote = 1',
			is_custom_quote TINYINT(1) NOT NULL DEFAULT 0,
			custom_quote_reason VARCHAR(255) NULL COMMENT 'e.g. crypto, foreign_accounts, assets_over_5m',
			status VARCHAR(20) NOT NULL DEFAULT 'in_progress' COMMENT 'completed, in_progress, abandoned',
			last_completed_step INT NOT NULL DEFAULT 0 COMMENT '1-5 for tracking partial submissions',
			hubspot_synced TINYINT(1) NOT NULL DEFAULT 0,
			hubspot_contact_id VARCHAR(100) NULL,
			hubspot_deal_id VARCHAR(100) NULL,
			hubspot_sync_failed TINYINT(1) NOT NULL DEFAULT 0,
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
			KEY followup_email_sent (followup_email_sent)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * The editable "checklist" line items — covers both the Individual sheet
	 * in full, and Part B (extras) of the Business sheet. This is what the
	 * admin dashboard repeater UI will read from and write to (Phase 3).
	 *
	 * pricing_pattern values (see PROJECT_SPEC.md Section 3):
	 *   'qty_times_fee' → IF(Yes, Qty*Fee, 0)
	 *   'flat'          → IF(Yes, Fee, 0)  — qty ignored
	 *
	 * NOTE: a third pattern, 'hardcoded' (priced off a separate hardcoded_value
	 * column instead of fee), existed through v1.2 and was removed in v1.3 as
	 * redundant with 'flat' — both ignore quantity, they only differed in which
	 * admin field supplied the number. See migrate_hardcoded_pricing_pattern().
	 * The hardcoded_value column itself is left in place (unused) rather than
	 * dropped, to avoid a destructive schema change on existing installs.
	 */
	private static function create_line_items_table( $wpdb, $charset_collate ) {
		$table_name = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_type VARCHAR(20) NOT NULL COMMENT 'individual or business',
			item_key VARCHAR(100) NOT NULL COMMENT 'stable slug, e.g. rental_property',
			label VARCHAR(255) NOT NULL,
			fee DECIMAL(10,2) NOT NULL DEFAULT 0,
			pricing_pattern VARCHAR(20) NOT NULL DEFAULT 'qty_times_fee' COMMENT 'qty_times_fee or flat',
			is_custom_quote_trigger TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = any Yes answer routes to custom-quote path instead of pricing (e.g. crypto, FBAR)',
			threshold_rules LONGTEXT NULL COMMENT 'JSON: structured threshold logic with logic (AND/OR) and conditions array',
			reveal_followup TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = hide quantity/dollar field until checkbox checked; 0 = always show (legacy behavior)',
			is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = hidden from public form (e.g. audit, meetings)',
			sort_order INT NOT NULL DEFAULT 0,
			tooltip TEXT NULL COMMENT 'Customer-facing help text shown on hover',
			notes TEXT NULL COMMENT 'Internal notes for admin reference',
			filing_status VARCHAR(50) NULL COMMENT 'NULL = show for all filing statuses; single/mfj/mfs/hoh = restrict',
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
	 * Seeds the tables with the exact production values exported from the
	 * live Tavola site (tavola.sql), so a fresh activation reproduces the
	 * client's real configuration immediately — no manual re-entry needed.
	 * Uses named keys (not positional array offsets) to avoid the field
	 * misalignment bug the previous version of this function had, where
	 * is_active/sort_order/tooltip were silently shifted by one column.
	 * Only the submissions table is deliberately left empty on activation.
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
			array(
				'item_key'        => 'w2_wages',
				'label'           => 'Did anyone in your household receive W-2 income from an employer?',
				'fee'             => 350,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'This includes wages, salaries, bonuses, commissions, and other employment income reported on a W-2.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'multi_state',
				'label'           => 'Did anyone in your household live or work in more than one state during the year?',
				'fee'             => 150,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'This helps determine whether multiple state tax returns may be required.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'interest_dividends',
				'label'           => 'Did anyone in your household earn interest or dividends from a bank or investment account?',
				'fee'             => 25,
				'pricing_pattern' => 'flat',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Look for Forms 1099-INT or 1099-DIV from your bank or brokerage.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'brokerage_sales',
				'label'           => 'Did anyone in your household sell stocks, ETFs, mutual funds, or other investments?',
				'fee'             => 25,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'You can usually find this information on Form 1099-B or your year-end brokerage statement.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'rental_property',
				'label'           => 'Did anyone in your household own a rental property during the year?',
				'fee'             => 200,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include long-term, short-term, or vacation rentals.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'self_employed',
				'label'           => 'Was anyone in your household self-employed or the owner of a sole proprietorship or single-member LLC?',
				'fee'             => 200,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include freelance work, consulting, side businesses, or gig economy income (1099 income).',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'farm_income',
				'label'           => 'Did anyone in your household receive farm income?',
				'fee'             => 275,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include income and expenses from farming or agricultural operations.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'k1_received',
				'label'           => 'Did anyone in your household receive a Schedule K-1?',
				'fee'             => 50,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'K-1s are commonly issued by partnerships, S corporations, estates, or trusts.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'foreign_accounts',
				'label'           => 'Did anyone in your household have foreign bank accounts or earn foreign income?',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 1,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'This helps determine whether FBAR or other international reporting requirements apply.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'crypto',
				'label'           => 'Did anyone in your household buy, sell, or trade cryptocurrency?',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => wp_json_encode( array(
					'logic'      => 'AND',
					'conditions' => array(
						array(
							'type'     => 'qty',
							'operator' => 'above',
							'value'    => 100,
						),
					),
				) ),
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include Bitcoin, Ethereum, NFTs, or any other digital assets.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'tuition',
				'label'           => 'Did anyone in your household pay qualified college tuition?',
				'fee'             => 25,
				'pricing_pattern' => 'flat',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Look for Form 1098-T from the educational institution.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'childcare',
				'label'           => 'Did anyone in your household pay for childcare or dependent care?',
				'fee'             => 25,
				'pricing_pattern' => 'flat',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include daycare, preschool, before/after-school care, or summer day camps.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'hsa',
				'label'           => 'Did anyone in your household contribute to or receive distributions from a Health Savings Account (HSA)?',
				'fee'             => 25,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Look for Forms 1099-SA or 5498-SA.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'home_sale',
				'label'           => 'Did anyone in your household sell a home during the year?',
				'fee'             => 150,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Look for Form 1099-S or include the sale of your primary residence or investment property.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'retirement_distributions',
				'label'           => 'Did anyone in your household receive retirement distributions?',
				'fee'             => 25,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include withdrawals from a 401(k), IRA, Roth IRA, pension, annuity, or similar retirement account.',
				'filing_status'   => 'mfj',
			),
			array(
				'item_key'        => 'meetings',
				'label'           => 'Meetings (end of year recap, tax return review, misc.)',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 0,
				'is_active'       => 0,
				'sort_order'      => 0,
				'tooltip'         => '',
				'filing_status'   => 'mfj',
			),
		);

		foreach ( $individual_items as $item ) {
			$wpdb->insert(
				$line_items_table,
				array_merge( array( 'quote_type' => 'individual' ), $item )
			);
		}

		$business_items = array(
			array(
				'item_key'        => 'extra_k1s',
				'label'           => 'Does your business have more than one owner or partner?',
				'fee'             => 25,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include any business where ownership is shared with another individual or entity. This helps us determine whether additional Schedule K-1s will need to be prepared.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'multi_state',
				'label'           => 'Does your business operate or file taxes in more than one state?',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'This includes having employees, offices, property, or business activity in multiple states that may require additional state tax filings.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'depreciation_schedule',
				'label'           => 'Do you need us to create or maintain a fixed asset and depreciation schedule?',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Select "Yes" if your business has purchased equipment, furniture, vehicles, buildings, or other assets that need to be tracked and depreciated.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'foreign_partner',
				'label'           => 'Does your business have any foreign owners or partners?',
				'fee'             => 350,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'This includes individuals or entities that are not U.S. persons and may require additional tax reporting.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'books_dont_match',
				'label'           => 'Do your accounting records differ from what was reported on your prior tax returns?',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'For example, if your QuickBooks balance doesn\'t match your last filed tax return or if prior accountant adjustments haven\'t been recorded.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'excess_equipment',
				'label'           => 'Does your business own more than 25 fixed assets or pieces of equipment?',
				'fee'             => 250,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 1,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => 'Include machinery, vehicles, computers, furniture, buildings, and other depreciable business assets. This helps us estimate the complexity of maintaining your depreciation schedule.',
				'filing_status'   => null,
			),
			array(
				'item_key'        => 'audit_support',
				'label'           => 'Under IRS audit / needs audit support',
				'fee'             => 350,
				'pricing_pattern' => 'qty_times_fee',
				'is_custom_quote_trigger' => 0,
				'threshold_rules' => null,
				'reveal_followup' => 0,
				'is_active'       => 1,
				'sort_order'      => 0,
				'tooltip'         => '',
				'filing_status'   => null,
			),
		);

		foreach ( $business_items as $item ) {
			$wpdb->insert(
				$line_items_table,
				array_merge( array( 'quote_type' => 'business' ), $item )
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
