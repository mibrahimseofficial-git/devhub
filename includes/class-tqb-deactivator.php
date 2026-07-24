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
		// Intentionally left minimal. Flush rewrite rules here later if the
		// front-end shortcode (Phase 4) ever registers custom rewrite rules.
	}
}
