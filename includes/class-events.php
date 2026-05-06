<?php
/**
 * Analytics events table — write + read.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes wp_bspe_connect_events. Only Rest (writes) and the
 * Analytics_Controller (reads) should call this class.
 */
final class Events {

	/** Allowed event types — duplicated client-side and validated server-side. */
	public const TYPES = [
		'bar_shown',
		'bubble_shown',
		'bubble_dismissed',
		'connect_click',
		'call_click',
		'text_click',
		'email_click',
		'form_open',
		'form_submit',
		'form_success',
		'form_error',
	];

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bspe_connect_events';
	}

	public static function is_allowed_type( string $type ): bool {
		return in_array( $type, self::TYPES, true );
	}

	/**
	 * Insert one event row. Returns the row ID or 0 on failure.
	 *
	 * @param array{event_type:string,occurred_at?:string,page_url?:string,session_id?:string} $data
	 */
	public static function insert( array $data ): int {
		if ( ! self::is_allowed_type( (string) ( $data['event_type'] ?? '' ) ) ) {
			return 0;
		}

		global $wpdb;

		$row = [
			'event_type'  => substr( (string) $data['event_type'], 0, 50 ),
			'occurred_at' => $data['occurred_at'] ?? current_time( 'mysql' ),
			'page_url'    => substr( (string) ( $data['page_url']   ?? '' ), 0, 500 ),
			'session_id'  => substr( (string) ( $data['session_id'] ?? '' ), 0, 64 ),
		];

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			$row,
			[ '%s', '%s', '%s', '%s' ]
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Count occurrences of each event type within the last N days.
	 *
	 * @return array<string, int> Map of event_type => count.
	 */
	public static function counts_by_type( int $days ): array {
		global $wpdb;
		$days  = max( 1, $days );
		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, COUNT(*) AS c FROM {$table} WHERE occurred_at >= %s GROUP BY event_type",
				gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) )
			),
			ARRAY_A
		);

		$out = array_fill_keys( self::TYPES, 0 );
		foreach ( (array) $rows as $row ) {
			$type = (string) ( $row['event_type'] ?? '' );
			if ( isset( $out[ $type ] ) ) {
				$out[ $type ] = (int) $row['c'];
			}
		}
		return $out;
	}

	/**
	 * Distinct-session count where any of the given event types were seen
	 * in the last N days. Used for funnel stages so a single visitor
	 * counts once per stage.
	 *
	 * @param string[] $types
	 */
	public static function unique_sessions_for_types( array $types, int $days ): int {
		if ( empty( $types ) ) {
			return 0;
		}
		global $wpdb;
		$days = max( 1, $days );

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( $types, [ gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) ) ] );

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE event_type IN ({$placeholders}) AND occurred_at >= %s AND session_id <> ''",
				$args
			)
		);
		return $count;
	}

	/**
	 * Top N page_urls by occurrence of a given event_type.
	 *
	 * @return array<int, array{page_url:string, count:int}>
	 */
	public static function top_pages( string $event_type, int $days, int $limit = 10 ): array {
		if ( ! self::is_allowed_type( $event_type ) ) {
			return [];
		}
		global $wpdb;
		$days  = max( 1, $days );
		$limit = max( 1, min( 50, $limit ) );

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, COUNT(*) AS c FROM {$table} WHERE event_type = %s AND occurred_at >= %s AND page_url <> '' GROUP BY page_url ORDER BY c DESC LIMIT %d",
				$event_type,
				gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) ),
				$limit
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = [
				'page_url' => (string) ( $row['page_url'] ?? '' ),
				'count'    => (int) ( $row['c'] ?? 0 ),
			];
		}
		return $out;
	}

	/**
	 * Best-effort daily-bucket histogram for an event type. Returns one row
	 * per day from oldest to newest within the window, with 0 fills for
	 * empty days (handy for sparkline rendering).
	 *
	 * @return array<int, array{date:string, count:int}>
	 */
	public static function daily_histogram( string $event_type, int $days ): array {
		if ( ! self::is_allowed_type( $event_type ) ) {
			return [];
		}
		global $wpdb;
		$days  = max( 1, $days );
		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(occurred_at) AS d, COUNT(*) AS c FROM {$table} WHERE event_type = %s AND occurred_at >= %s GROUP BY DATE(occurred_at)",
				$event_type,
				gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) )
			),
			ARRAY_A
		);

		$by_day = [];
		foreach ( (array) $rows as $row ) {
			$by_day[ (string) $row['d'] ] = (int) $row['c'];
		}

		$out = [];
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$out[] = [
				'date'  => $day,
				'count' => $by_day[ $day ] ?? 0,
			];
		}
		return $out;
	}
}
