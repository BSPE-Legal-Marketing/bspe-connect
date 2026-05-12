<?php
/**
 * Block WP's REST users endpoint for anonymous visitors.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Closes the WP-default information-disclosure vector at
 * /wp-json/wp/v2/users and /wp-json/wp/v2/users/<id>, which lets
 * any anonymous visitor enumerate usernames on a stock WordPress
 * install. Authenticated requests (admins / editors using wp-admin
 * JS) pass through untouched.
 *
 * Toggle: utilities.hide_users_rest in plugin settings (default ON).
 */
final class Hide_Users_Rest {

	public static function init(): void {
		if ( ! (bool) Settings::get( 'utilities.hide_users_rest', true ) ) {
			return;
		}
		add_filter( 'rest_endpoints', [ self::class, 'filter_endpoints' ] );
	}

	/**
	 * @param array<string,mixed> $endpoints
	 * @return array<string,mixed>
	 */
	public static function filter_endpoints( array $endpoints ): array {
		// Only hide from anonymous visitors. Logged-in users (admins,
		// editors, contributors) still get the endpoint — wp-admin's
		// own JS depends on it.
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		return $endpoints;
	}
}
