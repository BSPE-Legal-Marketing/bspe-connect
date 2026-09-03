<?php
/**
 * License enforcement.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the BSPE Connect Manager (licenses.bsplegalmarketing.com or
 * the Railway-provided URL) to activate the install, periodically
 * re-validate, and gracefully degrade when the server can't be
 * reached.
 *
 * Every server response is a signed RS256 JWT — verified locally with
 * the embedded public key before being trusted — so a malicious DNS
 * redirect or MITM can't forge an "active" response without the
 * private key that lives only in the manager's env vars.
 *
 * Public surface used by other classes:
 *
 *   Licensing::is_functional()    true when the bar should render +
 *                                 updates should run + form handler +
 *                                 analytics endpoint accept traffic
 *
 *   Licensing::state()            full current license state array
 *                                 (used by the License tab UI)
 *
 *   Licensing::activate($key)     called from the admin form
 *   Licensing::check()            called from the daily cron + by the
 *                                 admin's "Check now" button
 *   Licensing::deactivate()       called when admin opts to release
 *                                 the key for use elsewhere
 */
final class Licensing {

	public const OPTION_KEY = 'bspe_connect_license';
	public const CRON_HOOK  = 'bspe_connect_license_check';
	public const GRACE_DAYS = 7;

	/**
	 * License server base URL. Override via BSPE_CONNECT_LICENSE_URL
	 * wp-config constant if needed (e.g. development / staging).
	 */
	private const DEFAULT_SERVER_URL = 'https://bspe-connect-manager-production.up.railway.app';

	/**
	 * Expected `iss` claim in every signed response from the manager.
	 * Anything else is rejected — protects against keys signed by a
	 * different deployment somehow ending up here.
	 */
	private const EXPECTED_ISSUER = 'BSPE Legal Marketing';

	/**
	 * RSA public key for verifying signed responses. The corresponding
	 * private key lives only in the manager's LICENSE_JWT_PRIVATE_KEY
	 * env var on Railway. Rotating this requires shipping a new plugin
	 * release with the new public key.
	 */
	private const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzcTbs7xfDfp8NE343R5e
trRyXHpJZ2pPTPZ85gcvhcufDpxJp9ReNrECmmAjCcqfpluJb/bWFv0ZV2HZDt2a
u/9/qyP390sCitcfe1OC3KTtwipSg3ZQZ0w2YCJFszJhm4ucXMkZ9WVUU4CONlcN
hNEwAzBDpJqHDdMb/cKFAkvtu5TV+YPpM2PvmtUAtn4fIoBx94Wvi4Jd59SQ5yu5
67CVmJA3Flu0kFKaVkxZAbuLhrM4UuorX+bcdJ+48k1BqlTY/dlRHlqokXQkltqO
c3hl0UTpy9PdMPH/1d2g6goI4DXgF1P6iJbZp+Xsr3pzeXqGuole6cTZdD5meb5C
XQIDAQAB
-----END PUBLIC KEY-----";

