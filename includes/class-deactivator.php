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
		// Clear our daily prune crons so they don't accumulate stale
		// schedules across activate/deactivate cycles. The crons will be
		// rescheduled automatically on the next plugins_loaded after
		// reactivation (see Plugin::boot).
		foreach ( [ Plugin::CRON_PRUNE_EVENTS, Plugin::CRON_PRUNE_SUBMISSIONS, Licensing::CRON_HOOK ] as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			wp_clear_scheduled_hook( $hook );
		}
	}
}
