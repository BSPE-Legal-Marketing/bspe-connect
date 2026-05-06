<?php
/**
 * Activation hook handler.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Creates database tables and seeds default settings on first activation.
 * Subsequent activations re-run dbDelta (idempotent) but never overwrite
 * existing settings.
 */
final class Activator {

	public static function activate(): void {
		self::create_tables();
		self::seed_defaults();
		update_option( Settings::DB_VERSION_KEY, Settings::DB_VERSION );
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$submissions     = $wpdb->prefix . 'bspe_connect_submissions';
		$events          = $wpdb->prefix . 'bspe_connect_events';

		$submissions_sql = "CREATE TABLE {$submissions} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submitted_at DATETIME NOT NULL,
			source_button VARCHAR(20) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			phone VARCHAR(20) NOT NULL DEFAULT '',
			email VARCHAR(255) NOT NULL DEFAULT '',
			message TEXT NULL,
			contact_pref VARCHAR(20) NULL,
			page_url VARCHAR(500) NOT NULL DEFAULT '',
			user_agent VARCHAR(500) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			mail_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY  (id),
			KEY submitted_at (submitted_at),
			KEY source_button (source_button)
		) {$charset_collate};";

		$events_sql = "CREATE TABLE {$events} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(50) NOT NULL DEFAULT '',
			occurred_at DATETIME NOT NULL,
			page_url VARCHAR(500) NOT NULL DEFAULT '',
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_type_occurred_at (event_type, occurred_at),
			KEY occurred_at (occurred_at)
		) {$charset_collate};";

		dbDelta( $submissions_sql );
		dbDelta( $events_sql );
	}

	private static function seed_defaults(): void {
		$existing = get_option( Settings::OPTION_KEY, null );
		if ( null === $existing || ! is_array( $existing ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', 'yes' );
		}
	}
}
