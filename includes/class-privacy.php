<?php
/**
 * Privacy notice + opt-out (US opt-out, US + California, Strict opt-in).
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end half of the Privacy tab. Independent of the contact bar: it
 * runs on every front-end page (desktop too) whenever privacy.enabled is on,
 * even with the bar switched off.
 *
 * Everything printed here is identical for every visitor — the inline guard
 * and privacy.js make all decisions in the browser from localStorage and
 * navigator.globalPrivacyControl — so NitroPack / LiteSpeed page caches keep
 * serving one copy of each page.
 */
final class Privacy {

	public const HANDLE = 'bspe-connect-privacy';

	public const PROFILES = [ 'us', 'us_ca', 'strict' ];

	/** Attributes every page optimizer reads as "leave this tag alone". */
	private const OPTIMIZER_EXEMPT = 'nitro-exclude nowprocket data-no-optimize="1" data-noptimize="1" data-no-defer="1" data-cfasync="false"';

	public static function init(): void {
		add_shortcode( 'bspe_privacy_choices', [ self::class, 'choices_link' ] );

		if ( ! self::enabled() ) {
			return;
		}
		// First thing in <head>: the guard must run before any tracker,
		// including GTM snippets other plugins print at priority 0-1.
		add_action( 'wp_head', [ self::class, 'print_guard' ], -9999 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ], 20 );
		add_filter( 'style_loader_tag', [ self::class, 'mark_tag_optimizer_exempt' ], 10, 2 );
		add_filter( 'script_loader_tag', [ self::class, 'mark_tag_optimizer_exempt' ], 10, 2 );
		if ( Settings::get( 'privacy.auto_link', true ) ) {
			add_action( 'wp_footer', [ self::class, 'print_auto_link' ], 110 );
		}
	}

	public static function enabled(): bool {
		return (bool) Settings::get( 'privacy.enabled', false );
	}

	public static function profile(): string {
		$profile = (string) Settings::get( 'privacy.profile', 'us' );
		return in_array( $profile, self::PROFILES, true ) ? $profile : 'us';
	}

	public static function policy_url(): string {
		$url = (string) Settings::get( 'privacy.policy_url', '' );
		if ( '' === $url && function_exists( 'get_privacy_policy_url' ) ) {
			$url = (string) get_privacy_policy_url();
		}
		return $url;
	}

	/**
	 * Pixel-off globs, one per line, each starting with "/".
	 *
	 * @return string[]
	 */
	public static function pixel_off_paths(): array {
		$raw   = (string) Settings::get( 'privacy.pixel_off_paths', '' );
		$globs = [];
		foreach ( preg_split( '/[\r\n,]+/', $raw ) ?: [] as $line ) {
			$line = trim( $line );
			if ( '' !== $line && '/' === $line[0] ) {
				$globs[] = $line;
			}
		}
		return $globs;
	}

	/**
	 * Visitor-facing strings, through gettext so WPML / .po files translate them.
	 *
	 * @return array<string,string>
	 */
	private static function strings(): array {
		return [
			'regionLabel'  => __( 'Privacy choices', 'bspe-connect' ),
			'notice'       => __( 'We use cookies and analytics to improve this site.', 'bspe-connect' ),
			'noticeStrict' => __( "We'd like to use cookies and analytics to improve this site.", 'bspe-connect' ),
			'policy'       => __( 'Privacy Policy', 'bspe-connect' ),
			'dns'          => __( 'Do Not Sell or Share My Personal Information', 'bspe-connect' ),
			'ok'           => __( 'OK', 'bspe-connect' ),
			'optOut'       => __( 'Opt out', 'bspe-connect' ),
			'allow'        => __( 'Allow', 'bspe-connect' ),
			'accept'       => __( 'Accept', 'bspe-connect' ),
			'reject'       => __( 'Reject', 'bspe-connect' ),
			'close'        => __( 'Close', 'bspe-connect' ),
			'closeLabel'   => __( 'Close privacy notice', 'bspe-connect' ),
			'gpc'          => __( "Your browser's Global Privacy Control signal is on, so analytics and ad tracking are off on this site.", 'bspe-connect' ),
			'optedOut'     => __( "You've opted out. Analytics and ad tracking stop from your next page.", 'bspe-connect' ),
			'statusOn'     => __( 'Analytics and ad tracking are on. You can opt out at any time.', 'bspe-connect' ),
			'statusOff'    => __( 'Analytics and ad tracking are off for you on this site.', 'bspe-connect' ),
		];
	}

	/**
	 * The guard, minus its comment lines and indentation. Read once per request.
	 */
	private static function guard_source(): string {
		static $src = null;
		if ( null !== $src ) {
			return $src;
		}
		$raw = (string) file_get_contents( BSPE_CONNECT_DIR . 'public/assets/privacy-guard.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file
		$raw = (string) preg_replace( '#^/\*.*?\*/\s*#s', '', $raw ); // header block
		$out = [];
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '//' ) ) {
				continue;
			}
			$out[] = $line;
		}
		$src = implode( "\n", $out );
		return $src;
	}

	public static function print_guard(): void {
		if ( is_admin() || is_feed() || is_customize_preview() ) {
			return;
		}
		$config = [
			'profile'   => self::profile(),
			'policyUrl' => esc_url_raw( self::policy_url() ),
			'pixelOff'  => self::pixel_off_paths(),
			'i18n'      => self::strings(),
		];
		$json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
		echo '<script id="bspe-privacy-guard" ' . self::OPTIMIZER_EXEMPT . ">\nwindow.BSPE_PRIVACY=" . $json . ";\n" . self::guard_source() . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped; guard is a bundled plugin file
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style( self::HANDLE, BSPE_CONNECT_URL . 'public/assets/privacy.css', [], BSPE_CONNECT_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			BSPE_CONNECT_URL . 'public/assets/privacy.js',
			[],
			BSPE_CONNECT_VERSION,
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);
	}

	public static function mark_tag_optimizer_exempt( string $tag, string $handle ): string {
		if ( self::HANDLE !== $handle || false !== strpos( $tag, 'nitro-exclude' ) ) {
			return $tag;
		}
		$tag = str_replace( '<link ', '<link ' . self::OPTIMIZER_EXEMPT . ' ', $tag );
		return str_replace( '<script ', '<script ' . self::OPTIMIZER_EXEMPT . ' ', $tag );
	}

	/**
	 * [bspe_privacy_choices] — the "Your Privacy Choices" link. Clicks are
	 * caught by the guard (any link ending in #privacy-choices works).
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public static function choices_link( $atts = [] ): string {
		if ( ! self::enabled() ) {
			return '';
		}
		$atts  = shortcode_atts( [ 'label' => __( 'Your Privacy Choices', 'bspe-connect' ) ], is_array( $atts ) ? $atts : [], 'bspe_privacy_choices' );
		$icon  = 'us_ca' === self::profile() ? self::ca_icon() : '';
		return '<a href="#privacy-choices" class="bspe-privacy-choices" rel="nofollow">' . $icon . '<span>' . esc_html( (string) $atts['label'] ) . '</span></a>';
	}

	public static function print_auto_link(): void {
		echo '<p class="bspe-privacy-autolink">' . self::choices_link() . "</p>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts
	}

	/**
	 * California Privacy Protection Agency opt-out icon (blue/white toggle).
	 */
	private static function ca_icon(): string {
		return '<svg viewBox="0 0 30 14" aria-hidden="true" focusable="false"><rect x="0.5" y="0.5" width="29" height="13" rx="6.5" fill="#fff" stroke="#0066ff"/><path d="M7 .5h9.5L12 13.5H7a6.5 6.5 0 0 1 0-13z" fill="#0066ff"/><path d="M4.6 7.1l1.6 1.6 3-3.3" fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.5 4.8l4.4 4.4M23.9 4.8l-4.4 4.4" stroke="#0066ff" stroke-width="1.4" stroke-linecap="round"/></svg>';
	}
}
