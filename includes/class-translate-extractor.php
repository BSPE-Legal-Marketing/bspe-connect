<?php
/**
 * Turns a post into translatable text segments, and writes translated
 * segments back. Pure logic: the only WP calls are in the two thin
 * wrappers extract() / apply(); everything else works on plain arrays so
 * it can be exercised by tests/translate-extractor-test.php.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Segment ids are stable strings that encode where a text lives:
 *
 *   title                       post_title
 *   slug                        post_name, decoded, hyphens as spaces
 *   excerpt                     post_excerpt
 *   content:N                   Nth run of post_content (long HTML is split)
 *   el:<widgetId>:<path>[:N]    Elementor setting <path> inside _elementor_data
 *   meta:<key>[:N]              translatable postmeta (SEOPress + WPML fields)
 *
 * A run suffix :N means the source value was longer than the chunk limit
 * and was split at block boundaries; apply() joins the runs back in order.
 */
final class Translate_Extractor {

	/** Max characters of one segment before HTML is split at block ends. */
	public const CHUNK_LIMIT = 6000;

	/**
	 * Elementor setting keys that hold visitor-facing text. Mirrors the
	 * fields Elementor itself declares translatable in its WPML config.
	 * Unknown keys are never touched, so layout / link / style settings
	 * survive verbatim.
	 */
	public const ELEMENTOR_TEXT_KEYS = [
		'title', 'text', 'editor', 'description', 'button_text', 'alt', 'caption',
		'tab_title', 'tab_content', 'title_text', 'description_text', 'heading',
		'sub_heading', 'label', 'placeholder', 'link_text', 'testimonial_content',
		'testimonial_name', 'testimonial_job', 'before_text', 'highlighted_text',
		'rotating_text', 'after_text', 'field_label', 'field_html', 'field_options',
		'button_text_prev', 'button_text_next', 'success_message', 'error_message',
		'required_field_message', 'invalid_message', 'html', 'content', 'subtitle',
		'sub_title', 'item_title', 'item_description', 'inner_text', 'prefix',
		'suffix', 'ribbon_title', 'price', 'period', 'footer_additional_info',
		'read_more_text', 'blockquote_content', 'author_name', 'anchor_text',
		'text_prefix', 'text_next', 'text_prev', 'no_posts_message', 'more_text',
		'less_text', 'video_title', 'form_name', 'step_next_label', 'step_previous_label',
		'title_prefix', 'title_suffix',
	];

	/** SEOPress per-post fields WPML's SEOPress integration translates. */
	public const SEOPRESS_META = [
		'_seopress_titles_title',
		'_seopress_titles_desc',
		'_seopress_social_fb_title',
		'_seopress_social_fb_desc',
		'_seopress_social_twitter_title',
		'_seopress_social_twitter_desc',
	];

	/**
	 * Postmeta never copied to the translation: caches, locks, WPML's
	 * own bookkeeping, and the fields we rebuild ourselves.
	 */
	private const META_NEVER_COPY = [
		'_elementor_css',
		'_elementor_inline_svg',
		'_elementor_element_cache',
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
		'_icl_lang_duplicate_of',
		'_wpml_media_duplicate',
		'_wpml_media_featured',
		'_wpml_word_count',
		'_wpml_location_migration_done',
		'_last_translation_edit_mode',
	];

	/* -----------------------------------------------------------------
	 * WP-facing wrappers
	 * ----------------------------------------------------------------- */

