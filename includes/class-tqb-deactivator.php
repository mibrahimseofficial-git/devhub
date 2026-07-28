<?php
/**
 * Fired during plugin deactivation.
 *
 * IMPORTANT: This intentionally does NOT drop any database tables.
 * Deactivating a plugin is common during troubleshooting/updates — we don't
 * want to lose submission data or pricing config just because the plugin
 * was toggled off temporarily. Table cleanup only happens via a separate,
 * explicit "uninstall" flow (not yet built — would require an uninstall.php
 * with a clear confirmation step, since it's destructive).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Deactivator {

	public static function deactivate() {
		// Clear scheduled cron jobs
		wp_clear_scheduled_hook( 'tqb_send_abandoned_emails' );
		wp_clear_scheduled_hook( 'tqb_retry_hubspot_syncs' );
		wp_clear_scheduled_hook( 'tqb_notify_hubspot_failures' );
	}
}
