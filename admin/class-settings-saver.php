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

	public const RESET_ACTION  = 'bspe_connect_reset_settings';
	public const RESET_NONCE   = 'bspe_connect_reset_settings';
	public const RESET_PHRASE  = 'RESET';

	private const ALLOWED_TABS = [ 'general', 'buttons', 'form', 'design', 'display', 'logs', 'in_post_widget', 'chat' ];

	private const ALLOWED_ICON_LIBRARIES = [ 'none', 'fa-solid', 'fa-regular' ];

	/**
	 * Drop-in replacements for legacy library / icon values that were
	 * removed in v2.1.4. Anything not listed here falls back to the
	 * button-type default below.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const LEGACY_ICON_MIGRATIONS = [
		// brand → FA solid
		'connect-1' => [ 'fa-solid', 'comments' ],
		'connect-2' => [ 'fa-solid', 'comment-dots' ],
		'connect-3' => [ 'fa-solid', 'message' ],
		'call-1'    => [ 'fa-solid', 'phone' ],
		'call-2'    => [ 'fa-solid', 'mobile' ],
		'call-3'    => [ 'fa-solid', 'phone-volume' ],
		'text-1'    => [ 'fa-solid', 'comment-dots' ],
		'text-2'    => [ 'fa-solid', 'comments' ],
		'text-3'    => [ 'fa-solid', 'message' ],
		'email-1'   => [ 'fa-solid', 'envelope' ],
		'email-2'   => [ 'fa-solid', 'envelope-open' ],
		'email-3'   => [ 'fa-solid', 'at' ],
	];

	private const FALLBACK_FA_ICON_BY_TYPE = [
		'connect' => 'comments',
		'call'    => 'phone',
		'text'    => 'comment-dots',
		'email'   => 'envelope',
	];

	private const ALLOWED_GOOGLE_FONTS = [ 'DM Sans', 'Inter', 'Lato', 'Roboto', 'Open Sans', 'Source Sans 3', 'Poppins', 'Manrope', 'Nunito', 'Work Sans', 'Plus Jakarta Sans', 'IBM Plex Sans', 'Figtree', 'Montserrat', 'Public Sans' ];

	private const ALLOWED_DISPLAY_MODES = [ 'sitewide', 'pages_only', 'posts_only', 'pages_except', 'posts_except', 'sitewide_except_pages', 'sitewide_except_posts' ];

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION,       [ self::class, 'handle' ] );
		add_action( 'admin_post_' . self::RESET_ACTION, [ self::class, 'handle_reset' ] );
	}

	/**
	 * Hard-reset bspe_connect_settings to Settings::defaults(). Submissions,
	 * analytics events, the diagnostics log, and the DB version are NOT
	 * touched — only the plugin's settings option.
	 *
	 * The reset requires the admin to type the literal phrase "RESET" into
	 * the confirmation field. We re-validate that server-side so the gate
	 * still applies even if JS is bypassed (curl / direct POST).
	 */
	public static function handle_reset(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to reset settings.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::RESET_NONCE );

		$confirm = isset( $_POST['bspe_reset_confirm'] )
			? trim( (string) wp_unslash( (string) $_POST['bspe_reset_confirm'] ) )
			: '';

		if ( self::RESET_PHRASE !== $confirm ) {
			\BSPE\Connect\Logger::log( 'warn', 'Settings reset rejected — confirmation phrase mismatch', [
				'admin_user' => get_current_user_id(),
			] );
			set_transient( self::NOTICE_KEY . '_' . get_current_user_id(), 'reset_rejected', 60 );
			wp_safe_redirect(
				add_query_arg(
					[ 'page' => Admin::PAGE_SLUG, 'tab' => 'general' ],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Replace the entire settings option with the canonical defaults.
		// update_option short-circuits when the value hasn't changed; we
		// pass autoload = 'yes' to match the original add_option call in
		// Activator::seed_defaults().
		update_option( \BSPE\Connect\Settings::OPTION_KEY, \BSPE\Connect\Settings::defaults(), 'yes' );

		\BSPE\Connect\Logger::log( 'warn', 'Settings reset to defaults', [
			'admin_user' => get_current_user_id(),
		] );

		set_transient( self::NOTICE_KEY . '_' . get_current_user_id(), 'reset', 60 );
		wp_safe_redirect(
			add_query_arg(
				[ 'page' => Admin::PAGE_SLUG, 'tab' => 'general' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
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
			case 'logs':
				$existing['diagnostics']    = self::sanitize_diagnostics( $payload['diagnostics'] ?? [] );
				break;
			case 'in_post_widget':
				$existing['in_post_widget'] = self::sanitize_in_post_widget( $payload['in_post_widget'] ?? [] );
				break;
			case 'chat':
				$existing['chat'] = self::sanitize_chat( $payload['chat'] ?? [], $existing['chat'] ?? [] );
				break;
		}

		Settings::save( $existing );

		// AJAX path — the admin.js form-submit handler intercepts the
		// settings form and re-POSTs with X-Requested-With set. We
		// detect that header and respond with JSON instead of doing
		// the traditional set-transient-then-redirect dance. Same
		// pipeline either way (cap check, nonce, sanitize, save) —
		// only the response shape changes.
		if ( self::is_xhr_request() ) {
			wp_send_json_success( [
				'message' => __( 'Settings saved.', 'bspe-connect' ),
				'tab'     => $tab,
			] );
		}

		set_transient( self::NOTICE_KEY . '_' . get_current_user_id(), 'saved', 60 );

		wp_safe_redirect( Admin::tab_url( $tab ) );
		exit;
	}

	/**
	 * Detect a fetch / XHR call by sniffing the standard
	 * X-Requested-With header. WordPress's wp_doing_ajax() doesn't
	 * help here because settings posts go through admin-post.php,
	 * not admin-ajax.php.
	 */
	private static function is_xhr_request(): bool {
		if ( empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) {
			return false;
		}
		$hdr = (string) wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] );
		return 'xmlhttprequest' === strtolower( $hdr );
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
		$existing['utilities']      = self::sanitize_utilities( $payload['utilities'] ?? [], $existing['utilities'] ?? [] );
		return $existing;
	}

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_utilities( array $input, array $current ): array {
		return [
			'qr_indexer'             => ! empty( $input['qr_indexer'] ),
			'qr_size_px'             => max( 80, min( 400,  (int) ( $input['qr_size_px']      ?? 150 ) ) ),
			'qr_max_width_px'        => max( 320, min( 2400, (int) ( $input['qr_max_width_px'] ?? 1240 ) ) ),
			'external_links_new_tab' => ! empty( $input['external_links_new_tab'] ),
			'hide_users_rest'        => ! empty( $input['hide_users_rest'] ),
		];
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
			'show_delay'        => max( 0, min( 60, (int) ( $input['show_delay'] ?? 3 ) ) ),
			'scroll_threshold'  => max( 0, min( 5000, (int) ( $input['scroll_threshold'] ?? 0 ) ) ),
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
		$connect_lib_raw  = (string) ( $connect['icon_library'] ?? 'none' );
		// "none" survives sanitize unchanged; legacy values get migrated.
		$connect_lib      = 'none' === strtolower( trim( $connect_lib_raw ) )
			? 'none'
			: self::sanitize_icon_library( $connect_lib_raw );
		$out['connect']   = [
			'enabled'         => ! empty( $connect['enabled'] ),
			'label'           => sanitize_text_field( (string) ( $connect['label'] ?? 'Connect' ) ),
			'icon_library'    => $connect_lib,
			'icon'            => self::sanitize_icon_name( (string) ( $connect['icon'] ?? '' ), $connect_lib, 'connect' ),
			'label_weight'    => self::sanitize_label_weight( (string) ( $connect['label_weight']    ?? '' ) ),
			'label_uppercase' => self::sanitize_label_uppercase( (string) ( $connect['label_uppercase'] ?? '' ) ),
		];

		// Call.
		$call           = is_array( $input['call'] ?? null ) ? $input['call'] : [];
		$call_lib       = self::sanitize_icon_library( (string) ( $call['icon_library'] ?? 'fa-solid' ) );
		$out['call']    = [
			'enabled'         => ! empty( $call['enabled'] ),
			'phone'           => self::sanitize_phone( (string) ( $call['phone'] ?? '' ) ),
			'label'           => sanitize_text_field( (string) ( $call['label'] ?? 'Call' ) ),
			'icon_library'    => $call_lib,
			'icon'            => self::sanitize_icon_name( (string) ( $call['icon'] ?? 'call-1' ), $call_lib, 'call' ),
			'label_weight'    => self::sanitize_label_weight( (string) ( $call['label_weight']    ?? '' ) ),
			'label_uppercase' => self::sanitize_label_uppercase( (string) ( $call['label_uppercase'] ?? '' ) ),
		];

		// Text.
		$text           = is_array( $input['text'] ?? null ) ? $input['text'] : [];
		$text_mode      = (string) ( $text['mode'] ?? 'sms' );
		$text_lib       = self::sanitize_icon_library( (string) ( $text['icon_library'] ?? 'fa-solid' ) );
		$out['text']    = [
			'enabled'         => ! empty( $text['enabled'] ),
			'mode'            => in_array( $text_mode, [ 'sms', 'inline' ], true ) ? $text_mode : 'sms',
			'phone'           => self::sanitize_phone( (string) ( $text['phone'] ?? '' ) ),
			'label'           => sanitize_text_field( (string) ( $text['label'] ?? 'Text' ) ),
			'icon_library'    => $text_lib,
			'icon'            => self::sanitize_icon_name( (string) ( $text['icon'] ?? 'text-1' ), $text_lib, 'text' ),
			'label_weight'    => self::sanitize_label_weight( (string) ( $text['label_weight']    ?? '' ) ),
			'label_uppercase' => self::sanitize_label_uppercase( (string) ( $text['label_uppercase'] ?? '' ) ),
		];

		// Email.
		$email          = is_array( $input['email'] ?? null ) ? $input['email'] : [];
		$email_lib      = self::sanitize_icon_library( (string) ( $email['icon_library'] ?? 'fa-solid' ) );
		$out['email']   = [
			'enabled'         => ! empty( $email['enabled'] ),
			'label'           => sanitize_text_field( (string) ( $email['label'] ?? 'Email' ) ),
			'icon_library'    => $email_lib,
			'icon'            => self::sanitize_icon_name( (string) ( $email['icon'] ?? 'email-1' ), $email_lib, 'email' ),
			'label_weight'    => self::sanitize_label_weight( (string) ( $email['label_weight']    ?? '' ) ),
			'label_uppercase' => self::sanitize_label_uppercase( (string) ( $email['label_uppercase'] ?? '' ) ),
		];

		return $out;
	}

	/**
	 * @param array<string,mixed> $input
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_diagnostics( array $input ): array {
		return [
			'logging_enabled' => ! empty( $input['logging_enabled'] ),
		];
	}

	/**
	 * @param array<string,mixed> $input
	 *
	 * @return array<string,mixed>
	 */
	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $current
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_chat( array $input, array $current ): array {
		$provider = (string) ( $input['provider'] ?? 'intaker' );
		$provider = in_array( $provider, [ 'intaker', 'custom' ], true ) ? $provider : 'intaker';

		// Intaker ODL is an account slug — restrict to a safe charset.
		$odl = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $input['intaker_odl'] ?? '' ) ) ?? '';

		// Custom embed script is admin-authored markup. Don't run it
		// through wp_kses (that would strip the <script>); just strip
		// null / control bytes. Same trust model as the In-Post Widget
		// shortcode + any header/footer-script plugin.
		// NOTE: handle() already wp_unslash'd the whole $payload, so we
		// must NOT unslash again here — a second pass would mangle real
		// backslashes the admin typed (e.g. \d in an embedded regex).
		$custom = (string) ( $input['custom_script'] ?? '' );
		$custom = preg_replace( '/[\x00-\x08\x0E-\x1F]/', '', $custom ) ?? '';
		$custom = trim( $custom );

		// Open-selector override — a CSS selector. Keep it simple: strip
		// tags + line breaks, cap length. Empty = use provider default.
		$selector = sanitize_text_field( (string) ( $input['open_selector'] ?? '' ) );
		$selector = substr( $selector, 0, 200 );

		$icon = preg_replace( '/[^a-z0-9-]/i', '', (string) ( $input['button_icon'] ?? 'comment-dots' ) ) ?? '';

		// Icon library — same allow-list as the core buttons (none /
		// fa-solid / fa-regular).
		$icon_lib = (string) ( $input['button_icon_library'] ?? 'fa-solid' );
		$icon_lib = in_array( $icon_lib, [ 'none', 'fa-solid', 'fa-regular' ], true ) ? $icon_lib : 'fa-solid';

		return [
			'enabled'             => ! empty( $input['enabled'] ),
			'provider'            => $provider,
			'intaker_odl'         => $odl,
			'custom_script'       => $custom,
			'open_selector'       => $selector,
			'show_button'         => ! empty( $input['show_button'] ),
			'button_label'        => sanitize_text_field( (string) ( $input['button_label'] ?? 'Chat' ) ),
			'button_icon_library' => $icon_lib,
			'button_icon'         => '' !== $icon ? $icon : 'comment-dots',
		];
	}

	private static function sanitize_in_post_widget( array $input ): array {
		// Shortcode field — wp_kses is too strict, sanitize_textarea_field
		// strips legitimate shortcode brackets. Trim and let the admin's
		// raw input through; the runtime hook passes it to do_shortcode
		// which is the canonical pipeline anyway.
		// NOTE: handle() already wp_unslash'd the whole $payload, so we
		// must NOT unslash again here (a second pass mangles backslashes
		// the admin actually typed).
		$shortcode = isset( $input['shortcode'] ) ? trim( (string) $input['shortcode'] ) : '';
		// Strip control characters that have no business in a shortcode.
		$shortcode = preg_replace( '/[\x00-\x08\x0E-\x1F]/', '', $shortcode ) ?? '';

		// Exclusion list — comma / whitespace separated post IDs.
		$exclude_raw = isset( $input['exclude_ids'] ) ? (string) $input['exclude_ids'] : '';
		$exclude_ids = array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $exclude_raw ) ?: [] ) );
		$exclude     = implode( ',', $exclude_ids );

		return [
			'enabled'                  => ! empty( $input['enabled'] ),
			'shortcode'                => $shortcode,
			'margin_bottom_px'         => max( 0, min( 200, (int) ( $input['margin_bottom_px'] ?? 20 ) ) ),
			'fallback_after_paragraph' => max( 1, min( 10, (int) ( $input['fallback_after_paragraph'] ?? 1 ) ) ),
			'exclude_ids'              => $exclude,
		];
	}

	/**
	 * Per-button label weight override. Empty string = inherit from the
	 * Design tab default. Otherwise must be one of the four allowed
	 * weights so we don't end up loading half-weight Google fonts.
	 */
	private static function sanitize_label_weight( string $raw ): string {
		$raw = trim( $raw );
		return in_array( $raw, [ '', '400', '500', '600', '700' ], true ) ? $raw : '';
	}

	/**
	 * Per-button uppercase override. '' = inherit, 'yes' = uppercase,
	 * 'no' = render in saved case.
	 */
	private static function sanitize_label_uppercase( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		return in_array( $raw, [ '', 'yes', 'no' ], true ) ? $raw : '';
	}

	private static function sanitize_phone( string $raw ): string {
		$digits = preg_replace( '/\D/', '', $raw ) ?? '';
		// Allow either 10 digits or empty (admin can save partial config).
		return strlen( $digits ) === 10 ? $digits : ( '' === $digits ? '' : substr( $digits, 0, 10 ) );
	}

	/**
	 * Sanitize an icon library value. Migrates pre-v2.1.4 values
	 * (brand, ion-*, dripicons) to fa-solid silently so existing client
	 * configs don't break on upgrade.
	 */
	private static function sanitize_icon_library( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		if ( in_array( $raw, self::ALLOWED_ICON_LIBRARIES, true ) ) {
			return $raw;
		}
		// Anything legacy → fa-solid.
		return 'fa-solid';
	}

	/**
	 * Sanitize the per-button icon name. Migrates legacy brand / ion /
	 * drip icon names to FA equivalents so upgrading clients see a
	 * sensible default rather than an empty icon slot.
	 */
	private static function sanitize_icon_name( string $raw, string $library, string $type ): string {
		$raw = trim( $raw );

		// Library = none → no icon, regardless of stored name.
		if ( 'none' === $library ) {
			return '';
		}

		// Empty name → button-type default.
		if ( '' === $raw ) {
			return self::FALLBACK_FA_ICON_BY_TYPE[ $type ] ?? 'phone';
		}

		// Migrate legacy slugs (connect-1, dripicons-message, etc.).
		$lower = strtolower( $raw );
		if ( isset( self::LEGACY_ICON_MIGRATIONS[ $lower ] ) ) {
			return self::LEGACY_ICON_MIGRATIONS[ $lower ][1];
		}
		if ( 0 === strpos( $lower, 'dripicons-' ) ) {
			return self::FALLBACK_FA_ICON_BY_TYPE[ $type ] ?? 'phone';
		}
		if ( substr( $lower, -8 ) === '-outline' ) {
			$lower = substr( $lower, 0, -8 );
		}

		// FA — accept any kebab-case identifier.
		$cleaned = preg_replace( '/[^a-z0-9-]/i', '', $lower ) ?? '';
		return '' === $cleaned ? ( self::FALLBACK_FA_ICON_BY_TYPE[ $type ] ?? 'phone' ) : substr( $cleaned, 0, 60 );
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
			'retention_days' => max( 0, min( 3650, (int) ( $input['retention_days'] ?? 0 ) ) ),
			'webhook'        => self::sanitize_webhook( $input['webhook'] ?? [] ),
		];
	}

	/**
	 * @param array<string,mixed> $input
	 *
	 * @return array{enabled:bool, url:string}
	 */
	private static function sanitize_webhook( array $input ): array {
		// esc_url_raw normalizes + strips dangerous schemes. We then
		// enforce http(s) only — no mailto:, javascript:, etc. sneaking
		// in. An invalid URL is stored as '' so the dispatcher no-ops.
		$url = esc_url_raw( trim( (string) ( $input['url'] ?? '' ) ), [ 'http', 'https' ] );

		return [
			'enabled' => ! empty( $input['enabled'] ),
			'url'     => $url,
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

		// Label weight — only the standard 400 / 500 / 600 / 700 values are
		// accepted; everything else collapses to 500. Keeps the design
		// system tight and avoids loading extra font files for one-off weights.
		$weight = (int) ( $input['label_weight'] ?? 500 );
		if ( ! in_array( $weight, [ 400, 500, 600, 700 ], true ) ) {
			$weight = 500;
		}

		return [
			'firm_name'             => sanitize_text_field( (string) ( $input['firm_name'] ?? '' ) ),
			'colors'                => $out_colors,
			'icon_size'             => max( 12, min( 48, (int) ( $input['icon_size']  ?? 16 ) ) ),
			'label_size'            => max( 8,  min( 20, (int) ( $input['label_size'] ?? 12 ) ) ),
			'label_weight'          => $weight,
			'label_uppercase'       => ! empty( $input['label_uppercase'] ),
			'button_padding_top'    => max( 0, min( 32, (int) ( $input['button_padding_top']    ?? 6 ) ) ),
			'button_padding_right'  => max( 0, min( 32, (int) ( $input['button_padding_right']  ?? 4 ) ) ),
			'button_padding_bottom' => max( 0, min( 32, (int) ( $input['button_padding_bottom'] ?? 6 ) ) ),
			'button_padding_left'   => max( 0, min( 32, (int) ( $input['button_padding_left']   ?? 4 ) ) ),
			'icon_label_gap'        => max( 0, min( 16, (int) ( $input['icon_label_gap']        ?? 2 ) ) ),
			'font_mode'             => $font_mode,
			'google_font'           => $google_font,
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
