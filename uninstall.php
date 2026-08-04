<?php
/**
 * Uninstall handler for Tavola Quote Builder plugin.
 *
 * This file is run when a user deletes the plugin from WordPress Admin.
 * It checks the "delete_data_on_uninstall" setting and deletes all data
 * if the user has enabled this option.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user has enabled "Delete data on uninstall"
$delete_data = get_option( 'tqb_delete_data_on_uninstall', '0' );

if ( '1' !== $delete_data ) {
	// User chose to keep data, so just exit
	return;
}

// User wants to delete all data, proceed with deletion
global $wpdb;

// Define table names with prefix
$submissions_table      = $wpdb->prefix . 'tqb_submissions';
$line_items_table       = $wpdb->prefix . 'tqb_line_items';
$rate_bands_table       = $wpdb->prefix . 'tqb_rate_bands';
$question_sets_table    = $wpdb->prefix . 'tqb_question_sets';
$question_set_items_table = $wpdb->prefix . 'tqb_question_set_items';

// Drop all plugin tables
$wpdb->query( "DROP TABLE IF EXISTS {$question_set_items_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$question_sets_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$rate_bands_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$line_items_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$submissions_table}" );

// Delete all plugin options
delete_option( 'tqb_disclaimer_text' );
delete_option( 'tqb_scheduling_link' );
delete_option( 'tqb_team_notification_email' );
delete_option( 'tqb_hubspot_service_key' );
delete_option( 'tqb_hubspot_pipeline_id' );
delete_option( 'tqb_hubspot_stage_new' );
delete_option( 'tqb_hubspot_stage_custom' );
delete_option( 'tqb_enable_abandoned_emails' );
delete_option( 'tqb_reminder_email_hours' );
delete_option( 'tqb_followup_email_hours' );
delete_option( 'tqb_final_email_hours' );
delete_option( 'tqb_office_address' );
delete_option( 'tqb_delete_data_on_uninstall' );
delete_option( 'tqb_db_version' );

// Delete scheduled cron jobs
wp_clear_scheduled_hook( 'tqb_retry_hubspot_syncs' );
wp_clear_scheduled_hook( 'tqb_notify_hubspot_failures' );
wp_clear_scheduled_hook( 'tqb_cleanup_abandoned_quotes' );

// Log uninstall action
error_log( 'Tavola Quote Builder plugin uninstalled and all data deleted (user requested).' );
