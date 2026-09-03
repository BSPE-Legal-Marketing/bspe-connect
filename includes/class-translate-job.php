<?php
/**
 * Chunked translation job state, kept in a transient between AJAX steps.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * A job is created by Translate_Controller::start(), advanced one batch at
 * a time by step(), and consumed by apply(). Nothing is written to any post
 * until apply(), so an abandoned or failed job leaves the site untouched.
 * Jobs are scoped to the admin user who created them and expire after an
 * hour.
 */
final class Translate_Job {

	public const TRANSIENT_PREFIX = 'bspe_connect_tjob_';
	public const TTL              = HOUR_IN_SECONDS;

	/** Max characters of source text per API request. */
	public const BATCH_CHARS = 12000;

	/** Max segments per API request, so short-string pages don't make one huge JSON. */
	public const BATCH_SEGMENTS = 60;

	/**
	 * @param array<string,string> $segments
	 * @param array<string,mixed>  $map
	 *
	 * @return array<string,mixed> the job, already saved
	 */
	public static function create( int $source_id, string $source_lang, string $target_lang, bool $overwrite, array $segments, array $map ): array {
		$job = [
			'id'          => wp_generate_password( 12, false, false ),
			'user'        => get_current_user_id(),
			'source_id'   => $source_id,
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
			'overwrite'   => $overwrite,
			'segments'    => $segments,
			'map'         => $map,
			'batches'     => self::build_batches( $segments, self::BATCH_CHARS ),
			'done'        => [],
			'next'        => 0,
			'usage'       => [ 'input' => 0, 'output' => 0 ],
			'created'     => time(),
		];
		self::save( $job );
		return $job;
	}

	/**
	 * Greedy batches of segment ids up to $limit source characters each.
	 * A single oversized segment gets its own batch.
	 *
	 * @param array<string,string> $segments
	 *
	 * @return array<int,string[]>
	 */
	public static function build_batches( array $segments, int $limit ): array {
		$batches = [];
		$current = [];
		$size    = 0;
		foreach ( $segments as $id => $text ) {
			$len = strlen( $text );
			if ( ! empty( $current ) && ( $size + $len > $limit || count( $current ) >= self::BATCH_SEGMENTS ) ) {
				$batches[] = $current;
				$current   = [];
				$size      = 0;
			}
			$current[] = (string) $id;
			$size     += $len;
		}
		if ( ! empty( $current ) ) {
			$batches[] = $current;
		}
		return $batches;
	}

	/**
	 * @return array<string,mixed>|null null when missing, expired, or another user's
	 */
	public static function load( string $id ): ?array {
		$id = preg_replace( '/[^A-Za-z0-9]/', '', $id );
		if ( '' === $id ) {
			return null;
		}
		$job = get_transient( self::TRANSIENT_PREFIX . $id );
		if ( ! is_array( $job ) || (int) ( $job['user'] ?? 0 ) !== get_current_user_id() ) {
			return null;
		}
		return $job;
	}

	/** @param array<string,mixed> $job */
	public static function save( array $job ): void {
		set_transient( self::TRANSIENT_PREFIX . $job['id'], $job, self::TTL );
	}

	public static function delete( string $id ): void {
		delete_transient( self::TRANSIENT_PREFIX . preg_replace( '/[^A-Za-z0-9]/', '', $id ) );
	}

	/** @param array<string,mixed> $job */
	public static function total_chars( array $job ): int {
		$n = 0;
		foreach ( (array) $job['segments'] as $t ) {
			$n += strlen( (string) $t );
		}
		return $n;
	}

	/** @param array<string,mixed> $job */
	public static function done_chars( array $job ): int {
		$n = 0;
		foreach ( array_keys( (array) $job['done'] ) as $id ) {
			$n += strlen( (string) ( $job['segments'][ $id ] ?? '' ) );
		}
		return $n;
	}
}
