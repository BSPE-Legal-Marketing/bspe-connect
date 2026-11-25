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
 *
 * Delivery is blocking with a 10s timeout so the result can be logged
 * (lead delivery matters — admins want to know if the CRM didn't get
 * it). Form volume on these sites is low (a handful of leads a day),
 * so the occasional wait is acceptable. The webhook never blocks the
 * submission from succeeding: a failed POST is logged but the visitor
 * still sees the success message and the lead is still saved + emailed.
 *
 * PAYLOAD (flat, so Zapier/Make show every key as its own field):
 *   event, form_mode, source, submitted_at,
 *   name, first_name, last_name,
 *   phone (10 digits), phone_e164 (+1XXXXXXXXXX), phone_formatted ((XXX) XXX-XXXX),
 *   email, message, contact_pref,
 *   <every custom field key>   — custom mode only; a key that collides with
 *                                one above is sent as field_<key>
 *   summary                    — "Label: value" lines of every answered field
 *   page_url, site_name, firm_name, submission_id, [test]
 */
final class Webhook {

	private const TIMEOUT = 10;

	/** Keys the payload always carries; custom keys may not shadow them. */
	private const RESERVED = [ 'event', 'test', 'form_mode', 'source', 'submitted_at', 'name', 'first_name', 'last_name', 'phone', 'phone_e164', 'phone_formatted', 'email', 'message', 'contact_pref', 'summary', 'page_url', 'site_name', 'firm_name', 'submission_id' ];

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
		if ( '' === $url ) {
			return;
		}

