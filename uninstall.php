<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all custom tables and options. Submission data is intentionally
 * destroyed because the plugin is being removed.
 *
 * @package BSPE\Connect
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [
	$wpdb->prefix . 'bspe_connect_submissions',
	$wpdb->prefix . 'bspe_connect_events',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

delete_option( 'bspe_connect_settings' );
delete_option( 'bspe_connect_db_version' );
delete_option( 'bspe_connect_log' );

// Clear scheduled cron jobs so WP doesn't try to fire callbacks into
// classes that no longer exist after uninstall.
wp_clear_scheduled_hook( 'bspe_connect_prune_events' );
wp_clear_scheduled_hook( 'bspe_connect_prune_submissions' );

// Clean any rate-limit / event-rate / global-rate transients that
// may be lingering.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bspe_connect_rl_%' OR option_name LIKE '_transient_timeout_bspe_connect_rl_%' OR option_name LIKE '_transient_bspe_connect_evt_rl_%' OR option_name LIKE '_transient_timeout_bspe_connect_evt_rl_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
