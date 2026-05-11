<?php
/**
 * Deactivation hook handler.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation is intentionally a no-op for v1: we keep settings, tables,
 * and submission history intact so re-activating the plugin restores the
 * client's previous configuration. Full teardown only happens on uninstall.
 */
final class Deactivator {

	public static function deactivate(): void {
		// Clear the daily prune cron so it doesn't accumulate stale
		// schedules across activate/deactivate cycles. The cron will be
		// rescheduled automatically on the next plugins_loaded after
		// reactivation (see Plugin::boot).
		$timestamp = wp_next_scheduled( Plugin::CRON_PRUNE_EVENTS );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, Plugin::CRON_PRUNE_EVENTS );
		}
		wp_clear_scheduled_hook( Plugin::CRON_PRUNE_EVENTS );
	}
}
