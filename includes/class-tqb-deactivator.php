<?php
/**
 * Fired during plugin deactivation.
 *
 * Task 4 tables (question_sets, question_set_items) are dropped to ensure
 * clean state on re-activation. Original tables (submissions, line_items, rate_bands)
 * are preserved to avoid losing submission data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Deactivator {

	public static function deactivate() {
		global $wpdb;

		// Clear scheduled cron jobs
		wp_clear_scheduled_hook( 'tqb_send_abandoned_emails' );
		wp_clear_scheduled_hook( 'tqb_retry_hubspot_syncs' );
		wp_clear_scheduled_hook( 'tqb_notify_hubspot_failures' );

		// Drop Task 4 tables for clean re-activation
		self::drop_question_sets_tables();
	}

	/**
	 * Drops the Task 4 question sets tables.
	 * Called on deactivation to ensure clean slate on re-activation.
	 * Original tables (submissions, line_items, rate_bands) are preserved.
	 */
	private static function drop_question_sets_tables() {
		global $wpdb;

		$sets_table = $wpdb->prefix . TQB_TABLE_QUESTION_SETS;
		$items_table = $wpdb->prefix . TQB_TABLE_QUESTION_SET_ITEMS;

		// Drop in order (items first, then sets)
		$wpdb->query( "DROP TABLE IF EXISTS {$items_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$sets_table}" );
	}
}

