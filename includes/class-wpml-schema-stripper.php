<?php
/**
 * Strip JSON-LD schema from translated-language pages (WPML).
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * SEOPress (and similar) custom schemas are written once, in the site's
 * default language. WPML then serves the SAME English JSON-LD on every
 * Spanish page, which is wrong structured data for that URL. This
 * utility buffers wp_head on pages in the configured strip languages
 * (Spanish by default) and removes the UNMARKED
 * <script type="application/ld+json"> blocks from it: tags with no
 * id/class attribute, which is how SEOPress outputs manual schemas.
 * Blocks tagged by their generator (like the theme's
 * <script id="website-schema">) survive.
 *
 * Allow list: individual translated pages that DO have their own
 * correct schema can be exempted by ID (utilities.wpml_schema_allow_ids,
 * a comma separated list managed in General, Site utilities).
 *
 * Toggle: utilities.wpml_strip_schema (default OFF). When WPML is
 * active and the toggle is off, an admin-wide notice suggests turning
 * it on (see Admin::render_wpml_schema_notice).
 */
final class WPML_Schema_Stripper {

	/**
	 * Output-buffer nesting level captured when our buffer opened, so
	 * the closing hook can unwind exactly to it even if a plugin in
	 * between opened a buffer and never closed it.
	 *
	 * @var int|null Null when not buffering.
	 */
	private static ?int $ob_level = null;

	public static function init(): void {
		if ( ! (bool) Settings::get( 'utilities.wpml_strip_schema', false ) ) {
			return;
		}
		// Extreme priorities so the buffer wraps EVERYTHING other
		// plugins print into wp_head, whatever their own priorities.
		add_action( 'wp_head', [ self::class, 'start_buffer' ], -99999 );
		add_action( 'wp_head', [ self::class, 'strip_and_flush' ], 99999 );
	}

	/**
	 * Language codes the stripper acts on. Managed in Site utilities
	 * (utilities.wpml_schema_strip_langs, comma separated); falls back
	 * to Spanish when the field is empty, matching the original
	 * SEOPress-writes-English-only problem this exists for.
	 *
	 * @return string[] Lowercase codes, e.g. ['es'] or ['es', 'pt-br'].
	 */
	public static function strip_langs(): array {
		$raw   = strtolower( (string) Settings::get( 'utilities.wpml_schema_strip_langs', 'es' ) );
		$langs = [];
		foreach ( preg_split( '/[\s,]+/', $raw ) ?: [] as $piece ) {
			if ( preg_match( '/^[a-z]{2}(-[a-z0-9]{2,8})?$/', $piece ) ) {
				$langs[] = $piece;
			}
		}
		return $langs ? array_values( array_unique( $langs ) ) : [ 'es' ];
	}

	/**
	 * True when the current frontend request is a page in one of the
	 * configured strip languages (Spanish by default) whose schema
	 * should be stripped.
	 */
	public static function should_strip(): bool {
		if ( is_admin() ) {
			return false;
		}
		// Current WPML language ('en', 'es', ...). Null if WPML inactive.
		$lang = apply_filters( 'wpml_current_language', null );
		if ( null === $lang || '' === (string) $lang ) {
			return false;
		}
		if ( ! in_array( strtolower( (string) $lang ), self::strip_langs(), true ) ) {
			return false;
		}
		// Safety: never strip the site's DEFAULT language, even if it
		// was (mis)listed in the strip languages. The stripper exists
		// for translations carrying the default language's schema, not
		// for the original pages.
		$default = apply_filters( 'wpml_default_language', null );
		if ( null !== $default && '' !== (string) $default && strtolower( (string) $lang ) === strtolower( (string) $default ) ) {
			return false;
		}
		// Translated pages allowed to keep their own schema output.
		if ( in_array( (int) get_queried_object_id(), self::allowed_ids(), true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Parse the admin-managed allow list into post IDs.
	 *
	 * @return int[]
	 */
	public static function allowed_ids(): array {
		$raw = (string) Settings::get( 'utilities.wpml_schema_allow_ids', '' );
		if ( '' === trim( $raw ) ) {
			return [];
		}
		$ids = [];
		foreach ( preg_split( '/[\s,]+/', $raw ) ?: [] as $piece ) {
			$id = (int) $piece;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether a specific post would have its schema stripped (used by
	 * the admin warnings on the post edit screen). True when the
	 * feature is on, the post's WPML language is one of the configured
	 * strip languages (and not the site default), and the post is not
	 * on the allow list.
	 */
	public static function post_gets_stripped( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( ! (bool) Settings::get( 'utilities.wpml_strip_schema', false ) ) {
			return false;
		}
		if ( ! WPML_Status::wpml_active() ) {
			return false;
		}
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		if ( ! is_array( $details ) || empty( $details['language_code'] ) ) {
			return false;
		}
		$lang = strtolower( (string) $details['language_code'] );
		if ( ! in_array( $lang, self::strip_langs(), true ) ) {
			return false;
		}
		$default = apply_filters( 'wpml_default_language', null );
		if ( null !== $default && '' !== (string) $default && $lang === strtolower( (string) $default ) ) {
			return false;
		}
		return ! in_array( $post_id, self::allowed_ids(), true );
	}

	public static function start_buffer(): void {
		if ( ! self::should_strip() ) {
			return;
		}
		self::$ob_level = ob_get_level();
		ob_start();
	}

	public static function strip_and_flush(): void {
		if ( null === self::$ob_level ) {
			return;
		}
		$level          = self::$ob_level;
		self::$ob_level = null;
		// Unwind any buffer a plugin opened inside wp_head and never
		// closed, so ob_get_clean() below grabs OUR buffer, not theirs.
		while ( ob_get_level() > $level + 1 ) {
			ob_end_flush();
		}
		$head = (string) ob_get_clean();
		// Strip only UNMARKED JSON-LD blocks: script tags whose opening
		// tag carries no id= and no class= attribute. SEOPress prints
		// manual schemas (the Custom data type included) as bare
		// <script type="application/ld+json"> with no identifying
		// attribute, while schemas that should survive are tagged by
		// their generators (e.g. the theme's <script id="website-schema">).
		// The negative lookahead scans the whole opening tag, so the
		// attribute order does not matter.
		echo preg_replace(
			'#<script(?![^>]*\b(?:id|class)\s*=)[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>#si',
			'',
			$head
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- re-emitting already-escaped head markup minus JSON-LD blocks
		echo "\n<!-- bspe-noschema active -->\n";
	}
}
