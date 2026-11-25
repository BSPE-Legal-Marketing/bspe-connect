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
	 * Delete submission rows whose submitted_at is older than $days. A
	 * $days value of 0 (or less) means "keep forever" — the caller skips
	 * pruning entirely in that case. Returns the number of rows removed.
	 *
	 * Sent emails are NOT touched — they live in the recipient's inbox
	 * after wp_mail handed them to the SMTP server. This only trims the
	 * historical record kept inside WordPress.
	 */
	/**
	 * Hard-delete submissions by row ID. Returns the number of rows that
	 * actually went away (which may be less than count($ids) if some IDs
	 * were already gone or never existed). Bounded to MAX_DELETE_BATCH so
	 * a malicious POST can't issue an unbounded DELETE.
	 *
	 * @param int[] $ids
	 */
	public const MAX_DELETE_BATCH = 1000;

	public static function delete_by_ids( array $ids ): int {
		$clean = [];
		foreach ( $ids as $id ) {
			$n = (int) $id;
			if ( $n > 0 ) {
				$clean[] = $n;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		if ( empty( $clean ) ) {
			return 0;
		}
		if ( count( $clean ) > self::MAX_DELETE_BATCH ) {
			$clean = array_slice( $clean, 0, self::MAX_DELETE_BATCH );
		}

		global $wpdb;
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $clean ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders})",
				$clean
			)
		);
		return $deleted;
	}

	/**
	 * Hard-delete submissions whose row matches the given WHERE clause and
	 * args (produced by Submissions_Controller::build_where_clause). The
	 * controller passes the same filter state the admin saw in the list,
	 * so "delete all matching" is scoped to whatever date / source / status
	 * filters were active.
	 *
	 * @param string             $where_sql
	 * @param array<int,mixed>   $where_args
	 */
	public static function delete_by_where( string $where_sql, array $where_args ): int {
		global $wpdb;
		$table = self::table();
		$sql   = "DELETE FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = empty( $where_args )
			? (int) $wpdb->query( $sql )
			: (int) $wpdb->query( $wpdb->prepare( $sql, $where_args ) );
		return $deleted;
	}

	public static function prune_old( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
		$table  = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE submitted_at < %s", $cutoff )
		);
		return $deleted;
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
			'fields_json'   => '' !== (string) ( $data['fields_json'] ?? '' ) ? (string) $data['fields_json'] : null,
			'page_url'      => substr( (string) ( $data['page_url']      ?? '' ), 0, 500 ),
			'user_agent'    => substr( (string) ( $data['user_agent']    ?? '' ), 0, 500 ),
			'ip_hash'       => substr( (string) ( $data['ip_hash']       ?? '' ), 0, 64 ),
			'mail_status'   => substr( (string) ( $data['mail_status']   ?? 'pending' ), 0, 20 ),
		];

		// fields_json is only named when there is something to store, so the
		// standard form never depends on the 1.2.0 column existing.
		if ( null === $row['fields_json'] ) {
			unset( $row['fields_json'] );
		}

		$ok = $wpdb->insert( self::table(), $row, self::formats( $row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $ok && isset( $row['fields_json'] ) ) {
			// The column may be missing (a schema migration that could not
			// ALTER the table). Keep the lead: store the answers as text in
			// the message instead of dropping the row.
			Logger::log( 'error', 'Submission insert with fields_json failed — retrying without the column', [ 'db_error' => (string) $wpdb->last_error ] );
			$answers = Custom_Form::summary( self::decode_fields( $row['fields_json'] ) );
			unset( $row['fields_json'] );
			$row['message'] = trim( $row['message'] . "\n\n" . $answers );
			$ok = $wpdb->insert( self::table(), $row, self::formats( $row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * wpdb formats for a row: NULL values get NULL, everything else %s.
	 *
	 * @param array<string,mixed> $row
	 * @return array<int,string|null>
	 */
	private static function formats( array $row ): array {
		$out = [];
		foreach ( $row as $value ) {
			$out[] = null === $value ? null : '%s';
		}
		return $out;
	}

	/**
	 * True once the 1.2.0 fields_json column exists.
	 */
	public static function has_fields_column(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'fields_json' ) );
		return 'fields_json' === $col;
	}

	/**
	 * Custom-form answers as stored in fields_json: an ordered list of
	 * {key, label, value, type}. Labels are stored with the value so an old
	 * lead still reads correctly after the form is edited.
	 *
	 * @param array<int,array{key:string,label:string,value:string,type:string}> $rows
	 */
	public static function encode_fields( array $rows ): string {
		$clean = [];
		foreach ( $rows as $row ) {
			$clean[] = [
				'key'   => (string) ( $row['key'] ?? '' ),
				'label' => (string) ( $row['label'] ?? '' ),
				'value' => (string) ( $row['value'] ?? '' ),
				'type'  => (string) ( $row['type'] ?? 'text' ),
			];
		}
		$json = wp_json_encode( $clean );
		return false === $json ? '' : $json;
	}

	/**
	 * @return array<int,array{key:string,label:string,value:string,type:string}>
	 */
	public static function decode_fields( ?string $json ): array {
		if ( null === $json || '' === $json ) {
			return [];
		}
		$rows = json_decode( $json, true );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$out = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = [
					'key'   => (string) ( $row['key'] ?? '' ),
					'label' => (string) ( $row['label'] ?? '' ),
					'value' => (string) ( $row['value'] ?? '' ),
					'type'  => (string) ( $row['type'] ?? 'text' ),
				];
			}
		}
		return $out;
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
