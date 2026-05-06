<?php
/**
 * Submissions database access.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the wp_bspe_connect_submissions table. Only the
 * Form Handler and the admin Submissions view should call this class.
 */
final class Submissions {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bspe_connect_submissions';
	}

	/**
	 * Insert a sanitized submission row. Returns the new row ID or 0 on failure.
	 *
	 * @param array<string,mixed> $data Already-sanitized fields.
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$row = [
			'submitted_at'  => $data['submitted_at']  ?? current_time( 'mysql' ),
			'source_button' => substr( (string) ( $data['source_button'] ?? '' ), 0, 20 ),
			'name'          => substr( (string) ( $data['name']          ?? '' ), 0, 255 ),
			'phone'         => substr( (string) ( $data['phone']         ?? '' ), 0, 20 ),
			'email'         => substr( (string) ( $data['email']         ?? '' ), 0, 255 ),
			'message'       => (string) ( $data['message']      ?? '' ),
			'contact_pref'  => $data['contact_pref']  !== '' && $data['contact_pref'] !== null ? substr( (string) $data['contact_pref'], 0, 20 ) : null,
			'page_url'      => substr( (string) ( $data['page_url']      ?? '' ), 0, 500 ),
			'user_agent'    => substr( (string) ( $data['user_agent']    ?? '' ), 0, 500 ),
			'ip_hash'       => substr( (string) ( $data['ip_hash']       ?? '' ), 0, 64 ),
			'mail_status'   => substr( (string) ( $data['mail_status']   ?? 'pending' ), 0, 20 ),
		];

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			$row,
			[
				'%s', '%s', '%s', '%s', '%s', '%s',
				null === $row['contact_pref'] ? null : '%s',
				'%s', '%s', '%s', '%s',
			]
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update mail_status for an existing row.
	 */
	public static function update_mail_status( int $id, string $status ): void {
		if ( $id <= 0 ) {
			return;
		}
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			[ 'mail_status' => substr( $status, 0, 20 ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Hash an IP address for safe storage. Never returns the raw value.
	 */
	public static function hash_ip( string $raw_ip ): string {
		$raw_ip = trim( $raw_ip );
		if ( '' === $raw_ip ) {
			return '';
		}
		// salt with site URL so a single hashed IP can't be cross-referenced across BSPE sites.
		$salt = (string) wp_salt( 'auth' );
		return hash( 'sha256', $raw_ip . '|' . $salt );
	}

	/**
	 * Best-effort client IP from REMOTE_ADDR. Trusted proxy headers honored
	 * only when the WP install has explicitly opted in via `bspe_connect_trust_proxy`.
	 */
	public static function client_ip(): string {
		$candidate = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$candidate = trim( $candidate );

		if ( apply_filters( 'bspe_connect_trust_proxy', false ) ) {
			$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : ''; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_X_FORWARDED_FOR__
			if ( '' !== $forwarded ) {
				$first    = trim( explode( ',', $forwarded )[0] );
				$candidate = '' !== $first ? $first : $candidate;
			}
		}

		return filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
	}
}
