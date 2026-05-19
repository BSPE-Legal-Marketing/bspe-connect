<?php
/**
 * In-Post Widget — inject a saved shortcode into post content.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the_content filter, finds the Nth `</p>` in the rendered HTML,
 * and splices a saved shortcode's rendered output after it. Common
 * pattern for "in-content CTA" placement — typically the highest-
 * conversion-rate spot in a long-form article.
 *
 * Settings shape (see Settings::defaults().in_post_widget):
 *   enabled          bool
 *   shortcode        string  — raw shortcode, e.g. [elementor-template id="123"]
 *   after_paragraph  int     — 1..10 (1 = after the first <p>)
 *   post_types       array   — ['post'] by default; admin can add 'page'
 *   exclude_ids      string  — comma-separated post IDs to skip
 *
 * Toggle:  in_post_widget.enabled  (default OFF)
 * Gated:   only runs when Licensing::is_functional() — same as the
 *          other site utilities (called from Plugin::boot inside the
 *          $licensed block).
 */
final class In_Post_Widget {

	public static function init(): void {
		if ( ! (bool) Settings::get( 'in_post_widget.enabled', false ) ) {
			return;
		}
		$shortcode = trim( (string) Settings::get( 'in_post_widget.shortcode', '' ) );
		if ( '' === $shortcode ) {
			return;
		}

		// Priority 11 puts us just after WP's default the_content
		// processing (priority 10 = wpautop, do_shortcode). That way
		// our injection happens on the already-paragraphed HTML so
		// counting `</p>` is reliable.
		add_filter( 'the_content', [ self::class, 'inject' ], 11 );
	}

	/**
	 * Inject the shortcode output after the configured paragraph.
	 *
	 * The guards mirror what every "in-content widget" plugin does:
	 *  - only on singular templates (post / page detail view)
	 *  - only on the main query, in the loop
	 *  - only on allowed post types
	 *  - skip excluded post IDs
	 *  - find the Nth `</p>`; fall back to "append at end" if there
	 *    aren't enough paragraphs (very short articles)
	 */
	public static function inject( string $content ): string {
		if ( ! is_singular() ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		// Post-type allowlist
		$allowed_types = Settings::get( 'in_post_widget.post_types', [ 'post' ] );
		if ( ! is_array( $allowed_types ) ) {
			$allowed_types = [ 'post' ];
		}
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return $content;
		}

		// Excluded post IDs
		$exclude_raw = (string) Settings::get( 'in_post_widget.exclude_ids', '' );
		if ( '' !== $exclude_raw ) {
			$exclude_ids = array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $exclude_raw ) ?: [] ) );
			if ( in_array( (int) $post->ID, $exclude_ids, true ) ) {
				return $content;
			}
		}

		$after_n = max( 1, min( 10, (int) Settings::get( 'in_post_widget.after_paragraph', 1 ) ) );
		$shortcode = trim( (string) Settings::get( 'in_post_widget.shortcode', '' ) );
		if ( '' === $shortcode ) {
			return $content;
		}

		$rendered = do_shortcode( $shortcode );
		$rendered = '<div class="bspe-in-post-widget">' . $rendered . '</div>';

		// Find the position right after the Nth `</p>`.
		$offset = 0;
		$pos    = false;
		for ( $i = 0; $i < $after_n; $i++ ) {
			$found = stripos( $content, '</p>', $offset );
			if ( false === $found ) {
				$pos = false;
				break;
			}
			$pos    = $found + 4; // length of `</p>`
			$offset = $pos;
		}

		if ( false === $pos ) {
			// Not enough paragraphs in this article — append at the
			// end rather than failing silently.
			return $content . $rendered;
		}

		return substr( $content, 0, $pos ) . $rendered . substr( $content, $pos );
	}
}
