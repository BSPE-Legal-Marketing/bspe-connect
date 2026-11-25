<?php
/**
 * Page-cache purge after a lead-form change.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * The form markup is printed into every page, so a page cache (NitroPack on
 * most BSPE sites, LiteSpeed on the VPS) keeps serving the old form after an
 * edit. The submission handler refuses a stale form with "please refresh"
 * (Custom_Form::signature), but the real fix is to drop the cached pages the
 * moment the form changes. Each integration is optional and guarded; a cache
 * plugin that throws never breaks the save.
 */
final class Cache_Purge {

	/**
	 * Purge every page cache we know how to reach.
	 *
	 * @return string[] Names of the caches that were asked to purge.
	 */
	public static function all( string $reason ): array {
		$done = [];

		$attempts = [
			'nitropack'      => static function () use ( $reason ): bool {
				if ( ! function_exists( 'nitropack_sdk_purge' ) ) {
					return false;
				}
				\nitropack_sdk_purge( null, null, 'BSPE Connect: ' . $reason );
				return true;
			},
			'litespeed'      => static function (): bool {
				if ( ! defined( 'LSCWP_V' ) && ! has_action( 'litespeed_purge_all' ) ) {
					return false;
				}
				do_action( 'litespeed_purge_all' );
				return true;
			},
			'wp-rocket'      => static function (): bool {
				if ( ! function_exists( 'rocket_clean_domain' ) ) {
					return false;
				}
				\rocket_clean_domain();
				return true;
			},
			'w3-total-cache' => static function (): bool {
				if ( ! function_exists( 'w3tc_flush_all' ) ) {
					return false;
				}
				\w3tc_flush_all();
				return true;
			},
			'wp-super-cache' => static function (): bool {
				if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
					return false;
				}
				\wp_cache_clear_cache();
				return true;
			},
			'siteground'     => static function (): bool {
				if ( ! function_exists( 'sg_cachepress_purge_cache' ) ) {
					return false;
				}
				\sg_cachepress_purge_cache();
				return true;
			},
		];

		foreach ( $attempts as $name => $attempt ) {
			try {
				if ( $attempt() ) {
					$done[] = $name;
				}
			} catch ( \Throwable $e ) {
				Logger::log( 'error', 'Page cache purge failed', [ 'cache' => $name, 'error' => $e->getMessage() ] );
			}
		}

		Logger::log( 'info', empty( $done ) ? 'Lead form changed — no page cache plugin found to purge' : 'Page cache purged after lead form change', [
			'caches' => $done,
			'reason' => $reason,
		] );

		return $done;
	}
}