		self::send_to( $url, $payload, 'form_submission' );
	}

	/**
	 * POST one payload and report what happened.
	 *
	 * @param array<string,mixed> $payload
	 * @return array{ok:bool, code:int, error:string}
	 */
	public static function send_to( string $url, array $payload, string $event = 'form_submission' ): array {
		if ( ! wp_http_validate_url( $url ) ) {
			Logger::log( 'error', 'Webhook skipped — URL failed validation', [ 'url' => $url ] );
			return [ 'ok' => false, 'code' => 0, 'error' => 'URL failed validation' ];
		}

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			Logger::log( 'error', 'Webhook skipped — payload could not be JSON-encoded' );
			return [ 'ok' => false, 'code' => 0, 'error' => 'payload could not be JSON-encoded' ];
		}

		$headers = [
			'Content-Type'         => 'application/json; charset=utf-8',
			'Accept'               => 'application/json',
			'User-Agent'           => 'BSPE-Connect/' . BSPE_CONNECT_VERSION,
			'X-BSPE-Connect-Event' => $event,
		];

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
				'event'  => $event,
				'reason' => $response->get_error_message(),
			] );
			return [ 'ok' => false, 'code' => 0, 'error' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			Logger::log( 'warn', 'Webhook POST returned non-2xx', [
				'url'   => $url,
				'event' => $event,
				'code'  => $code,
			] );
			return [ 'ok' => false, 'code' => $code, 'error' => 'HTTP ' . $code ];
		}

		Logger::log( 'info', 'Webhook delivered', [
			'url'   => $url,
			'event' => $event,
			'code'  => $code,
		] );
		return [ 'ok' => true, 'code' => $code, 'error' => '' ];
	}

	/**
	 * Build the lead payload (standard and custom mode share one shape).
	 *
	 * @param array<string,string>                                               $core   name, first_name, last_name, phone, email, message, contact_pref
	 * @param array<string,string>                                               $custom Custom field values by key ([] in standard mode).
	 * @param array<int,array{key:string,label:string,value:string,type:string}> $rows   Labelled rows for the summary.
	 * @param array<string,mixed>                                                $meta   source, page_url, submission_id, form_mode, test
	 * @return array<string,mixed>
	 */
	public static function build_payload( array $core, array $custom, array $rows, array $meta ): array {
		$name  = (string) ( $core['name'] ?? '' );
		$first = (string) ( $core['first_name'] ?? '' );
		$last  = (string) ( $core['last_name'] ?? '' );
		if ( '' === $first && '' === $last && '' !== $name ) {
			[ $first, $last ] = Custom_Form::split_name( $name );
		}
		$phone = (string) ( $core['phone'] ?? '' );

		$payload = [ 'event' => 'form_submission' ];
		if ( ! empty( $meta['test'] ) ) {
			$payload['test'] = true;
		}
		$payload += [
			'form_mode'       => (string) ( $meta['form_mode'] ?? 'standard' ),
			'source'          => (string) ( $meta['source'] ?? '' ),
			'submitted_at'    => current_time( 'c' ),
			'name'            => $name,
			'first_name'      => $first,
			'last_name'       => $last,
			'phone'           => $phone,
			'phone_e164'      => Custom_Form::phone_e164( $phone ),
			'phone_formatted' => Custom_Form::format_phone( $phone ),
			'email'           => (string) ( $core['email'] ?? '' ),
			'message'         => (string) ( $core['message'] ?? '' ),
			'contact_pref'    => (string) ( $core['contact_pref'] ?? '' ),
		];

		foreach ( $custom as $key => $value ) {
			if ( in_array( $key, self::RESERVED, true ) ) {
				// A field keyed "phone" / "email" / "message" is normally the
				// one that fills that core key — sending it twice would only
				// clutter the Zap. A different value is kept as field_<key>.
				if ( isset( $payload[ $key ] ) && (string) $payload[ $key ] === (string) $value ) {
					continue;
				}
				$payload[ 'field_' . $key ] = (string) $value;
				continue;
			}
			$payload[ $key ] = (string) $value;
		}

		if ( empty( $rows ) ) {
			$rows = [
				[ 'key' => 'name', 'label' => __( 'Name', 'bspe-connect' ), 'value' => $name, 'type' => 'text' ],
				[ 'key' => 'phone', 'label' => __( 'Phone', 'bspe-connect' ), 'value' => $phone, 'type' => 'tel' ],
				[ 'key' => 'email', 'label' => __( 'Email', 'bspe-connect' ), 'value' => (string) ( $core['email'] ?? '' ), 'type' => 'email' ],
				[ 'key' => 'contact_pref', 'label' => __( 'Preferred contact', 'bspe-connect' ), 'value' => (string) ( $core['contact_pref'] ?? '' ), 'type' => 'text' ],
				[ 'key' => 'message', 'label' => __( 'Message', 'bspe-connect' ), 'value' => (string) ( $core['message'] ?? '' ), 'type' => 'textarea' ],
			];
		}

		$payload['summary']       = Custom_Form::summary( $rows );
		$payload['page_url']      = (string) ( $meta['page_url'] ?? '' );
		$payload['site_name']     = (string) get_bloginfo( 'name' );
		$payload['firm_name']     = (string) ( Settings::get( 'design.firm_name', '' ) ?: get_bloginfo( 'name' ) );
		$payload['submission_id'] = (int) ( $meta['submission_id'] ?? 0 );

		return $payload;
	}

	/**
	 * A realistic test lead for the CURRENT form, so a Zap can be (re)mapped
	 * against the exact keys real submissions will carry.
	 *
	 * @return array<string,mixed>
	 */
	public static function sample_payload(): array {
		if ( Custom_Form::is_active() ) {
			$config = Custom_Form::config();
			$values = Custom_Form::sample_values( $config );
			$parsed = Custom_Form::collect( $values, $config );
			return self::build_payload( $parsed['core'], $parsed['values'], $parsed['rows'], [
				'form_mode' => 'custom',
				'source'    => 'email',
				'page_url'  => home_url( '/' ),
				'test'      => true,
			] );
		}

		return self::build_payload(
			[
				'name'         => 'Test Lead',
				'phone'        => '5555550123',
				'email'        => 'test.lead@example.com',
				'message'      => 'This is a test submission sent from BSPE Connect to check the webhook mapping.',
				'contact_pref' => '',
			],
			[],
			[],
			[ 'form_mode' => 'standard', 'source' => 'email', 'page_url' => home_url( '/' ), 'test' => true ]
		);
	}

	/**
	 * The keys a submission of this custom form will carry (for docs / the
	 * Fleet, so a Zap can be checked against the schema without sending).
	 *
	 * @param array<string,mixed> $custom
	 * @return string[]
	 */
	public static function payload_keys( array $custom ): array {
		$keys     = array_values( array_diff( self::RESERVED, [ 'test' ] ) );
		$role_key = [ 'first_name' => 'first_name', 'last_name' => 'last_name', 'full_name' => 'name', 'phone' => 'phone', 'email' => 'email', 'message' => 'message' ];
		$roles    = Custom_Form::effective_roles( $custom );
		foreach ( Custom_Form::all_fields( $custom ) as $f ) {
			if ( ! in_array( $f['key'], self::RESERVED, true ) ) {
				$keys[] = $f['key'];
				continue;
			}
			$fills_same_key = false;
			foreach ( $role_key as $role => $core ) {
				if ( $core === $f['key'] && ( $roles[ $role ] ?? '' ) === $f['key'] ) {
					$fills_same_key = true;
				}
			}
			if ( ! $fills_same_key ) {
				$keys[] = 'field_' . $f['key'];
			}
		}
		return $keys;
	}
}
