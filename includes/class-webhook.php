<?php
/**
 * Outbound form-submission webhook.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * POSTs a JSON payload of each successful form submission to an
 * admin-configured URL, in addition to the email + stored DB row.
 * Lets a firm pipe leads straight into a CRM, Zapier, Make, n8n, etc.
 *
 * Settings shape (Settings::defaults().form.webhook):
 *   enabled  bool
 *   url      string   — https endpoint
 *   secret   string   — optional; when set, the request carries an
 *                       X-BSPE-Signature: sha256=<hmac> header so the
 *                       receiver can verify the body wasn't tampered.
 *
 * Delivery is blocking with a 10s timeout so the result can be logged
 * (lead delivery matters — admins want to know if the CRM didn't get
 * it). Form volume on these sites is low (a handful of leads a day),
 * so the occasional wait is acceptable. The webhook never blocks the
 * submission from succeeding: a failed POST is logged but the visitor
 * still sees the success message and the lead is still saved + emailed.
 */
final class Webhook {

	private const TIMEOUT = 10;

	/**
	 * Fire the webhook for one submission if enabled + configured.
	 * Safe to call unconditionally — it self-gates on the setting.
	 *
	 * @param array<string,mixed> $payload Submission data to send.
	 */
	public static function maybe_send( array $payload ): void {
		if ( ! (bool) Settings::get( 'form.webhook.enabled', false ) ) {
			return;
		}

		$url = trim( (string) Settings::get( 'form.webhook.url', '' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			if ( '' !== $url ) {
				Logger::log( 'error', 'Webhook skipped — URL failed validation', [ 'url' => $url ] );
			}
			return;
		}

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			Logger::log( 'error', 'Webhook skipped — payload could not be JSON-encoded' );
			return;
		}

		$headers = [
			'Content-Type'          => 'application/json; charset=utf-8',
			'Accept'                => 'application/json',
			'User-Agent'            => 'BSPE-Connect/' . BSPE_CONNECT_VERSION,
			'X-BSPE-Connect-Event'  => 'form_submission',
		];

		// Optional HMAC signature so the receiver can confirm the body
		// came from us and wasn't altered in transit. Format mirrors
		// GitHub / Stripe webhooks: "sha256=<hex>".
		$secret = (string) Settings::get( 'form.webhook.secret', '' );
		if ( '' !== $secret ) {
			$headers['X-BSPE-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}

		$response = wp_remote_post( $url, [
			'timeout'     => self::TIMEOUT,
			'redirection' => 3,
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => $body,
		] );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'error', 'Webhook POST failed', [
				'url'    => $url,
				'reason' => $response->get_error_message(),
			] );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			Logger::log( 'warn', 'Webhook POST returned non-2xx', [
				'url'  => $url,
				'code' => $code,
			] );
			return;
		}

		Logger::log( 'info', 'Webhook delivered', [
			'url'  => $url,
			'code' => $code,
		] );
	}
}
