<?php
/**
 * Settings POST handler with per-tab sanitization.
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

use BSPE\Connect\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles `wp-admin/admin-post.php?action=bspe_connect_save_settings`.
 *
 * Each tab POSTs to this handler. The `_tab` field decides which sanitizer
 * runs; the sanitized result is merged into the existing settings array
 * so unrelated tabs are never clobbered.
 */
final class Settings_Saver {

	public const ACTION       = 'bspe_connect_save_settings';
	public const NONCE_ACTION = 'bspe_connect_save';
	public const NOTICE_KEY   = 'bspe_connect_admin_notice';

	private const ALLOWED_TABS = [ 'general', 'buttons', 'form', 'design', 'display' ];

	private const ALLOWED_ICONS = [ 'connect-1', 'connect-2', 'connect-3', 'connect-4', 'call-1', 'call-2', 'call-3', 'call-4', 'text-1', 'text-2', 'text-3', 'text-4', 'email-1', 'email-2', 'email-3', 'email-4' ];

	private const ALLOWED_ICON_LIBRARIES = [ 'none', 'brand', 'fa-solid', 'fa-regular', 'ion-filled', 'ion-outline', 'dripicons' ];

	private const ALLOWED_GOOGLE_FONTS = [ 'DM Sans', 'Inter', 'Lato', 'Roboto', 'Open Sans', 'Source Sans 3', 'Poppins', 'Manrope', 'Nunito', 'Work Sans', 'Plus Jakarta Sans', 'IBM Plex Sans', 'Figtree', 'Montserrat', 'Public Sans' ];

