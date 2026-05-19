<?php
/**
 * In-Post Widget — inject a saved shortcode into post content.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the_content filter and splices a saved shortcode's rendered
 * output into the post HTML at a specific anchor point:
 *
 *   1. Find the first heading in the post body (h2-h6 — h1 isn't used
 *      in WP post bodies; the post title takes that role).
 *   2. Scan the slice of content BEFORE that heading for an <iframe>.
 *      If found, the widget goes right before the iframe — useful for
 *      articles that open with a video embed where the CTA needs to
 *      sit above the player.
 *   3. Otherwise, the widget goes right before the heading.
 *   4. Fallback (no heading at all in the post body): place the widget
 *      after the Nth paragraph (configurable via
 *      in_post_widget.fallback_after_paragraph, default 1). If the
 *      post has fewer paragraphs than that, append at the end.
 *
 * Posts-only — pages, attachments, and custom post types are never
 * touched. The admin can blacklist specific post IDs via the
 * exclude_ids setting.
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
		// the heading / iframe pattern matching is reliable.
		add_filter( 'the_content', [ self::class, 'inject' ], 11 );
	}

	public static function inject( string $content ): string {
		// Posts only. is_singular('post') is the cleanest gate — it
		// rejects pages, archives, attachments, and every custom post
		// type in a single call.
		if ( ! is_singular( 'post' ) ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
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

		$shortcode = trim( (string) Settings::get( 'in_post_widget.shortcode', '' ) );
		if ( '' === $shortcode ) {
			return $content;
		}

		// Wrap the rendered shortcode in a div with the configured
		// bottom margin baked in as an inline style. Theme CSS rules
		// can still override via #wrapper-id selectors if needed.
		$margin   = (int) Settings::get( 'in_post_widget.margin_bottom_px', 20 );
		$margin   = max( 0, min( 200, $margin ) );
		$rendered = do_shortcode( $shortcode );
		$rendered = sprintf(
			'<div class="bspe-in-post-widget" style="margin-bottom: %dpx;">%s</div>',
			$margin,
			$rendered
		);

		// Find the first heading (h2 through h6). Post bodies in
		// WordPress don't use h1 — the post title owns h1.
		if ( preg_match( '/<h[2-6]\b[^>]*>/i', $content, $h_match, PREG_OFFSET_CAPTURE ) ) {
			$heading_pos = (int) $h_match[0][1];

			// Look for an iframe in the slice BEFORE the first heading.
			$preamble  = substr( $content, 0, $heading_pos );
			$insert_at = $heading_pos;
			if ( preg_match( '/<iframe\b[^>]*>/i', $preamble, $iframe_match, PREG_OFFSET_CAPTURE ) ) {
				$insert_at = (int) $iframe_match[0][1];
			}
			return substr( $content, 0, $insert_at ) . $rendered . substr( $content, $insert_at );
		}

		// Fallback path: no heading anywhere in the post body. Drop the
		// widget after the Nth paragraph (configurable). If the post
		// has fewer paragraphs than that, append at the end.
		$fallback_n = (int) Settings::get( 'in_post_widget.fallback_after_paragraph', 1 );
		$fallback_n = max( 1, min( 10, $fallback_n ) );

		$pos    = false;
		$offset = 0;
		for ( $i = 0; $i < $fallback_n; $i++ ) {
			$found = stripos( $content, '</p>', $offset );
			if ( false === $found ) {
				$pos = false;
				break;
			}
			$pos    = $found + 4; // length of `</p>`
			$offset = $pos;
		}
		if ( false === $pos ) {
			// Not enough paragraphs — last-resort append.
			return $content . $rendered;
		}
		return substr( $content, 0, $pos ) . $rendered . substr( $content, $pos );
	}
}
