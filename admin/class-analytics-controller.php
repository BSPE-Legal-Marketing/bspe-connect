<?php
/**
 * Analytics dashboard controller — window resolution + summary queries.
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

use BSPE\Connect\Events;

defined( 'ABSPATH' ) || exit;

final class Analytics_Controller {

	public const ALLOWED_WINDOWS = [ 7, 30, 60, 90 ];
	public const DEFAULT_WINDOW  = 30;

	public static function active_window(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation
		$candidate = isset( $_GET['window'] ) ? (int) $_GET['window'] : self::DEFAULT_WINDOW;
		return in_array( $candidate, self::ALLOWED_WINDOWS, true ) ? $candidate : self::DEFAULT_WINDOW;
	}

	/**
	 * @param int $days
	 *
	 * @return array{
	 *   counts: array<string,int>,
	 *   funnel: array<int, array{key:string, label:string, count:int, drop:?float}>,
	 *   top_submission_pages: array<int, array{page_url:string, count:int}>,
	 *   top_bar_pages: array<int, array{page_url:string, count:int}>,
	 * }
	 */
	public static function summary( int $days ): array {
		$counts = Events::counts_by_type( $days );

		$funnel = self::build_funnel( $days );

		$top_submission_pages = Events::top_pages( 'form_success', $days, 10 );
		$top_bar_pages        = Events::top_pages( 'bar_shown', $days, 10 );

		return [
			'counts'               => $counts,
			'funnel'               => $funnel,
			'top_submission_pages' => $top_submission_pages,
			'top_bar_pages'        => $top_bar_pages,
		];
	}

	/**
	 * Build a 5-stage funnel using DISTINCT session counts so a single
	 * visitor only counts once per stage. Falls back to raw counts when
	 * sessions weren't recorded (e.g. older events from before Phase 5).
	 *
	 * @return array<int, array{key:string, label:string, count:int, drop:?float}>
	 */
	private static function build_funnel( int $days ): array {
		$stages = [
			[ 'key' => 'shown',   'label' => __( 'Bar shown', 'bspe-connect' ),     'types' => [ 'bar_shown' ] ],
			[ 'key' => 'click',   'label' => __( 'Button click', 'bspe-connect' ),  'types' => [ 'connect_click', 'call_click', 'text_click', 'email_click' ] ],
			[ 'key' => 'open',    'label' => __( 'Form opened', 'bspe-connect' ),   'types' => [ 'form_open' ] ],
			[ 'key' => 'submit',  'label' => __( 'Form submitted', 'bspe-connect' ),'types' => [ 'form_submit' ] ],
			[ 'key' => 'success', 'label' => __( 'Form success', 'bspe-connect' ),  'types' => [ 'form_success' ] ],
		];

		$out      = [];
		$previous = null;

		$counts = Events::counts_by_type( $days );

		foreach ( $stages as $stage ) {
			$session_count = Events::unique_sessions_for_types( $stage['types'], $days );

			$fallback = 0;
			foreach ( $stage['types'] as $type ) {
				$fallback += (int) ( $counts[ $type ] ?? 0 );
			}

			// Prefer session-distinct count if any sessions were recorded.
			$count = $session_count > 0 ? $session_count : $fallback;

			$drop = null;
			if ( null !== $previous && $previous > 0 ) {
				$drop = $count / $previous;
			}

			$out[] = [
				'key'   => $stage['key'],
				'label' => $stage['label'],
				'count' => $count,
				'drop'  => $drop,
			];

			$previous = $count;
		}

		return $out;
	}

	/**
	 * Helper — produce a friendly display path for a URL ("/contact?utm=foo").
	 */
	public static function format_path( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return $url;
		}
		$path  = (string) ( $parts['path']  ?? '/' );
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		return $path . $query;
	}
}
