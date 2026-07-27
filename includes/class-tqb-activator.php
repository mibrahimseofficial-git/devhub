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

		self::create_submissions_table( $wpdb, $charset_collate );
		self::create_line_items_table( $wpdb, $charset_collate );
		self::create_rate_bands_table( $wpdb, $charset_collate );
		self::seed_default_data();
		self::seed_default_settings();

		update_option( 'tqb_db_version', TQB_VERSION );
	}

	/**
	 * Runs on plugin upgrade to fix existing data.
	 */
	public static function upgrade() {
		global $wpdb;

		// Fix crypto and tuition items: change pricing_pattern from qty_times_fee to flat
		$wpdb->update(
			$wpdb->prefix . 'tqb_line_items',
			array( 'pricing_pattern' => 'flat' ),
			array( 'item_key' => 'crypto' ),
			array( '%s' ),
			array( '%s' )
		);

		$wpdb->update(
			$wpdb->prefix . 'tqb_line_items',
			array( 'pricing_pattern' => 'flat' ),
			array( 'item_key' => 'tuition' ),
			array( '%s' ),
			array( '%s' )
		);

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
			hubspot_synced TINYINT(1) NOT NULL DEFAULT 0,
			hubspot_contact_id VARCHAR(100) NULL,
			hubspot_deal_id VARCHAR(100) NULL,
			confirmation_email_sent TINYINT(1) NOT NULL DEFAULT 0,
			team_notified TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY quote_type (quote_type),
			KEY created_at (created_at),
			KEY is_custom_quote (is_custom_quote)
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
			is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = hidden from public form (e.g. audit, meetings)',
			sort_order INT NOT NULL DEFAULT 0,
			tooltip TEXT NULL COMMENT 'Customer-facing help text shown on hover',
			notes TEXT NULL COMMENT 'Internal notes for admin reference',
			PRIMARY KEY  (id),
			UNIQUE KEY item_key_type (item_key, quote_type),
			KEY quote_type (quote_type)
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
			KEY band_type (band_type),
			KEY entity_group (entity_group)
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
			array( 'w2_wages', 'W-2 wage income', 350, 'qty_times_fee', null, 0, 1, 0, 'Your W-2 form shows wages you earned as an employee. This applies to everyone filing a personal return.' ),
			array( 'multi_state', 'Lived or worked in more than one state', 150, 'qty_times_fee', null, 0, 1, 10, 'If you earned income or worked in a state other than your primary residence, additional state filings may be required.' ),
			array( 'interest_dividends', 'Bank or investment account interest/dividend statements', 25, 'flat', null, 0, 1, 20, 'Look for 1099-INT (interest) and 1099-DIV (dividends) forms from your banks and investment accounts.' ),
			array( 'brokerage_sales', 'Brokerage statement showing stock or investment sales', 25, 'qty_times_fee', null, 0, 1, 30, 'If you sold stocks, bonds, or other investments, you should receive a 1099-B form from your brokerage.' ),
			array( 'rental_property', 'Owns rental property', 200, 'qty_times_fee', null, 0, 1, 40, 'Income and expenses from rental properties need to be reported on your tax return.' ),
			array( 'self_employed', 'Self-employed or owns a small business / single-member LLC', 200, 'qty_times_fee', null, 0, 1, 50, 'If you run your own business or are a sole proprietor, your business income and expenses are reported on a Schedule C.' ),
			array( 'farm_income', 'Farm income', 275, 'qty_times_fee', null, 0, 1, 60, 'Income from farming activities, including livestock, crops, and other agricultural products.' ),
			array( 'k1_received', 'Received a K-1', 50, 'qty_times_fee', null, 0, 1, 70, 'A K-1 form reports income from partnerships, S-corporations, or estates/trusts.' ),
			array( 'foreign_accounts', 'Has foreign bank accounts or foreign income (FBAR)', 250, 'qty_times_fee', null, 1, 1, 80, 'If you have foreign bank accounts exceeding $10,000 at any point during the year, you may need to file an FBAR (FinCEN Form 114).' ),
			array( 'crypto', 'Bought, sold, or traded cryptocurrency', 250, 'flat', null, 1, 1, 90, 'Cryptocurrency transactions (buying, selling, trading) are taxable and must be reported on your return. Trading more than $100K may require a custom quote.' ),
			array( 'tuition', 'Paid college tuition (1098-T)', 25, 'flat', null, 0, 1, 100, 'You should receive a 1098-T form from your educational institution showing tuition paid.' ),
			array( 'childcare', 'Paid for childcare or dependent care', 25, 'flat', null, 0, 1, 110, 'Child and dependent care expenses may qualify for a tax credit. You will need the provider\'s name and tax ID.' ),
			array( 'hsa', 'Has an HSA', 25, 'qty_times_fee', null, 0, 1, 120, 'Health Savings Account contributions and distributions are reported on Form 8889.' ),
			array( 'home_sale', 'Sold any home during the year (1099-S)', 150, 'qty_times_fee', null, 0, 1, 130, 'If you sold a home, you should receive a 1099-S form. There may be capital gains implications.' ),
			array( 'retirement_distributions', 'Retirement Distributions (401K, IRA, ROTH IRA etc.)', 25, 'hardcoded', 100, 0, 1, 140, 'Distributions from retirement accounts like 401(k)s, IRAs, and Roth IRAs are taxable.' ),
			array( 'meetings', 'Meetings (end of year recap, tax return review, misc.)', 250, 'qty_times_fee', null, 0, 0, 150, 'Internal use only.' ),
		);

		foreach ( $individual_items as $item ) {
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
					'is_active'               => $item[6],
					'sort_order'              => $item[7],
					'tooltip'                 => $item[8],
				)
			);
		}

		$business_items = array(
			array( 'extra_k1s', 'Multiple partners/owners (extra K-1s to issue)', 25, 'qty_times_fee', null, 0, 1, 10, 'Each additional partner or owner requires a separate K-1 form to be issued.' ),
			array( 'multi_state', 'Business operates in more than one state', 250, 'qty_times_fee', null, 0, 1, 20, 'If your business has income or activities in states other than your home state, additional state filings may be required.' ),
			array( 'depreciation_schedule', 'Need a fixed asset / depreciation schedule built or maintained', 250, 'qty_times_fee', null, 0, 1, 30, 'A depreciation schedule tracks the cost of business assets over time. Required if you have significant equipment, vehicles, or property.' ),
			array( 'foreign_partner', 'Has a foreign partner/owner', 350, 'qty_times_fee', null, 0, 1, 40, 'Foreign partner/owner interests require additional reporting and may have tax implications.' ),
			array( 'books_dont_match', "Books don't match tax records (book-to-tax adjustments)", 250, 'qty_times_fee', null, 0, 1, 50, 'If your bookkeeping does not align with your tax filings, additional work is needed to reconcile the differences.' ),
			array( 'excess_equipment', 'More than 25 pieces of equipment/fixed assets', 250, 'qty_times_fee', null, 0, 1, 60, 'Larger numbers of fixed assets require detailed depreciation calculations.' ),
			array( 'audit_support', 'Under IRS audit / needs audit support', 350, 'qty_times_fee', null, 0, 0, 70, 'Audit representation is not included in standard engagement.' ),
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
					'is_active'               => $item[6],
					'sort_order'              => $item[7],
					'tooltip'                 => $item[8],
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
}
