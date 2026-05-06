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

		Events::insert( [
			'event_type'  => $event_type,
			'occurred_at' => current_time( 'mysql' ),
			'page_url'    => substr( $page_url,   0, self::PAGE_URL_MAX ),
			'session_id'  => substr( $session_id, 0, self::SESSION_ID_MAX ),
		] );

		return $response;
	}
}
