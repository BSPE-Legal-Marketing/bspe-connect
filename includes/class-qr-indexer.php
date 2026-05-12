<?php
/**
 * QR indexer — append a QR code under every post / page that encodes
 * the current permalink.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the_content filter to append a <p id="qri-code"> wrapper
 * carrying the permalink. The vendored JS encoder reads the URL +
 * size off the wrapper and renders an inline SVG QR client-side —
 * no external HTTP, no iframe, no GD requirement.
 *
 * Toggle:    utilities.qr_indexer        (default ON)
 * Size:      utilities.qr_size_px        (default 150, range 80-400)
 * Max width: utilities.qr_max_width_px   (default 1240, range 320-2400)
 */
final class QR_Indexer {

	public const HANDLE_CSS = 'bspe-connect-qr-indexer';
	public const HANDLE_JS  = 'bspe-connect-qr-indexer';

	public static function init(): void {
		if ( ! (bool) Settings::get( 'utilities.qr_indexer', true ) ) {
			return;
		}

		add_filter( 'the_content', [ self::class, 'append_qr' ], 99 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Append the QR wrapper to the post body. Two guards keep this from
	 * firing where it shouldn't:
	 *   - is_singular() — we only want the QR on standalone post / page
	 *     views, not on archive / category / search index pages.
	 *   - in_the_loop() + main query — defends against shortcode / widget
	 *     renders of post content that would otherwise duplicate the QR.
	 */
	public static function append_qr( string $content ): string {
		if ( ! is_singular() ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$url = get_permalink();
		if ( ! $url ) {
			return $content;
		}
		$size = (int) Settings::get( 'utilities.qr_size_px', 150 );
		$size = max( 80, min( 400, $size ) );

		$wrapper = sprintf(
			'<p id="qri-code" data-bspe-qri data-url="%s" data-size="%d"></p>',
			esc_url( $url ),
			$size
		);
		return $content . $wrapper;
	}

	public static function enqueue_assets(): void {
		// Only on singular templates — no point loading the JS on
		// archives where the QR wrapper isn't injected.
		if ( ! is_singular() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE_CSS,
			BSPE_CONNECT_URL . 'public/assets/qr-indexer.css',
			[],
			BSPE_CONNECT_VERSION
		);

		// Emit the max-width as a CSS custom property — keeps the
		// stylesheet static while letting the WP setting drive layout.
		$max_width = (int) Settings::get( 'utilities.qr_max_width_px', 1240 );
		$max_width = max( 320, min( 2400, $max_width ) );
		wp_add_inline_style(
			self::HANDLE_CSS,
			'#qri-code { --bspe-qri-max-width: ' . $max_width . 'px; }'
		);

		wp_enqueue_script(
			self::HANDLE_JS,
			BSPE_CONNECT_URL . 'public/assets/qr-indexer.js',
			[],
			BSPE_CONNECT_VERSION,
			true
		);
	}
}
