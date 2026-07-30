<?php
/**
 * Plugin Name: Tavola Quote Builder
 * Description: Self-service pricing questionnaire for Individual and Business tax return quotes. Generates instant proposals or routes out-of-range submissions to a custom-quote path.
 * Version: 0.5.1
 * Author: Sabeeh
 * Text Domain: tavola-quote-builder
 *
 * ARCHITECTURE NOTE: See PROJECT_SPEC.md in the project root for full pricing
 * logic, business rules, and open questions. See PROGRESS_LOG.md for build status.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants
 */
define( 'TQB_VERSION', '0.3.0' );
define( 'TQB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TQB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TQB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Table names (without $wpdb->prefix — resolved at runtime where used)
define( 'TQB_TABLE_SUBMISSIONS', 'tqb_submissions' );
define( 'TQB_TABLE_LINE_ITEMS', 'tqb_line_items' );
define( 'TQB_TABLE_RATE_BANDS', 'tqb_rate_bands' );

/**
 * Returns a cache-busting version string for a plugin asset, based on the
 * file's last-modified time rather than the static TQB_VERSION constant.
 *
 * WHY: enqueuing with a hardcoded version (e.g. TQB_VERSION) means browsers
 * and caching plugins/CDNs keep serving the OLD file after every code
 * update, since the URL never changes — this was confirmed as the actual
 * root cause of a styling fix appearing not to work on the live site
 * (the browser was still loading the pre-fix CSS). Using filemtime() means
 * every saved change automatically gets a new version string, with no risk
 * of forgetting to bump a version number by hand.
 *
 * @param string $relative_path Path relative to the plugin root, e.g. 'public/css/tqb-public.css'
 * @return string
 */
function tqb_asset_version( $relative_path ) {
	$full_path = TQB_PLUGIN_DIR . ltrim( $relative_path, '/' );
	return file_exists( $full_path ) ? (string) filemtime( $full_path ) : TQB_VERSION;
}

/**
 * Activation / Deactivation hooks
 * (Registered here at the top level — WordPress requires this, cannot be
 * inside a class method reference to a not-yet-loaded file in some setups.)
 */
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-activator.php';
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-deactivator.php';

register_activation_hook( __FILE__, array( 'TQB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TQB_Deactivator', 'deactivate' ) );

/**
 * Core includes
 * NOTE: admin/public/rules-engine classes are stubbed here for Phase 1.
 * They get filled in during Phase 2 (rules engine), Phase 3 (admin dashboard),
 * Phase 4 (front-end shortcode) — see PROGRESS_LOG.md for status.
 */
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-db.php';
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-pricing-engine.php';
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-quote-handler.php';
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-email.php';
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-hubspot.php';
require_once TQB_PLUGIN_DIR . 'includes/class-tqb-public.php';

if ( is_admin() ) {
	require_once TQB_PLUGIN_DIR . 'includes/class-tqb-admin.php';
}

/**
 * Begins execution of the plugin.
 * Kept minimal for Phase 1 — just confirms the plugin loads and tables exist.
 * Admin UI and front-end shortcode registration will be added in later phases.
 */
function tqb_run() {
	new TQB_Public();

	if ( is_admin() ) {
		new TQB_Admin();
	}
}
add_action( 'plugins_loaded', 'tqb_run' );

/**
 * Run upgrades when plugin is loaded
 */
function tqb_check_upgrade() {
	$saved_version = get_option( 'tqb_db_version', '0' );
	if ( version_compare( $saved_version, TQB_VERSION, '<' ) ) {
		TQB_Activator::upgrade();
	}
}
add_action( 'init', 'tqb_check_upgrade' );

// Register cron schedule for abandoned quote emails
add_filter( 'cron_schedules', 'tqb_add_cron_interval' );
function tqb_add_cron_interval( $schedules ) {
	$schedules['tqb_hourly'] = array(
		'interval' => HOUR_IN_SECONDS,
		'display'  => 'Every Hour (TQB)',
	);
	return $schedules;
}

// Cron functions
function tqb_clear_cron() {
	wp_clear_scheduled_hook( 'tqb_send_abandoned_emails' );
	wp_clear_scheduled_hook( 'tqb_retry_hubspot_syncs' );
}

// Cron job to send abandoned quote emails
add_action( 'tqb_send_abandoned_emails', 'tqb_send_abandoned_quote_emails' );
function tqb_send_abandoned_quote_emails() {
	if ( ! get_option( 'tqb_enable_abandoned_emails', '1' ) ) {
		return;
	}
	TQB_Email::send_abandoned_quote_emails();
}

// Cron job to retry failed HubSpot syncs (every hour)
add_action( 'tqb_retry_hubspot_syncs', 'tqb_retry_failed_hubspot_syncs' );
function tqb_retry_failed_hubspot_syncs() {
	TQB_Hubspot::retry_failed_syncs();
}

// Daily admin notification for HubSpot failures
add_action( 'tqb_notify_hubspot_failures', 'tqb_notify_hubspot_failures' );
function tqb_notify_hubspot_failures() {
	TQB_Hubspot::notify_admin_of_failures();
}
