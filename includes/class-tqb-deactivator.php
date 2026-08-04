<?php
/**
 * Fired during plugin deactivation.
 *
 * Note: as of v1.4 the question_sets / question_set_items tables (and the
 * parallel questions system that used them) were removed entirely as dead
 * code — see TQB_Activator::cleanup_deprecated_schema(). This file no longer
 * needs to drop them on every deactivation; the one-time migration handles it.
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

