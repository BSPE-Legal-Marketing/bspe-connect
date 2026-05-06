<?php
/**
 * REST API — analytics event ingestion.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `bspe-connect/v1/event` endpoint. Public, rate-limited per
 * hashed IP at 60 events / minute. Always returns `{ ok: true }` so we
 * don't leak rate-limit state to bots — silently dropped events look
 * identical to accepted events.
 */
final class Rest {

	public const NAMESPACE        = 'bspe-connect/v1';
	public const ROUTE            = '/event';
	public const RATE_LIMIT_PER   = 60;       // events per minute per hashed IP
	public const RATE_LIMIT_TTL   = 60;       // seconds
	public const PAGE_URL_MAX     = 500;
	public const SESSION_ID_MAX   = 64;

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle_event' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'event_type' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'page_url'   => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					],
					'session_id' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_event( $request ) {
		$response = new \WP_REST_Response( [ 'ok' => true ], 200 );

		$event_type = (string) $request->get_param( 'event_type' );
		if ( '' === $event_type || ! Events::is_allowed_type( $event_type ) ) {
			return $response;
		}

		// Rate limit per hashed IP — silent drop on excess.
		$ip = Submissions::client_ip();
		if ( '' !== $ip ) {
			$hash = Submissions::hash_ip( $ip );
			$key  = 'bspe_connect_evt_rl_' . $hash;
			$bucket = (int) get_transient( $key );
			if ( $bucket >= self::RATE_LIMIT_PER ) {
				return $response;
			}
			set_transient( $key, $bucket + 1, self::RATE_LIMIT_TTL );
		}

		$page_url   = (string) $request->get_param( 'page_url' );
		$session_id = (string) $request->get_param( 'session_id' );

		// Discard URLs not in the same site host — defends against arbitrary referer logging.
		if ( '' !== $page_url ) {
			$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
			$page_host = wp_parse_url( $page_url,    PHP_URL_HOST );
			if ( $home_host && $page_host && strtolower( (string) $home_host ) !== strtolower( (string) $page_host ) ) {
				$page_url = '';
			}
		}

		// Redact privacy-sensitive query strings before persistence.
		if ( '' !== $page_url ) {
			$page_url = self::redact_url( $page_url );
		}

		Events::insert( [
			'event_type'  => $event_type,
			'occurred_at' => current_time( 'mysql' ),
			'page_url'    => substr( $page_url,   0, self::PAGE_URL_MAX ),
			'session_id'  => substr( $session_id, 0, self::SESSION_ID_MAX ),
		] );

		return $response;
	}

	/**
	 * Strip known sensitive query keys from a URL before storing it.
	 * Keys can be extended by site code via the `bspe_connect_redact_query_keys`
	 * filter; the URL itself can be rewritten via `bspe_connect_redact_url`.
	 */
	private static function redact_url( string $url ): string {
		$default_keys = [
			'token',
			'access_token',
			'auth',
			'auth_token',
			'session',
			'session_id',
			'sid',
			'pwd',
			'password',
			'email',
			'user_email',
			'user_id',
			'uid',
			'api_key',
			'apikey',
			'key',
			'secret',
			'reset_key',
			'magic_link',
		];

		/**
		 * @param string[] $default_keys
		 */
		$keys = (array) apply_filters( 'bspe_connect_redact_query_keys', $default_keys );

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			/**
			 * @param string $url
			 */
			return (string) apply_filters( 'bspe_connect_redact_url', $url );
		}

		$query = [];
		wp_parse_str( (string) $parts['query'], $query );

		$redacted = false;
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}
			if ( array_key_exists( $key, $query ) ) {
				$query[ $key ] = '[redacted]';
				$redacted = true;
			}
		}

		if ( $redacted ) {
			$parts['query'] = http_build_query( $query );
			$url            = self::reassemble_url( $parts );
		}

		return (string) apply_filters( 'bspe_connect_redact_url', $url );
	}

	/**
	 * Reassemble a parsed URL array back into a string. Used after redacting
	 * the query component.
	 *
	 * @param array<string,mixed> $parts
	 */
	private static function reassemble_url( array $parts ): string {
		$scheme   = isset( $parts['scheme'] )   ? $parts['scheme'] . '://' : '';
		$host     = isset( $parts['host'] )     ? $parts['host'] : '';
		$port     = isset( $parts['port'] )     ? ':' . $parts['port'] : '';
		$user     = isset( $parts['user'] )     ? $parts['user'] : '';
		$pass     = isset( $parts['pass'] )     ? ':' . $parts['pass']  : '';
		$auth     = ( '' !== $user || '' !== $pass ) ? $user . $pass . '@' : '';
		$path     = isset( $parts['path'] )     ? $parts['path'] : '';
		$query    = isset( $parts['query'] )    && '' !== $parts['query'] ? '?' . $parts['query']    : '';
		$fragment = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . $parts['fragment'] : '';
		return $scheme . $auth . $host . $port . $path . $query . $fragment;
	}
}
