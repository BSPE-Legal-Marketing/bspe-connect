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
		// Reserved for future cleanup (e.g., flushing rewrite rules in Phase 5).
	}
}
