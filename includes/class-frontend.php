<?php
/**
 * Frontend orchestration: display rules, asset enqueueing, bar rendering.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the front-of-site lifecycle for the contact bar:
 *   - decides whether the bar should appear on the current view
 *   - enqueues the public CSS/JS only when rendering would happen
 *   - emits inline CSS variables driven by the Design settings
 *   - prints the bar + welcome bubble in wp_footer
 *
 * The bar template itself lives in public/templates/. Forms (Phase 3) and
 * analytics event delivery (Phase 5) hook into the same JS via custom
 * events dispatched from the public bundle.
 */
final class Frontend {

	public const HANDLE = 'bspe-connect';

	/** Cached should_show_bar() result for the current request. */
	private static ?bool $should_show = null;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue_assets' ], 20 );
		add_action( 'wp_head', [ self::class, 'print_inline_css_vars' ], 100 );
		add_action( 'wp_footer', [ self::class, 'render_bar' ], 100 );
	}

	/**
	 * Decides whether the bar should render on the current request. Cached
	 * for the request to avoid re-walking display rules on every hook.
	 */
	public static function should_show_bar(): bool {
		if ( null !== self::$should_show ) {
			return self::$should_show;
		}

		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return self::$should_show = false;
		}

		if ( ! Settings::get( 'enabled', false ) ) {
			return self::$should_show = false;
		}

		if ( ! self::has_any_button_enabled() ) {
			return self::$should_show = false;
		}

		$rules = Settings::get( 'display_rules', [] );
		$mode  = is_array( $rules ) && isset( $rules['mode'] ) ? (string) $rules['mode'] : 'sitewide';
		$slugs = is_array( $rules ) && isset( $rules['slugs'] ) ? self::parse_slugs( (string) $rules['slugs'] ) : [];

		$current_slug = self::current_post_slug();

		switch ( $mode ) {
			case 'sitewide':
				$show = true;
				break;
			case 'pages_only':
				$show = is_page();
				break;
			case 'posts_only':
				$show = is_singular( 'post' );
				break;
			case 'pages_except':
				$show = is_page() && ! in_array( $current_slug, $slugs, true );
				break;
			case 'posts_except':
				$show = is_singular( 'post' ) && ! in_array( $current_slug, $slugs, true );
				break;
			case 'sitewide_except_pages':
				$show = ! ( is_page() && in_array( $current_slug, $slugs, true ) );
				break;
			case 'sitewide_except_posts':
				$show = ! ( is_singular( 'post' ) && in_array( $current_slug, $slugs, true ) );
				break;
			default:
				$show = true;
		}

		/**
		 * Final filter so site code can force-hide the bar on a given route.
		 *
		 * @param bool $show Computed visibility.
		 */
		return self::$should_show = (bool) apply_filters( 'bspe_connect_should_show_bar', $show );
	}

	/**
	 * Parse comma- or newline-separated slug list into trimmed array.
	 *
	 * @return string[]
	 */
	private static function parse_slugs( string $raw ): array {
		$raw = str_replace( [ "\r\n", "\r", "\n" ], ',', $raw );
		$out = [];
		foreach ( explode( ',', $raw ) as $slug ) {
			$slug = trim( $slug );
			if ( '' !== $slug ) {
				$out[] = sanitize_title( $slug );
			}
		}
		return $out;
	}

	private static function current_post_slug(): string {
		$id = get_queried_object_id();
		if ( ! $id ) {
			return '';
		}
		$slug = get_post_field( 'post_name', $id );
		return is_string( $slug ) ? $slug : '';
	}

	private static function has_any_button_enabled(): bool {
		foreach ( [ 'connect', 'call', 'text', 'email' ] as $key ) {
			if ( Settings::get( "buttons.{$key}.enabled", false ) ) {
				return true;
			}
		}
		return false;
	}

	public static function maybe_enqueue_assets(): void {
		if ( ! self::should_show_bar() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			BSPE_CONNECT_URL . 'public/assets/bspe-connect.css',
			[],
			BSPE_CONNECT_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			BSPE_CONNECT_URL . 'public/assets/bspe-connect.js',
			[],
			BSPE_CONNECT_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'BSPE_CONNECT_DATA',
			[
				'showDelay'        => (int) Settings::get( 'display.show_delay', 3 ),
				'mobileBreakpoint' => (int) Settings::get( 'display.mobile_breakpoint', 768 ),
				'ajaxUrl'          => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'bubble'           => [
					'enabled' => (bool) Settings::get( 'welcome_bubble.enabled', true ),
					'trigger' => (string) Settings::get( 'welcome_bubble.trigger', 'auto' ),
					'delay'   => (int) Settings::get( 'welcome_bubble.delay', 3 ),
					'repeat'  => (string) Settings::get( 'welcome_bubble.repeat', 'session' ),
				],
				'restEndpoint'     => esc_url_raw( rest_url( 'bspe-connect/v1/event' ) ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
			]
		);

		// Google Font enqueue if the design setting requests one.
		if ( 'google' === Settings::get( 'design.font_mode', 'inherit' ) ) {
			$font = (string) Settings::get( 'design.google_font', 'DM Sans' );
			if ( '' !== $font ) {
				$font_url = sprintf(
					'https://fonts.googleapis.com/css2?family=%s:wght@400;500;600;700&display=swap',
					rawurlencode( $font )
				);
				wp_enqueue_style( 'bspe-connect-font', $font_url, [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}
		}

		// Cloudflare Turnstile widget — only when both flag and key are configured.
		$turnstile_enabled  = (bool) Settings::get( 'form.antispam.turnstile_enabled', false );
		$turnstile_site_key = (string) Settings::get( 'form.antispam.turnstile_site_key', '' );
		if ( $turnstile_enabled && '' !== $turnstile_site_key ) {
			wp_enqueue_script(
				'bspe-connect-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				[],
				null,  // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				[ 'in_footer' => true, 'strategy' => 'defer' ]
			);
		}

		// Icon library CDNs — only enqueue what the configured buttons actually need.
		self::enqueue_icon_libraries();
	}

	/**
	 * Walk the configured buttons and enqueue the CDN style/script for each
	 * unique third-party icon library in use. Brand icons are bundled —
	 * nothing to load for those.
	 */
	private static function enqueue_icon_libraries(): void {
		// Only Font Awesome is supported (v2.1.4+). Enqueue the CDN if any
		// configured button uses fa-solid or fa-regular.
		$needs_fa = false;
		foreach ( [ 'connect', 'call', 'text', 'email' ] as $key ) {
			if ( ! Settings::get( "buttons.{$key}.enabled", false ) ) {
				continue;
			}
			$lib = (string) Settings::get( "buttons.{$key}.icon_library", 'fa-solid' );
			if ( 0 === strpos( $lib, 'fa-' ) ) {
				$needs_fa = true;
				break;
			}
		}

		if ( $needs_fa ) {
			wp_enqueue_style(
				'bspe-connect-fa',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
				[],
				'6.5.0'
			);
		}
	}

	/**
	 * Emits CSS custom properties driven by Design settings. Goes in
	 * wp_head so values are applied before the bar renders in the footer.
	 */
	public static function print_inline_css_vars(): void {
		if ( ! self::should_show_bar() ) {
			return;
		}

		$colors = Settings::get( 'design.colors', [] );
		$colors = is_array( $colors ) ? $colors : [];
		$pairs  = [
			'--bspe-bar-bg'    => self::sanitize_color( $colors['bar_bg'] ?? '#351E28' ),
			'--bspe-button-bg' => self::sanitize_color( $colors['button_bg'] ?? '#351E28' ),
			'--bspe-button-fg' => self::sanitize_color( $colors['button_fg'] ?? '#FAF7F2' ),
			'--bspe-bubble-bg' => self::sanitize_color( $colors['bubble_bg'] ?? '#FAF7F2' ),
			'--bspe-bubble-fg' => self::sanitize_color( $colors['bubble_fg'] ?? '#351E28' ),
			'--bspe-accent'    => self::sanitize_color( $colors['accent'] ?? '#3AAFB9' ),
		];

		$breakpoint = (int) Settings::get( 'display.mobile_breakpoint', 768 );

		// Font family
		$font_family = '';
		if ( 'google' === Settings::get( 'design.font_mode', 'inherit' ) ) {
			$font        = (string) Settings::get( 'design.google_font', 'DM Sans' );
			$font_family = sprintf( '"%s", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', esc_attr( $font ) );
		}

		$icon_size  = max( 12, min( 48, (int) Settings::get( 'design.icon_size', 16 ) ) );
		$label_size = max( 8,  min( 20, (int) Settings::get( 'design.label_size', 12 ) ) );

		$label_weight = (int) Settings::get( 'design.label_weight', 500 );
		if ( ! in_array( $label_weight, [ 400, 500, 600, 700 ], true ) ) {
			$label_weight = 500;
		}
		$label_transform = Settings::get( 'design.label_uppercase', true ) ? 'uppercase' : 'none';
		// Per-side button padding. If only the legacy button_padding_y is
		// stored (pre-v2.2.0), use it for top + bottom and apply 4px to
		// the horizontal sides.
		$pad_legacy_y    = (int) Settings::get( 'design.button_padding_y', -1 );
		$default_top     = $pad_legacy_y >= 0 ? $pad_legacy_y : 6;
		$default_bottom  = $pad_legacy_y >= 0 ? $pad_legacy_y : 6;
		$pad_top         = max( 0, min( 32, (int) Settings::get( 'design.button_padding_top',    $default_top ) ) );
		$pad_right       = max( 0, min( 32, (int) Settings::get( 'design.button_padding_right',  4 ) ) );
		$pad_bottom      = max( 0, min( 32, (int) Settings::get( 'design.button_padding_bottom', $default_bottom ) ) );
		$pad_left        = max( 0, min( 32, (int) Settings::get( 'design.button_padding_left',   4 ) ) );
		$icon_label_gap  = max( 0, min( 16, (int) Settings::get( 'design.icon_label_gap', 2 ) ) );

		echo "<style id=\"bspe-connect-vars\">\n";
		echo ":root, .bspe-connect, #bspe-connect {\n";
		foreach ( $pairs as $name => $value ) {
			echo "\t" . esc_html( $name ) . ': ' . esc_html( $value ) . ";\n"; // phpcs:ignore Squiz.PHP.EmbeddedPhp.SpacingBeforeOpen
		}
		echo "\t--bspe-icon-size: " . esc_html( (string) $icon_size ) . "px;\n";
		echo "\t--bspe-label-size: " . esc_html( (string) $label_size ) . "px;\n";
		echo "\t--bspe-label-weight: " . esc_html( (string) $label_weight ) . ";\n";
		echo "\t--bspe-label-transform: " . esc_html( $label_transform ) . ";\n";
		echo "\t--bspe-button-padding-top: "    . esc_html( (string) $pad_top )    . "px;\n";
		echo "\t--bspe-button-padding-right: "  . esc_html( (string) $pad_right )  . "px;\n";
		echo "\t--bspe-button-padding-bottom: " . esc_html( (string) $pad_bottom ) . "px;\n";
		echo "\t--bspe-button-padding-left: "   . esc_html( (string) $pad_left )   . "px;\n";
		echo "\t--bspe-icon-label-gap: " . esc_html( (string) $icon_label_gap ) . "px;\n";
		if ( '' !== $font_family ) {
			echo "\t--bspe-font-family: " . wp_kses_post( $font_family ) . ";\n";
		}
		echo "}\n";

		// Per-button label overrides — only emit a rule if the user has
		// set a non-empty value on that button. Each rule scopes the
		// CSS variables to a specific button class so the existing
		// .bspe-connect__btn rule reads the per-button value.
		foreach ( [ 'connect', 'call', 'text', 'email' ] as $btn_key ) {
			$btn_weight    = (string) Settings::get( "buttons.{$btn_key}.label_weight",    '' );
			$btn_uppercase = (string) Settings::get( "buttons.{$btn_key}.label_uppercase", '' );

			$declarations = [];
			if ( in_array( $btn_weight, [ '400', '500', '600', '700' ], true ) ) {
				$declarations[] = '--bspe-label-weight: ' . esc_html( $btn_weight );
			}
			if ( 'yes' === $btn_uppercase ) {
				$declarations[] = '--bspe-label-transform: uppercase';
			} elseif ( 'no' === $btn_uppercase ) {
				$declarations[] = '--bspe-label-transform: none';
			}

			if ( ! empty( $declarations ) ) {
				echo "#bspe-connect .bspe-connect__btn--" . esc_html( $btn_key ) . " {\n";
				foreach ( $declarations as $decl ) {
					echo "\t" . $decl . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- decl values are escaped above
				}
				echo "}\n";
			}
		}

		// Hide AT or above the configured breakpoint. Setting "768" means
		// "viewports 768px and wider don't see the bar" — matching common
		// Bootstrap-style breakpoint conventions where the value is the
		// first non-mobile width.
		echo '@media (min-width: ' . esc_html( (string) $breakpoint ) . "px) {\n";
		echo "\t.bspe-connect { display: none !important; }\n";
		echo "}\n";
		echo "</style>\n";
	}

	/**
	 * Reduce a user-supplied color to a safe `#RRGGBB` (or rgba(...)) value.
	 * Falls back to the provided default when the input is unrecognized.
	 */
	private static function sanitize_color( string $color, string $fallback = '#351E28' ): string {
		$color = trim( $color );
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color ) ) {
			return $color;
		}
		if ( preg_match( '/^rgba?\(\s*\d+(\s*,\s*\d+){2,3}(\s*,\s*[\d.]+)?\s*\)$/i', $color ) ) {
			return $color;
		}
		return $fallback;
	}

	public static function render_bar(): void {
		if ( ! self::should_show_bar() ) {
			return;
		}

		$buttons        = self::resolve_button_list();
		$bubble_enabled = (bool) Settings::get( 'welcome_bubble.enabled', true );
		$bubble         = self::resolve_bubble_data();
		$pointer_pos    = self::compute_pointer_position( $buttons );

		require BSPE_CONNECT_DIR . 'public/templates/bar.php';
	}

	/**
	 * Build an ordered list of enabled buttons with everything the template
	 * needs to render. Order is fixed: connect, call, text, email.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private static function resolve_button_list(): array {
		$out      = [];
		$settings = Settings::get( 'buttons', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		foreach ( [ 'connect', 'call', 'text', 'email' ] as $key ) {
			$cfg = $settings[ $key ] ?? [];
			if ( ! ( $cfg['enabled'] ?? false ) ) {
				continue;
			}

			[ $icon_library, $icon_key ] = self::migrate_icon_settings(
				(string) ( $cfg['icon_library'] ?? 'fa-solid' ),
				(string) ( $cfg['icon'] ?? '' ),
				$key
			);

			$entry = [
				'key'          => $key,
				'label'        => (string) ( $cfg['label'] ?? ucfirst( $key ) ),
				'icon_library' => $icon_library,
				'icon'         => $icon_key,
				'icon_url'     => '',
				'href'         => '#',
				'tag'          => 'button',
				'attrs'        => [],
				'mode'         => '',
			];

			switch ( $key ) {
				case 'connect':
					// Image mode removed in v2.1.2 — Connect is label + optional library icon only.
					break;
				case 'call':
					$phone = self::digits( (string) ( $cfg['phone'] ?? '' ) );
					if ( strlen( $phone ) === 10 ) {
						$entry['tag']  = 'a';
						$entry['href'] = 'tel:+1' . $phone;
					}
					break;
				case 'text':
					$entry['mode'] = (string) ( $cfg['mode'] ?? 'sms' );
					$phone         = self::digits( (string) ( $cfg['phone'] ?? '' ) );
					if ( 'sms' === $entry['mode'] && strlen( $phone ) === 10 ) {
						$entry['tag']  = 'a';
						$entry['href'] = 'sms:+1' . $phone;
					}
					break;
				case 'email':
					// Always opens the modal (Phase 3 wires in form rendering).
					break;
			}

			$out[] = $entry;
		}
		return $out;
	}

	private static function digits( string $value ): string {
		return preg_replace( '/\D/', '', $value ) ?? '';
	}

	/**
	 * Migrate pre-v2.1.4 icon settings (brand, ion-*, dripicons) to a
	 * supported library + name on the fly so existing client configs
	 * keep rendering after upgrade until the next save.
	 *
	 * @return array{0:string,1:string}
	 */
	private static function migrate_icon_settings( string $library, string $icon, string $type ): array {
		$library = strtolower( trim( $library ) );
		$icon    = trim( $icon );

		// "none" stays as-is — no icon rendered.
		if ( 'none' === $library ) {
			return [ 'none', '' ];
		}

		if ( 'fa-solid' === $library || 'fa-regular' === $library ) {
			if ( '' === $icon ) {
				$icon = self::default_fa_icon_for_type( $type );
			}
			return [ $library, $icon ];
		}

		// Legacy library values — map to fa-solid + a sensible icon.
		$legacy_map = [
			'connect-1' => 'comments',
			'connect-2' => 'comment-dots',
			'connect-3' => 'message',
			'call-1'    => 'phone',
			'call-2'    => 'mobile',
			'call-3'    => 'phone-volume',
			'text-1'    => 'comment-dots',
			'text-2'    => 'comments',
			'text-3'    => 'message',
			'email-1'   => 'envelope',
			'email-2'   => 'envelope-open',
			'email-3'   => 'at',
		];

		if ( isset( $legacy_map[ $icon ] ) ) {
			return [ 'fa-solid', $legacy_map[ $icon ] ];
		}

		// Anything we can't map → button-type default.
		return [ 'fa-solid', self::default_fa_icon_for_type( $type ) ];
	}

	private static function default_fa_icon_for_type( string $type ): string {
		switch ( $type ) {
			case 'connect': return 'comments';
			case 'call':    return 'phone';
			case 'text':    return 'comment-dots';
			case 'email':   return 'envelope';
		}
		return 'phone';
	}

	private static function icon_url( string $icon_key, string $type ): string {
		$icon_key = preg_replace( '/[^a-z0-9-]/i', '', $icon_key ) ?? '';
		$path     = BSPE_CONNECT_DIR . 'public/assets/icons/' . $icon_key . '.svg';
		if ( file_exists( $path ) ) {
			return BSPE_CONNECT_URL . 'public/assets/icons/' . $icon_key . '.svg';
		}
		// Fallback to {type}-1.svg.
		$fallback = BSPE_CONNECT_DIR . 'public/assets/icons/' . $type . '-1.svg';
		if ( file_exists( $fallback ) ) {
			return BSPE_CONNECT_URL . 'public/assets/icons/' . $type . '-1.svg';
		}
		return '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function resolve_bubble_data(): array {
		$bubble    = Settings::get( 'welcome_bubble', [] );
		$bubble    = is_array( $bubble ) ? $bubble : [];
		$firm_name = (string) Settings::get( 'design.firm_name', '' );
		if ( '' === $firm_name ) {
			$firm_name = (string) get_bloginfo( 'name' );
		}

		$heading = (string) ( $bubble['heading'] ?? '' );
		$message = (string) ( $bubble['message'] ?? '' );

		// Apply template variables. Kept narrow so server never reflects user input.
		$replacements = [
			'{firm_name}' => $firm_name,
			'{site_name}' => (string) get_bloginfo( 'name' ),
		];
		$heading = strtr( $heading, $replacements );
		$message = strtr( $message, $replacements );

		$avatar_url = '';
		if ( ! empty( $bubble['show_avatar'] ) && ! empty( $bubble['avatar_id'] ) ) {
			$avatar_url = (string) wp_get_attachment_image_url( (int) $bubble['avatar_id'], 'thumbnail' );
		}

		return [
			'heading'    => $heading,
			'message'    => $message,
			'avatar_url' => $avatar_url,
		];
	}

	/**
	 * Returns a percentage (e.g. "12.5%") for the bubble triangle pointer
	 * based on Connect's index in the enabled-buttons list.
	 *
	 * @param array<int, array<string,mixed>> $buttons
	 */
	private static function compute_pointer_position( array $buttons ): string {
		$count   = max( 1, count( $buttons ) );
		$index   = -1;
		foreach ( $buttons as $i => $b ) {
			if ( ( $b['key'] ?? '' ) === 'connect' ) {
				$index = $i;
				break;
			}
		}
		if ( $index < 0 ) {
			return '50%'; // Connect not enabled — pointer hidden via template.
		}
		$pos = ( $index + 0.5 ) / $count * 100;
		return rtrim( rtrim( number_format( $pos, 2, '.', '' ), '0' ), '.' ) . '%';
	}
}