	/**
	 * @return array{segments: array<string,string>, map: array<string,mixed>}
	 */
	public static function extract( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [ 'segments' => [], 'map' => [] ];
		}
		$meta = [];
		foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
			$meta[ $key ] = is_array( $values ) ? ( $values[0] ?? '' ) : $values;
		}
		return self::extract_from(
			[
				'post_title'   => (string) $post->post_title,
				'post_name'    => (string) $post->post_name,
				'post_excerpt' => (string) $post->post_excerpt,
				'post_content' => (string) $post->post_content,
			],
			$meta,
			self::wpml_translatable_meta_keys()
		);
	}

	/**
	 * Custom-field keys WPML is configured to translate (value 2 in the
	 * translation-management setting). SEOPress keys are always included.
	 *
	 * @return string[]
	 */
	public static function wpml_translatable_meta_keys(): array {
		$keys = self::SEOPRESS_META;
		$tm   = apply_filters( 'wpml_setting', null, 'translation-management' );
		if ( is_array( $tm ) && ! empty( $tm['custom_fields_translation'] ) && is_array( $tm['custom_fields_translation'] ) ) {
			foreach ( $tm['custom_fields_translation'] as $key => $mode ) {
				if ( 2 === (int) $mode && '_elementor_data' !== $key ) {
					$keys[] = (string) $key;
				}
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/* -----------------------------------------------------------------
	 * Pure extraction
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,string> $fields    post_title, post_name, post_excerpt, post_content
	 * @param array<string,mixed>  $meta      meta_key => first value (raw, unserialized string)
	 * @param string[]             $meta_keys meta keys whose values are translatable text
	 *
	 * @return array{segments: array<string,string>, map: array<string,mixed>}
	 */
	public static function extract_from( array $fields, array $meta, array $meta_keys ): array {
		$segments = [];
		$map      = [ 'runs' => [], 'elementor' => false, 'meta_keys' => [] ];

		$title = trim( (string) ( $fields['post_title'] ?? '' ) );
		if ( '' !== $title ) {
			$segments['title'] = $title;
		}
		$slug = trim( str_replace( [ '-', '_' ], ' ', rawurldecode( (string) ( $fields['post_name'] ?? '' ) ) ) );
		if ( '' !== $slug ) {
			$segments['slug'] = $slug;
		}
		$excerpt = trim( (string) ( $fields['post_excerpt'] ?? '' ) );
		if ( '' !== $excerpt ) {
			$segments['excerpt'] = $excerpt;
		}
		self::add_runs( $segments, $map, 'content', (string) ( $fields['post_content'] ?? '' ) );

		$el_raw = (string) ( $meta['_elementor_data'] ?? '' );
		if ( '' !== $el_raw ) {
			$decoded = json_decode( $el_raw, true );
			if ( is_array( $decoded ) ) {
				$map['elementor'] = true;
				self::walk_elementor( $decoded, $segments, $map );
			}
		}

		foreach ( $meta_keys as $key ) {
			$val = $meta[ $key ] ?? '';
			if ( ! is_string( $val ) || '' === trim( $val ) || is_serialized( $val ) ) {
				continue;
			}
			$map['meta_keys'][] = $key;
			self::add_runs( $segments, $map, 'meta:' . $key, $val );
		}

		return [ 'segments' => $segments, 'map' => $map ];
	}

	/**
	 * Add one value as a single segment, or as ordered runs when long HTML.
	 *
	 * @param array<string,string> $segments
	 * @param array<string,mixed>  $map
	 */
	private static function add_runs( array &$segments, array &$map, string $id, string $value ): void {
		if ( '' === trim( $value ) ) {
			return;
		}
		$runs = self::split_html( $value, self::CHUNK_LIMIT );
		if ( 1 === count( $runs ) ) {
			$segments[ $id ] = $value;
			return;
		}
		$map['runs'][ $id ] = count( $runs );
		foreach ( $runs as $i => $run ) {
			$segments[ $id . ':' . $i ] = $run;
		}
	}

	/**
	 * Split HTML longer than $limit into runs at block boundaries, never
	 * inside a tag. Concatenating the runs reproduces the input byte for
	 * byte. A run may exceed $limit only when a single block does.
	 *
	 * @return string[]
	 */
	public static function split_html( string $html, int $limit ): array {
		if ( strlen( $html ) <= $limit ) {
			return [ $html ];
		}
		$pieces = preg_split(
			'#(</p>|</li>|</h[1-6]>|</div>|</section>|</article>|</blockquote>|</ul>|</ol>|</table>|<!--\s*/wp:[^>]*-->|\n\n)#i',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);
		if ( ! is_array( $pieces ) || count( $pieces ) < 2 ) {
			return [ $html ];
		}
		// Glue each delimiter back onto the piece before it.
		$blocks = [];
		foreach ( $pieces as $piece ) {
			if ( preg_match( '#^(</[a-z0-9]+>|<!--\s*/wp:[^>]*-->|\n\n)$#i', $piece ) && ! empty( $blocks ) ) {
				$blocks[ count( $blocks ) - 1 ] .= $piece;
			} else {
				$blocks[] = $piece;
			}
		}
		$runs = [];
		$cur  = '';
		foreach ( $blocks as $block ) {
			if ( '' !== $cur && strlen( $cur ) + strlen( $block ) > $limit ) {
				$runs[] = $cur;
				$cur    = '';
			}
			$cur .= $block;
		}
		if ( '' !== $cur ) {
			$runs[] = $cur;
		}
		return $runs;
	}

	/**
	 * Recursive walk over Elementor's element tree collecting text settings.
	 *
	 * @param array<int,array<string,mixed>> $elements
	 * @param array<string,string>           $segments
	 * @param array<string,mixed>            $map
	 */
	private static function walk_elementor( array $elements, array &$segments, array &$map ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$id       = (string) ( $element['id'] ?? '' );
			$settings = $element['settings'] ?? [];
			if ( '' !== $id && is_array( $settings ) ) {
				self::collect_settings( $settings, 'el:' . $id, $segments, $map );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk_elementor( $element['elements'], $segments, $map );
			}
		}
	}

	/**
	 * @param array<string,mixed>  $settings
	 * @param array<string,string> $segments
	 * @param array<string,mixed>  $map
	 */
	private static function collect_settings( array $settings, string $prefix, array &$segments, array &$map ): void {
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( is_array( $value ) ) {
				// Repeater: list of item objects (each with an _id).
				foreach ( $value as $index => $item ) {
					if ( is_array( $item ) && isset( $item['_id'] ) ) {
						self::collect_settings( $item, $prefix . ':' . $key . ':' . $index, $segments, $map );
					}
				}
				continue;
			}
			if ( ! is_string( $value ) || ! in_array( $key, self::ELEMENTOR_TEXT_KEYS, true ) ) {
				continue;
			}
			if ( ! self::looks_like_text( $value ) ) {
				continue;
			}
			self::add_runs( $segments, $map, $prefix . ':' . $key, $value );
		}
	}

	/** Reject values no translator should touch: empty, numbers, URLs, colors, ids. */
	public static function looks_like_text( string $value ): bool {
		$v = trim( $value );
		if ( '' === $v || is_numeric( $v ) ) {
			return false;
		}
		if ( preg_match( '#^(https?:)?//#i', $v ) || preg_match( '/^#[0-9a-f]{3,8}$/i', $v ) ) {
			return false;
		}
		// Icon classes and slug-like tokens ("fas fa-check", "my-anchor").
		if ( preg_match( '/^(fa[srlbd]?|fab|eicon|icon)[ -]/i', $v ) || preg_match( '/^[a-z0-9]+([_-][a-z0-9]+)+$/', $v ) ) {
			return false;
		}
		return (bool) preg_match( '/\p{L}/u', $v );
	}

	/* -----------------------------------------------------------------
	 * Apply
	 * ----------------------------------------------------------------- */

	/**
	 * Rebuild a value from its translated segment(s).
	 *
	 * @param array<string,string> $translated
	 * @param array<string,mixed>  $map
	 */
	public static function rebuild( string $id, array $translated, array $map, string $fallback ): string {
		if ( isset( $map['runs'][ $id ] ) ) {
			$out = '';
			for ( $i = 0; $i < (int) $map['runs'][ $id ]; $i++ ) {
				$out .= (string) ( $translated[ $id . ':' . $i ] ?? '' );
			}
			return $out;
		}
		return isset( $translated[ $id ] ) ? (string) $translated[ $id ] : $fallback;
	}

	/**
	 * Return the Elementor tree with translated settings written back.
	 *
	 * @param array<int,array<string,mixed>> $elements
	 * @param array<string,string>           $translated
	 * @param array<string,mixed>            $map
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function rebuild_elementor( array $elements, array $translated, array $map ): array {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$id = (string) ( $element['id'] ?? '' );
			if ( '' !== $id && isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$element['settings'] = self::rebuild_settings( $element['settings'], 'el:' . $id, $translated, $map );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::rebuild_elementor( $element['elements'], $translated, $map );
			}
		}
		unset( $element );
		return $elements;
	}

	/**
	 * @param array<string,mixed>  $settings
	 * @param array<string,string> $translated
	 * @param array<string,mixed>  $map
	 *
	 * @return array<string,mixed>
	 */
	private static function rebuild_settings( array $settings, string $prefix, array $translated, array $map ): array {
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( is_array( $value ) ) {
				foreach ( $value as $index => $item ) {
					if ( is_array( $item ) && isset( $item['_id'] ) ) {
						$settings[ $key ][ $index ] = self::rebuild_settings( $item, $prefix . ':' . $key . ':' . $index, $translated, $map );
					}
				}
				continue;
			}
			if ( ! is_string( $value ) ) {
				continue;
			}
			$id = $prefix . ':' . $key;
			if ( isset( $translated[ $id ] ) || isset( $map['runs'][ $id ] ) ) {
				$settings[ $key ] = self::rebuild( $id, $translated, $map, $value );
			}
		}
		return $settings;
	}

	/**
	 * Write the translation into $target_id: core fields, Elementor data,
	 * translatable meta, then a verbatim copy of all other postmeta.
	 *
	 * @param array<string,string> $translated
	 * @param array<string,mixed>  $map
	 *
	 * @return true|\WP_Error
	 */
	public static function apply( int $source_id, int $target_id, array $translated, array $map ) {
		$source = get_post( $source_id );
		if ( ! $source ) {
			return new \WP_Error( 'no_source', 'Source post disappeared.' );
		}

		$title   = self::rebuild( 'title', $translated, $map, (string) $source->post_title );
		$slug    = self::rebuild( 'slug', $translated, $map, (string) $source->post_name );
		$excerpt = self::rebuild( 'excerpt', $translated, $map, (string) $source->post_excerpt );
		$content = self::rebuild( 'content', $translated, $map, (string) $source->post_content );

		$result = wp_update_post(
			wp_slash( [
				'ID'           => $target_id,
				'post_title'   => $title,
				'post_name'    => sanitize_title( $slug ),
				'post_excerpt' => $excerpt,
				'post_content' => $content,
			] ),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$source_meta = (array) get_post_meta( $source_id );
		foreach ( $source_meta as $key => $values ) {
			$key = (string) $key;
			if ( in_array( $key, self::META_NEVER_COPY, true ) ) {
				continue;
			}
			$raw = is_array( $values ) ? ( $values[0] ?? '' ) : $values;
			$raw = is_string( $raw ) ? $raw : '';

			if ( '_elementor_data' === $key ) {
				$tree = json_decode( $raw, true );
				if ( is_array( $tree ) ) {
					$tree = self::rebuild_elementor( $tree, $translated, $map );
					$raw  = (string) wp_json_encode( $tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				}
				// Elementor stores JSON slashed; update_post_meta unslashes once.
				update_post_meta( $target_id, $key, wp_slash( $raw ) );
				continue;
			}

			if ( in_array( $key, (array) ( $map['meta_keys'] ?? [] ), true ) ) {
				update_post_meta( $target_id, $key, wp_slash( self::rebuild( 'meta:' . $key, $translated, $map, $raw ) ) );
				continue;
			}

			// Verbatim copy, preserving serialized values.
			update_post_meta( $target_id, $key, wp_slash( maybe_unserialize( $raw ) ) );
		}

		delete_post_meta( $target_id, '_elementor_css' );
		delete_post_meta( $target_id, '_elementor_element_cache' );

		return true;
	}
}