	private const ALLOWED_DISPLAY_MODES = [ 'sitewide', 'pages_only', 'posts_only', 'pages_except', 'posts_except', 'sitewide_except_pages', 'sitewide_except_posts' ];

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to update these settings.', 'bspe-connect' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		$tab = isset( $_POST['_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['_tab'] ) ) : 'general';
		if ( ! in_array( $tab, self::ALLOWED_TABS, true ) ) {
			$tab = 'general';
		}

		$existing = Settings::all();
		$payload  = isset( $_POST['bspe'] ) && is_array( $_POST['bspe'] ) ? wp_unslash( $_POST['bspe'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- per-tab sanitizers run below.

		switch ( $tab ) {
			case 'general':
				$existing                   = self::merge_general( $existing, $payload );
				break;
			case 'buttons':
				$existing['buttons']        = self::sanitize_buttons( $payload['buttons'] ?? [], $existing['buttons'] ?? [] );
				break;
			case 'form':
				$existing['form']           = self::sanitize_form( $payload['form'] ?? [], $existing['form'] ?? [] );
				break;
			case 'design':
				$existing['design']         = self::sanitize_design( $payload['design'] ?? [], $existing['design'] ?? [] );
				break;
			case 'display':
				$existing['display_rules']  = self::sanitize_display_rules( $payload['display_rules'] ?? [], $existing['display_rules'] ?? [] );
				break;
		}

		Settings::save( $existing );

		set_transient( self::NOTICE_KEY . '_' . get_current_user_id(), 'saved', 60 );

		wp_safe_redirect( Admin::tab_url( $tab ) );
		exit;
	}

	/**
	 * Returns the one-shot admin notice for the current user, then clears it.
	 */
	public static function consume_notice(): string {
		$key  = self::NOTICE_KEY . '_' . get_current_user_id();
		$type = (string) get_transient( $key );
		if ( '' === $type ) {
			return '';
		}
		delete_transient( $key );
		return $type;
	}

	/* -----------------------------------------------------------------
	 * General
	 * ----------------------------------------------------------------- */

	/**
	 * Merge sanitized General-tab values back into the full settings array.
	 *
	 * @param array<string,mixed> $existing
	 * @param array<string,mixed> $payload
	 *
	 * @return array<string,mixed>
	 */
	private static function merge_general( array $existing, array $payload ): array {
		$existing['enabled']        = ! empty( $payload['enabled'] );
		$existing['welcome_bubble'] = self::sanitize_welcome_bubble( $payload['welcome_bubble'] ?? [], $existing['welcome_bubble'] ?? [] );
		$existing['display']        = self::sanitize_display( $payload['display'] ?? [], $existing['display'] ?? [] );
		return $existing;
	}

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_welcome_bubble( array $input, array $current ): array {
		$trigger = isset( $input['trigger'] ) ? (string) $input['trigger'] : 'auto';
		$repeat  = isset( $input['repeat'] )  ? (string) $input['repeat']  : 'session';

		return [
			'enabled'     => ! empty( $input['enabled'] ),
			'heading'     => sanitize_text_field( (string) ( $input['heading'] ?? '' ) ),
			'message'     => sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ),
			'show_avatar' => ! empty( $input['show_avatar'] ),
			'avatar_id'   => max( 0, (int) ( $input['avatar_id'] ?? 0 ) ),
			'trigger'     => in_array( $trigger, [ 'auto', 'click' ], true ) ? $trigger : 'auto',
			'delay'       => max( 0, min( 60, (int) ( $input['delay'] ?? 3 ) ) ),
			'repeat'      => in_array( $repeat, [ 'session', 'once', 'always' ], true ) ? $repeat : 'session',
		];
	}

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_display( array $input, array $current ): array {
		return [
			'scroll_threshold'  => max( 0, min( 5000, (int) ( $input['scroll_threshold'] ?? 200 ) ) ),
			'hide_on_scroll_up' => ! empty( $input['hide_on_scroll_up'] ),
			'mobile_breakpoint' => max( 320, min( 2000, (int) ( $input['mobile_breakpoint'] ?? 768 ) ) ),
		];
	}

	/* -----------------------------------------------------------------
	 * Buttons
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_buttons( array $input, array $current ): array {
		$out = [];

		// Connect.
		$connect          = is_array( $input['connect'] ?? null ) ? $input['connect'] : [];
		$connect_mode     = (string) ( $connect['mode'] ?? 'text' );
		$connect_lib      = self::sanitize_icon_library( (string) ( $connect['icon_library'] ?? 'brand' ) );
		$out['connect']   = [
			'enabled'      => ! empty( $connect['enabled'] ),
			'mode'         => in_array( $connect_mode, [ 'text', 'image' ], true ) ? $connect_mode : 'text',
			'label'        => sanitize_text_field( (string) ( $connect['label'] ?? 'Connect' ) ),
			'image_id'     => max( 0, (int) ( $connect['image_id'] ?? 0 ) ),
			'icon_library' => $connect_lib,
			'icon'         => self::sanitize_icon_name( (string) ( $connect['icon'] ?? 'connect-1' ), $connect_lib, 'connect' ),
		];

		// Call.
		$call           = is_array( $input['call'] ?? null ) ? $input['call'] : [];
		$call_lib       = self::sanitize_icon_library( (string) ( $call['icon_library'] ?? 'brand' ) );
		$out['call']    = [
			'enabled'      => ! empty( $call['enabled'] ),
			'phone'        => self::sanitize_phone( (string) ( $call['phone'] ?? '' ) ),
			'label'        => sanitize_text_field( (string) ( $call['label'] ?? 'Call' ) ),
			'icon_library' => $call_lib,
			'icon'         => self::sanitize_icon_name( (string) ( $call['icon'] ?? 'call-1' ), $call_lib, 'call' ),
		];

		// Text.
		$text           = is_array( $input['text'] ?? null ) ? $input['text'] : [];
		$text_mode      = (string) ( $text['mode'] ?? 'sms' );
		$text_lib       = self::sanitize_icon_library( (string) ( $text['icon_library'] ?? 'brand' ) );
		$out['text']    = [
			'enabled'      => ! empty( $text['enabled'] ),
			'mode'         => in_array( $text_mode, [ 'sms', 'inline' ], true ) ? $text_mode : 'sms',
			'phone'        => self::sanitize_phone( (string) ( $text['phone'] ?? '' ) ),
			'label'        => sanitize_text_field( (string) ( $text['label'] ?? 'Text' ) ),
			'icon_library' => $text_lib,
			'icon'         => self::sanitize_icon_name( (string) ( $text['icon'] ?? 'text-1' ), $text_lib, 'text' ),
		];

		// Email.
		$email          = is_array( $input['email'] ?? null ) ? $input['email'] : [];
		$email_lib      = self::sanitize_icon_library( (string) ( $email['icon_library'] ?? 'brand' ) );
		$out['email']   = [
			'enabled'      => ! empty( $email['enabled'] ),
			'label'        => sanitize_text_field( (string) ( $email['label'] ?? 'Email' ) ),
			'icon_library' => $email_lib,
			'icon'         => self::sanitize_icon_name( (string) ( $email['icon'] ?? 'email-1' ), $email_lib, 'email' ),
		];

		return $out;
	}

	private static function sanitize_phone( string $raw ): string {
		$digits = preg_replace( '/\D/', '', $raw ) ?? '';
		// Allow either 10 digits or empty (admin can save partial config).
		return strlen( $digits ) === 10 ? $digits : ( '' === $digits ? '' : substr( $digits, 0, 10 ) );
	}

	private static function sanitize_icon_library( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		return in_array( $raw, self::ALLOWED_ICON_LIBRARIES, true ) ? $raw : 'brand';
	}

	/**
	 * Sanitize the per-button icon name relative to its selected library.
	 * Brand library uses the bundled SVG list; third-party libs accept any
	 * value matching their naming pattern.
	 */
	private static function sanitize_icon_name( string $raw, string $library, string $type ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return 'brand' === $library ? $type . '-1' : '';
		}

		if ( 'brand' === $library ) {
			$raw = strtolower( $raw );
			if ( in_array( $raw, self::ALLOWED_ICONS, true ) && str_starts_with( $raw, $type . '-' ) ) {
				return $raw;
			}
			return $type . '-1';
		}

		// FA / Ionicons / Dripicons — accept any kebab-case-ish identifier.
		$cleaned = strtolower( preg_replace( '/[^a-z0-9-]/i', '', $raw ) ?? '' );
		return substr( $cleaned, 0, 60 );
	}

	/* -----------------------------------------------------------------
	 * Form
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_form( array $input, array $current ): array {
		// Field config.
		$fields    = is_array( $input['fields'] ?? null ) ? $input['fields'] : [];
		$out_field = [];
		foreach ( [ 'name', 'phone', 'email', 'contact_pref', 'message' ] as $key ) {
			$cfg              = is_array( $fields[ $key ] ?? null ) ? $fields[ $key ] : [];
			$visible          = ! empty( $cfg['visible'] );
			$required         = ! empty( $cfg['required'] );
			$out_field[ $key ] = [
				'visible'  => $visible,
				'required' => $visible ? $required : false,
			];
		}

		// Anti-spam.
		$as     = is_array( $input['antispam'] ?? null ) ? $input['antispam'] : [];
		$as_out = [
			'honeypot'             => ! empty( $as['honeypot'] ),
			'min_seconds'          => max( 0, min( 60, (int) ( $as['min_seconds'] ?? 2 ) ) ),
			'rate_limit'           => max( 0, min( 1000, (int) ( $as['rate_limit'] ?? 5 ) ) ),
			'turnstile_enabled'    => ! empty( $as['turnstile_enabled'] ),
			'turnstile_site_key'   => self::sanitize_secret( (string) ( $as['turnstile_site_key'] ?? '' ) ),
			'turnstile_secret_key' => self::sanitize_secret( (string) ( $as['turnstile_secret_key'] ?? '' ) ),
		];

		return [
			'fields'           => $out_field,
			'text_heading'     => sanitize_text_field( (string) ( $input['text_heading']     ?? 'Send us a text' ) ),
			'email_heading'    => sanitize_text_field( (string) ( $input['email_heading']    ?? 'Send us an email' ) ),
			'text_subheading'  => sanitize_text_field( (string) ( $input['text_subheading']  ?? 'Please enter your name and contact info.' ) ),
			'email_subheading' => sanitize_text_field( (string) ( $input['email_subheading'] ?? 'Please enter your name and contact info.' ) ),
			'submit_label'   => sanitize_text_field( (string) ( $input['submit_label']  ?? 'Send' ) ),
			'success_msg'    => sanitize_text_field( (string) ( $input['success_msg']   ?? "Thanks. We'll be in touch shortly." ) ),
			'mail_to'        => self::sanitize_email_list( (string) ( $input['mail_to'] ?? '' ) ),
			'mail_subject'   => sanitize_text_field( (string) ( $input['mail_subject'] ?? '' ) ),
			'mail_from'      => sanitize_email( (string) ( $input['mail_from'] ?? '' ) ),
			'mail_from_name' => sanitize_text_field( (string) ( $input['mail_from_name'] ?? '' ) ),
			'antispam'       => $as_out,
		];
	}

	private static function sanitize_email_list( string $raw ): string {
		$out = [];
		foreach ( preg_split( '/[,\s;]+/', $raw ) as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( '' !== $candidate && is_email( $candidate ) ) {
				$out[] = sanitize_email( $candidate );
			}
		}
		return implode( ', ', $out );
	}

	private static function sanitize_secret( string $raw ): string {
		// Trim only — some keys may include = and other chars sanitize_text_field would touch.
		return substr( trim( str_replace( [ "\r", "\n", "\0" ], '', $raw ) ), 0, 200 );
	}

	/* -----------------------------------------------------------------
	 * Design
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_design( array $input, array $current ): array {
		$current_colors = is_array( $current['colors'] ?? null ) ? $current['colors'] : [];
		$colors_in      = is_array( $input['colors']  ?? null ) ? $input['colors']  : [];

		$out_colors = [];
		foreach ( [ 'bar_bg' => '#351E28', 'button_bg' => '#351E28', 'button_fg' => '#FAF7F2', 'bubble_bg' => '#FAF7F2', 'bubble_fg' => '#351E28', 'accent' => '#3AAFB9' ] as $key => $default ) {
			$candidate = (string) ( $colors_in[ $key ] ?? ( $current_colors[ $key ] ?? $default ) );
			$out_colors[ $key ] = self::sanitize_hex_color( $candidate, (string) ( $current_colors[ $key ] ?? $default ) );
		}

		$font_mode = (string) ( $input['font_mode'] ?? 'inherit' );
		$font_mode = in_array( $font_mode, [ 'inherit', 'google' ], true ) ? $font_mode : 'inherit';

		$google_font = (string) ( $input['google_font'] ?? 'DM Sans' );
		if ( ! in_array( $google_font, self::ALLOWED_GOOGLE_FONTS, true ) ) {
			$google_font = 'DM Sans';
		}

		return [
			'firm_name'   => sanitize_text_field( (string) ( $input['firm_name'] ?? '' ) ),
			'colors'      => $out_colors,
			'icon_size'   => max( 12, min( 48, (int) ( $input['icon_size']  ?? 18 ) ) ),
			'label_size'  => max( 8,  min( 20, (int) ( $input['label_size'] ?? 11 ) ) ),
			'font_mode'   => $font_mode,
			'google_font' => $google_font,
		];
	}

	private static function sanitize_hex_color( string $color, string $fallback ): string {
		$color = trim( $color );
		if ( preg_match( '/^#([0-9a-f]{6})$/i', $color ) ) {
			return strtoupper( '#' . substr( $color, 1 ) );
		}
		if ( preg_match( '/^#([0-9a-f]{3})$/i', $color, $m ) ) {
			$expanded = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
			return strtoupper( $expanded );
		}
		return $fallback;
	}

	/* -----------------------------------------------------------------
	 * Display rules
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_display_rules( array $input, array $current ): array {
		$mode = (string) ( $input['mode'] ?? 'sitewide' );
		if ( ! in_array( $mode, self::ALLOWED_DISPLAY_MODES, true ) ) {
			$mode = 'sitewide';
		}

		$slugs_raw = (string) ( $input['slugs'] ?? '' );
		$slugs_raw = str_replace( [ "\r\n", "\r" ], "\n", $slugs_raw );

		// Normalize to a comma-separated list, sanitize each entry.
		$out_slugs = [];
		foreach ( preg_split( '/[,\n]+/', $slugs_raw ) as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( '' === $candidate ) {
				continue;
			}
			$slug = sanitize_title( $candidate );
			if ( '' !== $slug ) {
				$out_slugs[] = $slug;
			}
		}

		return [
			'mode'  => $mode,
			'slugs' => implode( ', ', array_unique( $out_slugs ) ),
		];
	}
}
