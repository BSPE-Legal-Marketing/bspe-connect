<?php
/**
 * AJAX form submission handler with anti-spam pipeline.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Handles `wp-admin/admin-ajax.php?action=bspe_connect_submit`.
 *
 * Pipeline order (spec §7):
 *   1. Nonce check
 *   2. Honeypot (silent reject = success response to bot)
 *   3. Time check (silent reject)
 *   4. Rate limit (per hashed IP)
 *   5. Turnstile verification (if enabled)
 *   6. Field validation
 *   7. DB insert
 *   8. Email send
 *   9. Increment rate-limit counter
 */
final class Form_Handler {

	public const ACTION       = 'bspe_connect_submit';
	public const NONCE_ACTION = 'bspe_connect_form';

	public const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		// 1. Nonce.
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'bspe_connect_nonce', false ) ) {
			self::error_response( [ '_form' => __( 'Your session expired. Please refresh the page and try again.', 'bspe-connect' ) ] );
		}

		// 2. Honeypot — silently accept then drop.
		$honeypot = isset( $_POST['bspe_website'] ) ? trim( wp_unslash( (string) $_POST['bspe_website'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_send_json_success( [ 'message' => self::success_message() ] );
		}

		// 3. Time check.
		$min_seconds = (int) Settings::get( 'form.antispam.min_seconds', 2 );
		$ts_raw      = isset( $_POST['bspe_connect_form_ts'] ) ? (string) wp_unslash( $_POST['bspe_connect_form_ts'] ) : '';
		$form_ts     = (int) $ts_raw;
		if ( $min_seconds > 0 && $form_ts > 0 && ( time() - $form_ts ) < $min_seconds ) {
			wp_send_json_success( [ 'message' => self::success_message() ] );
		}

		// 4. Rate limit.
		$ip_raw  = Submissions::client_ip();
		$ip_hash = Submissions::hash_ip( $ip_raw );
		if ( '' !== $ip_hash ) {
			$rate_key   = 'bspe_connect_rl_' . $ip_hash;
			$rate_count = (int) get_transient( $rate_key );
			$rate_limit = (int) Settings::get( 'form.antispam.rate_limit', 5 );
			if ( $rate_limit > 0 && $rate_count >= $rate_limit ) {
				self::error_response( [ '_form' => __( 'Too many submissions from your address. Please try again later.', 'bspe-connect' ) ], 429 );
			}
		}

		// 5. Turnstile verification (only if enabled and a secret is configured).
		// The secret may come from wp-config.php (preferred — keeps the secret out
		// of the database / options export) or from settings as a fallback.
		$turnstile_enabled = (bool) Settings::get( 'form.antispam.turnstile_enabled', false );
		$secret_key        = self::resolve_turnstile_secret();
		if ( $turnstile_enabled && '' !== $secret_key ) {
			$token = isset( $_POST['cf-turnstile-response'] ) ? trim( wp_unslash( (string) $_POST['cf-turnstile-response'] ) ) : '';
			if ( '' === $token || ! self::verify_turnstile( $token, $secret_key, $ip_raw ) ) {
				self::error_response( [ '_form' => __( 'Captcha verification failed. Please try again.', 'bspe-connect' ) ] );
			}
		}

		// 6. Sanitize + validate.
		$source = self::clean_source( isset( $_POST['bspe_source'] ) ? (string) wp_unslash( $_POST['bspe_source'] ) : '' );
		$fields = self::collect_fields();
		$errors = self::validate_fields( $fields );
		if ( ! empty( $errors ) ) {
			self::error_response( $errors );
		}

		// 7. Persist submission.
		$page_url   = self::sanitize_referer( isset( $_POST['bspe_page_url'] ) ? (string) wp_unslash( $_POST['bspe_page_url'] ) : '' );
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

		$row_id = Submissions::insert( [
			'submitted_at'  => current_time( 'mysql' ),
			'source_button' => $source,
			'name'          => $fields['name'],
			'phone'         => $fields['phone'],
			'email'         => $fields['email'],
			'message'       => $fields['message'],
			'contact_pref'  => $fields['contact_pref'],
			'page_url'      => $page_url,
			'user_agent'    => $user_agent,
			'ip_hash'       => $ip_hash,
			'mail_status'   => 'pending',
		] );

		// 8. Send email.
		$mail_vars = [
			'site_name'    => (string) get_bloginfo( 'name' ),
			'firm_name'    => (string) ( Settings::get( 'design.firm_name', '' ) ?: get_bloginfo( 'name' ) ),
			'source'       => $source,
			'page_url'     => $page_url,
			'name'         => $fields['name'],
			'phone'        => $fields['phone'],
			'email'        => $fields['email'],
			'message'      => $fields['message'],
			'contact_pref' => $fields['contact_pref'] ?? '',
		];

		$mail_ok = Mailer::send( $mail_vars );
		if ( $row_id ) {
			Submissions::update_mail_status( $row_id, $mail_ok ? 'sent' : 'failed' );
		}

		// 9. Increment rate-limit transient (1-hour window).
		if ( '' !== $ip_hash ) {
			$rate_key = 'bspe_connect_rl_' . $ip_hash;
			$current  = (int) get_transient( $rate_key );
			set_transient( $rate_key, $current + 1, HOUR_IN_SECONDS );
		}

		wp_send_json_success( [ 'message' => self::success_message() ] );
	}

	/* -----------------------------------------------------------------
	 * Field collection + validation
	 * ----------------------------------------------------------------- */

	/**
	 * @return array<string,string>
	 */
	private static function collect_fields(): array {
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['message'] ) ) : '';
		$pref    = isset( $_POST['contact_pref'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['contact_pref'] ) ) : '';

		// Phone — strip all non-digits.
		$phone_raw = isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '';
		$phone     = preg_replace( '/\D/', '', $phone_raw ) ?? '';

		// Validate contact preference against the known set.
		$pref_valid = [ 'any', 'phone', 'text', 'email' ];
		if ( ! in_array( strtolower( $pref ), $pref_valid, true ) ) {
			$pref = '';
		} else {
			$pref = strtolower( $pref );
		}

		return [
			'name'         => substr( $name, 0, 255 ),
			'phone'        => substr( $phone, 0, 20 ),
			'email'        => substr( $email, 0, 255 ),
			'message'      => $message,
			'contact_pref' => $pref,
		];
	}

	/**
	 * @param array<string,string> $fields
	 *
	 * @return array<string,string>
	 */
	private static function validate_fields( array $fields ): array {
		$errors = [];
		$config = Settings::get( 'form.fields', [] );
		$config = is_array( $config ) ? $config : [];

		foreach ( [ 'name', 'email', 'message' ] as $key ) {
			$visible  = (bool) ( $config[ $key ]['visible']  ?? true );
			$required = (bool) ( $config[ $key ]['required'] ?? true );
			if ( $visible && $required && '' === trim( (string) $fields[ $key ] ) ) {
				$errors[ $key ] = self::required_message( $key );
			}
		}

		// Phone — required+visible AND must be exactly 10 digits.
		$phone_visible  = (bool) ( $config['phone']['visible']  ?? true );
		$phone_required = (bool) ( $config['phone']['required'] ?? true );
		if ( $phone_visible ) {
			$phone_len = strlen( $fields['phone'] );
			if ( $phone_required && 0 === $phone_len ) {
				$errors['phone'] = self::required_message( 'phone' );
			} elseif ( $phone_len > 0 && 10 !== $phone_len ) {
				$errors['phone'] = __( 'Please enter a 10-digit US phone number.', 'bspe-connect' );
			}
		}

		// Email format check (only if non-empty).
		if ( '' !== $fields['email'] && ! is_email( $fields['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'bspe-connect' );
		}

		// Contact preference required + visible — only flag if visible AND required AND empty.
		$pref_visible  = (bool) ( $config['contact_pref']['visible']  ?? false );
		$pref_required = (bool) ( $config['contact_pref']['required'] ?? false );
		if ( $pref_visible && $pref_required && '' === $fields['contact_pref'] ) {
			$errors['contact_pref'] = __( 'Please choose your preferred contact method.', 'bspe-connect' );
		}

		return $errors;
	}

	private static function required_message( string $field ): string {
		switch ( $field ) {
			case 'name':
				return __( 'Please enter your name.', 'bspe-connect' );
			case 'phone':
				return __( 'Please enter your phone number.', 'bspe-connect' );
			case 'email':
				return __( 'Please enter your email address.', 'bspe-connect' );
			case 'message':
				return __( 'Please enter a message.', 'bspe-connect' );
			default:
				return __( 'This field is required.', 'bspe-connect' );
		}
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	private static function clean_source( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		return in_array( $raw, [ 'text', 'email' ], true ) ? $raw : 'email';
	}

	private static function sanitize_referer( string $raw ): string {
		$url = esc_url_raw( $raw, [ 'http', 'https' ] );
		return substr( (string) $url, 0, 500 );
	}

	private static function success_message(): string {
		$msg = (string) Settings::get( 'form.success_msg', '' );
		if ( '' === $msg ) {
			$msg = __( "Thanks. We'll be in touch shortly.", 'bspe-connect' );
		}
		return $msg;
	}

	/**
	 * @param array<string,string> $errors
	 */
	private static function error_response( array $errors, int $status = 400 ): void {
		wp_send_json_error( [ 'errors' => $errors ], $status );
	}

	/**
	 * Prefer BSPE_CONNECT_TURNSTILE_SECRET from wp-config.php so the secret
	 * never lands in the database or in a settings export. Falls back to
	 * the value saved in settings if the constant isn't defined.
	 */
	private static function resolve_turnstile_secret(): string {
		if ( defined( 'BSPE_CONNECT_TURNSTILE_SECRET' ) ) {
			$constant = (string) constant( 'BSPE_CONNECT_TURNSTILE_SECRET' );
			if ( '' !== trim( $constant ) ) {
				return $constant;
			}
		}
		return (string) Settings::get( 'form.antispam.turnstile_secret_key', '' );
	}

	private static function verify_turnstile( string $token, string $secret, string $remote_ip ): bool {
		$body = [
			'secret'   => $secret,
			'response' => $token,
		];
		if ( '' !== $remote_ip ) {
			$body['remoteip'] = $remote_ip;
		}

		$response = wp_remote_post( self::TURNSTILE_VERIFY_URL, [
			'timeout' => 5,
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $payload ) && ! empty( $payload['success'] );
	}
}