	public static function init(): void {
		add_action( self::CRON_HOOK, [ self::class, 'cron_check' ] );

		// Periodic re-check, every 12 hours. The check method itself
		// is cheap (one HTTP call + DB write), and 12h matches WP's
		// own update-check cadence so the operational rhythm is the
		// same.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event(
				time() + HOUR_IN_SECONDS,
				'twicedaily',
				self::CRON_HOOK
			);
		}
	}

	public static function server_url(): string {
		if ( defined( 'BSPE_CONNECT_LICENSE_URL' ) && is_string( BSPE_CONNECT_LICENSE_URL ) ) {
			return rtrim( (string) BSPE_CONNECT_LICENSE_URL, '/' );
		}
		return rtrim( self::DEFAULT_SERVER_URL, '/' );
	}

	/**
	 * Read the current license state. Always returns a fully-shaped
	 * array so callers don't have to defensive-check every field.
	 *
	 * @return array{
	 *   key:string, status:string, domain:string,
	 *   last_check_at:int, last_success_at:int, last_error:string,
	 *   last_version:string,
	 * }
	 */
	public static function state(): array {
		$raw = get_option( self::OPTION_KEY, [] );
		$defaults = [
			'key'             => '',
			'status'          => 'unactivated',
			'domain'          => '',
			'last_check_at'   => 0,
			'last_success_at' => 0,
			'last_error'      => '',
			'last_version'    => '',
		];
		return array_merge( $defaults, is_array( $raw ) ? $raw : [] );
	}

	private static function save_state( array $state ): void {
		// Always autoload — license state is read on every page load
		// for the is_functional() gate, and skipping autoload would
		// add a DB query to every request.
		update_option( self::OPTION_KEY, $state, 'yes' );
	}

	/**
	 * The runtime gate. Frontend rendering, updater registration,
	 * REST analytics, and form handling all early-return when this
	 * returns false.
	 *
	 *   status === 'active' → always functional
	 *   status === 'active', server unreachable, within grace → still functional
	 *   anything else → not functional
	 */
	public static function is_functional(): bool {
		$state = self::state();
		if ( 'active' !== $state['status'] ) {
			return false;
		}
		// last_success_at == 0 means we've never confirmed with the
		// server. Treat as not functional — admin must explicitly
		// activate. (Shouldn't happen in normal flow since activate()
		// sets last_success_at on success.)
		if ( 0 === (int) $state['last_success_at'] ) {
			return false;
		}
		$grace_until = (int) $state['last_success_at'] + ( self::GRACE_DAYS * DAY_IN_SECONDS );
		return time() <= $grace_until;
	}

	/**
	 * True if we're currently coasting on cached state because the
	 * server isn't reachable. Used by the UI to show a "couldn't
	 * reach license server, retrying" notice without scaring the
	 * admin (the plugin is still working).
	 */
	public static function in_grace_period(): bool {
		$state = self::state();
		if ( 'active' !== $state['status'] ) {
			return false;
		}
		$success = (int) $state['last_success_at'];
		$check   = (int) $state['last_check_at'];
		if ( $success === 0 || $check === 0 ) {
			return false;
		}
		// In grace when the last check failed (check newer than
		// success) but the failure window is still under the cap.
		return $check > $success;
	}

	/**
	 * The host that gets sent to the license server. Strips the port
	 * and protocol; lowercased. The server applies its own normalization
	 * to drop the www/staging subdomain so all subdomains of one
	 * registrable domain share a single license.
	 */
	public static function current_domain(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return strtolower( trim( $host ) );
	}

	/**
	 * Submit a license key for first-time activation. Returns the new
	 * license state on success or an array with `error` on failure.
	 */
	public static function activate( string $key ): array {
		$key = trim( $key );
		if ( '' === $key ) {
			return [ 'error' => __( 'License key is required.', 'bspe-connect' ) ];
		}

		$domain = self::current_domain();
		$response = self::post( '/v1/activate', [
			'key'     => $key,
			'domain'  => $domain,
			'version' => BSPE_CONNECT_VERSION,
		] );

		if ( isset( $response['error'] ) ) {
			Logger::log( 'warn', 'License activation failed', [
				'reason' => $response['error'],
				'domain' => $domain,
			] );
			return $response;
		}

		$verified = self::verify_signed_payload( $response );
		if ( null === $verified ) {
			Logger::log( 'error', 'License activation rejected — signature verification failed', [
				'domain' => $domain,
			] );
			return [ 'error' => __( 'The license server response could not be verified. Please contact BSPE Legal Marketing.', 'bspe-connect' ) ];
		}

		$state = self::state();
		$state['key']             = $key;
		$state['status']          = (string) ( $verified['status'] ?? 'unknown' );
		$state['domain']          = (string) ( $verified['domain'] ?? $domain );
		$state['last_check_at']   = time();
		$state['last_success_at'] = time();
		$state['last_error']      = '';
		$state['last_version']    = BSPE_CONNECT_VERSION;
		self::save_state( $state );

		Logger::log( 'info', 'License activated', [
			'status' => $state['status'],
			'domain' => $state['domain'],
		] );

		return $state;
	}

	/**
	 * Re-check license status against the server. Updates state with
	 * either the fresh server response or a cached + last_error
	 * record when the server is unreachable.
	 */
	public static function check(): array {
		$state = self::state();
		if ( '' === $state['key'] ) {
			return $state;
		}

		$response = self::post( '/v1/check', [
			'key'     => $state['key'],
			'domain'  => self::current_domain(),
			'version' => BSPE_CONNECT_VERSION,
		] );

		$state['last_check_at'] = time();

		if ( isset( $response['error'] ) ) {
			$state['last_error'] = (string) $response['error'];
			self::save_state( $state );
			Logger::log( 'warn', 'License check failed (network)', [
				'reason' => $response['error'],
			] );
			return $state;
		}

		$verified = self::verify_signed_payload( $response );
		if ( null === $verified ) {
			$state['last_error'] = __( 'Response signature could not be verified.', 'bspe-connect' );
			self::save_state( $state );
			Logger::log( 'error', 'License check failed — signature verification failed' );
			return $state;
		}

		$new_status = (string) ( $verified['status'] ?? $state['status'] );
		$state['status']          = $new_status;
		$state['domain']          = (string) ( $verified['domain'] ?? $state['domain'] );
		$state['last_success_at'] = time();
		$state['last_error']      = '';
		$state['last_version']    = BSPE_CONNECT_VERSION;
		self::save_state( $state );

		Logger::log( 'info', 'License check completed', [
			'status' => $new_status,
		] );

		return $state;
	}

	/**
	 * Cron callback. Same as check() but with a guard so we don't
	 * spam the server if the install isn't activated yet.
	 */
	public static function cron_check(): void {
		$state = self::state();
		if ( '' === $state['key'] || 'unactivated' === $state['status'] ) {
			return;
		}
		self::check();
	}

	/**
	 * Notify the server this install is releasing the license. Frees
	 * the domain binding so the same key can be activated elsewhere
	 * by BSPE staff if needed.
	 */
	public static function deactivate(): bool {
		$state = self::state();
		if ( '' === $state['key'] ) {
			return false;
		}

		self::post( '/v1/deactivate', [
			'key'    => $state['key'],
			'domain' => self::current_domain(),
		] );

		// Clear state regardless of server response — admin wanted out.
		$cleared = [
			'key'             => '',
			'status'          => 'unactivated',
			'domain'          => '',
			'last_check_at'   => 0,
			'last_success_at' => 0,
			'last_error'      => '',
			'last_version'    => '',
		];
		self::save_state( $cleared );

		Logger::log( 'info', 'License locally deactivated' );
		return true;
	}

	/**
	 * Verify the signed JWT in a server response. Returns the decoded
	 * payload on success or null on any failure mode (bad signature,
	 * wrong issuer, expired, malformed). null means "do not trust the
	 * response."
	 *
	 * @param array<string,mixed> $response
	 *
	 * @return array<string,mixed>|null
	 */
	private static function verify_signed_payload( array $response ): ?array {
		$token = (string) ( $response['token'] ?? '' );
		if ( '' === $token ) {
			return null;
		}
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) {
			return null;
		}
		[ $header_b64, $payload_b64, $signature_b64 ] = $parts;

		$header_json = self::b64url_decode( $header_b64 );
		$payload_json = self::b64url_decode( $payload_b64 );
		$signature   = self::b64url_decode( $signature_b64 );
		if ( '' === $header_json || '' === $payload_json || '' === $signature ) {
			return null;
		}

		$header = json_decode( $header_json, true );
		if ( ! is_array( $header ) ) {
			return null;
		}
		// Only RS256 is accepted. Refuse "alg: none" attack and any
		// HMAC variants that would let an attacker forge with public
		// material.
		if ( ( $header['alg'] ?? '' ) !== 'RS256' ) {
			return null;
		}

		$signed_data = $header_b64 . '.' . $payload_b64;
		$key_resource = openssl_pkey_get_public( self::PUBLIC_KEY );
		if ( ! $key_resource ) {
			return null;
		}
		$ok = openssl_verify( $signed_data, $signature, $key_resource, OPENSSL_ALGO_SHA256 );
		if ( $ok !== 1 ) {
			return null;
		}

		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		// Issuer must match. Stops a token signed for a different
		// deployment from being accepted here.
		if ( ( $payload['iss'] ?? '' ) !== self::EXPECTED_ISSUER ) {
			return null;
		}

		// Expiry must be in the future. Server-issued tokens last 1h.
		$exp = (int) ( $payload['exp'] ?? 0 );
		if ( $exp > 0 && $exp < time() ) {
			return null;
		}

		return $payload;
	}

	/**
	 * POST helper for the license-server endpoints. Returns the
	 * decoded JSON response on success, or an array with an `error`
	 * key on any non-200 / parsing failure.
	 *
	 * @param array<string,mixed> $body
	 *
	 * @return array<string,mixed>
	 */
	private static function post( string $endpoint, array $body ): array {
		$url = self::server_url() . $endpoint;
		$response = wp_remote_post( $url, [
			'timeout'     => 15,
			'redirection' => 3,
			'headers'     => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'BSPE-Connect/' . BSPE_CONNECT_VERSION,
			],
			'body'        => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( ! is_array( $json ) ) {
			return [ 'error' => sprintf( /* translators: %d: http status */ __( 'Unexpected response from license server (HTTP %d).', 'bspe-connect' ), $code ) ];
		}

		if ( $code === 429 ) {
			return [ 'error' => __( 'License server rate limit hit, please retry shortly.', 'bspe-connect' ) ];
		}

		if ( $code >= 400 ) {
			$msg = (string) ( $json['error'] ?? sprintf( /* translators: %d: http status */ __( 'License server returned HTTP %d.', 'bspe-connect' ), $code ) );
			return [ 'error' => self::pretty_error( $msg ) ];
		}

		return $json;
	}

	private static function pretty_error( string $code ): string {
		switch ( $code ) {
			case 'invalid_key':
				return __( 'License key not recognized. Check for typos or contact BSPE Legal Marketing.', 'bspe-connect' );
			case 'missing_fields':
				return __( 'Internal: required fields missing from the activation request.', 'bspe-connect' );
			case 'invalid_domain':
				return __( 'Internal: this site\'s domain could not be determined.', 'bspe-connect' );
			case 'domain_mismatch':
				return __( 'This license key is already activated on a different domain. Contact BSPE Legal Marketing if this needs to be transferred.', 'bspe-connect' );
			case 'rate_limited':
				return __( 'License server rate limit hit, please retry shortly.', 'bspe-connect' );
			default:
				return $code;
		}
	}

	private static function b64url_decode( string $s ): string {
		$pad = strlen( $s ) % 4;
		if ( $pad > 0 ) {
			$s .= str_repeat( '=', 4 - $pad );
		}
		$decoded = base64_decode( strtr( $s, '-_', '+/' ), true );
		return false === $decoded ? '' : $decoded;
	}
}
