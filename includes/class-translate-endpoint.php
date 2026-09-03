<?php
/**
 * REST endpoints the BSPE Connect Manager uses to translate a page.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden fleet-side surface: nothing here shows up in wp-admin. The
 * Manager (fleet dashboard) holds the Claude key and runs the job; this
 * site only extracts the translatable text of a page and later writes
 * the translated text back as a WPML-linked post.
 *
 *   POST /wp-json/bspe-connect/v1/translate/status
 *   POST /wp-json/bspe-connect/v1/translate/extract   { url | post_id, target }
 *   POST /wp-json/bspe-connect/v1/translate/apply     { source_id, target_lang, source_lang, overwrite, translated, map }
 *
 * Every request must carry `Authorization: Bearer <jwt>`: an RS256 token
 * signed by the Manager's private key (the plugin verifies with the same
 * public key it uses for license responses) whose claims name this
 * site's registrable domain (aud), this install's license key (key),
 * scope "translate", and a short expiry. Without an active license or
 * WPML the endpoints refuse.
 */
final class Translate_Endpoint {

	public const NAMESPACE = 'bspe-connect/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$common = [
			'methods'             => 'POST',
			'permission_callback' => [ self::class, 'authorize' ],
		];
		register_rest_route( self::NAMESPACE, '/translate/status', $common + [ 'callback' => [ self::class, 'status' ] ] );
		register_rest_route( self::NAMESPACE, '/translate/extract', $common + [ 'callback' => [ self::class, 'extract' ] ] );
		register_rest_route( self::NAMESPACE, '/translate/apply', $common + [ 'callback' => [ self::class, 'apply' ] ] );
	}

	/* -----------------------------------------------------------------
	 * Auth
	 * ----------------------------------------------------------------- */

	/**
	 * @return true|\WP_Error
	 */
	public static function authorize( \WP_REST_Request $request ) {
		if ( ! Licensing::is_functional() ) {
			return new \WP_Error( 'bspe_unlicensed', 'This install has no active license.', [ 'status' => 403 ] );
		}
		$auth = (string) $request->get_header( 'authorization' );
		if ( ! preg_match( '/^Bearer\s+(\S+)$/i', trim( $auth ), $m ) ) {
			return new \WP_Error( 'bspe_no_token', 'Missing bearer token.', [ 'status' => 401 ] );
		}
		$claims = Licensing::verify_token( $m[1] );
		if ( null === $claims ) {
			return new \WP_Error( 'bspe_bad_token', 'Token signature, issuer or expiry rejected.', [ 'status' => 401 ] );
		}
		if ( 'translate' !== (string) ( $claims['scope'] ?? '' ) ) {
			return new \WP_Error( 'bspe_bad_scope', 'Token scope is not translate.', [ 'status' => 403 ] );
		}
		if ( (int) ( $claims['exp'] ?? 0 ) <= 0 ) {
			return new \WP_Error( 'bspe_no_exp', 'Token must expire.', [ 'status' => 401 ] );
		}
		$state = Licensing::state();
		if ( (string) ( $claims['aud'] ?? '' ) !== Licensing::current_domain() ) {
			return new \WP_Error( 'bspe_wrong_site', 'Token was issued for a different domain.', [ 'status' => 403 ] );
		}
		if ( '' === (string) $state['key'] || (string) ( $claims['key'] ?? '' ) !== (string) $state['key'] ) {
			return new \WP_Error( 'bspe_wrong_key', 'Token was issued for a different license.', [ 'status' => 403 ] );
		}
		return true;
	}

	/* -----------------------------------------------------------------
	 * Handlers
	 * ----------------------------------------------------------------- */

	public static function status( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		$langs = [];
		if ( WPML_Status::wpml_active() ) {
			$active = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
			foreach ( (array) $active as $code => $l ) {
				$langs[ (string) $code ] = (string) ( $l['native_name'] ?? $l['translated_name'] ?? $code );
			}
		}
		return new \WP_REST_Response( [
			'ok'           => true,
			'version'      => BSPE_CONNECT_VERSION,
			'wpml'         => WPML_Status::wpml_active(),
			'default_lang' => (string) apply_filters( 'wpml_default_language', 'en' ),
			'languages'    => $langs,
			'elementor'    => defined( 'ELEMENTOR_VERSION' ),
			'site_url'     => home_url( '/' ),
		] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function extract( \WP_REST_Request $request ) {
		if ( ! WPML_Status::wpml_active() ) {
			return new \WP_Error( 'bspe_no_wpml', 'WPML is not active on this site.', [ 'status' => 400 ] );
		}
		$post_id = (int) $request->get_param( 'post_id' );
		$url     = (string) $request->get_param( 'url' );
		if ( $post_id <= 0 && '' !== $url ) {
			$post_id = self::resolve_url( esc_url_raw( $url ) );
		}
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new \WP_Error( 'bspe_not_found', 'No post or page found at that URL on this site.', [ 'status' => 404 ] );
		}

		$target    = sanitize_key( (string) $request->get_param( 'target' ) );
		$extracted = Translate_Extractor::extract( $post_id );
		$info      = self::describe( $post, $target, $extracted );

		return new \WP_REST_Response( $info + [
			'segments' => $extracted['segments'],
			'map'      => $extracted['map'],
		] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function apply( \WP_REST_Request $request ) {
		if ( ! WPML_Status::wpml_active() ) {
			return new \WP_Error( 'bspe_no_wpml', 'WPML is not active on this site.', [ 'status' => 400 ] );
		}
		$source_id  = (int) $request->get_param( 'source_id' );
		$target     = sanitize_key( (string) $request->get_param( 'target_lang' ) );
		$source_lng = sanitize_key( (string) $request->get_param( 'source_lang' ) );
		$overwrite  = (bool) $request->get_param( 'overwrite' );
		$translated = $request->get_param( 'translated' );
		$map        = $request->get_param( 'map' );

		$source = get_post( $source_id );
		if ( ! $source ) {
			return new \WP_Error( 'bspe_not_found', 'The source post disappeared.', [ 'status' => 404 ] );
		}
		if ( '' === $target || ! is_array( $translated ) || ! is_array( $map ) || empty( $translated ) ) {
			return new \WP_Error( 'bspe_bad_request', 'target_lang, translated and map are required.', [ 'status' => 400 ] );
		}
		if ( '' === $source_lng ) {
			$source_lng = self::post_language( $source_id );
		}
		if ( $source_lng === $target ) {
			return new \WP_Error( 'bspe_same_lang', 'Source and target language are the same.', [ 'status' => 400 ] );
		}
		$translated = array_map( 'strval', array_filter( $translated, 'is_string' ) );

		$type        = (string) $source->post_type;
		$existing_id = self::translation_id( $source_id, $type, $target );
		$created     = false;

		if ( $existing_id > 0 ) {
			if ( ! $overwrite ) {
				return new \WP_Error( 'bspe_exists', 'A translation already exists; pass overwrite to replace its text.', [ 'status' => 409 ] );
			}
			$target_id = $existing_id;
		} else {
			$parent_id = 0;
			if ( (int) $source->post_parent > 0 ) {
				$parent_id = self::translation_id( (int) $source->post_parent, $type, $target );
			}
			$target_id = wp_insert_post(
				wp_slash( [
					'post_type'      => $type,
					'post_status'    => (string) $source->post_status,
					'post_author'    => (int) $source->post_author,
					'post_parent'    => $parent_id,
					'menu_order'     => (int) $source->menu_order,
					'comment_status' => (string) $source->comment_status,
					'ping_status'    => (string) $source->ping_status,
					'post_title'     => (string) $source->post_title,
					'post_content'   => '',
				] ),
				true
			);
			if ( is_wp_error( $target_id ) ) {
				$target_id->add_data( [ 'status' => 500 ] );
				return $target_id;
			}
			$created = true;

			$trid = apply_filters( 'wpml_element_trid', null, $source_id, 'post_' . $type );
			do_action( 'wpml_set_element_language_details', [
				'element_id'           => $target_id,
				'element_type'         => 'post_' . $type,
				'trid'                 => $trid,
				'language_code'        => $target,
				'source_language_code' => $source_lng,
			] );
		}

		$applied = Translate_Extractor::apply( $source_id, (int) $target_id, $translated, $map );
		if ( is_wp_error( $applied ) ) {
			if ( $created ) {
				wp_delete_post( (int) $target_id, true );
			}
			$applied->add_data( [ 'status' => 500 ] );
			return $applied;
		}

		self::copy_terms( $source_id, (int) $target_id, $type, $target );

		Logger::log( 'info', 'Translate: page translated from the fleet dashboard', [
			'source_id' => $source_id,
			'target_id' => (int) $target_id,
			'lang'      => $target,
			'created'   => $created,
			'segments'  => count( $translated ),
		] );

		return new \WP_REST_Response( [
			'id'       => (int) $target_id,
			'created'  => $created,
			'edit_url' => (string) get_edit_post_link( (int) $target_id, 'raw' ),
			'view_url' => (string) get_permalink( (int) $target_id ),
			'status'   => (string) get_post_status( (int) $target_id ),
		] );
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	/**
	 * URL -> post id. url_to_postid() first; then with the WPML language
	 * directory stripped; then a slug match across public post types.
	 */
	public static function resolve_url( string $url ): int {
		$id = (int) url_to_postid( $url );
		if ( $id > 0 ) {
			return $id;
		}
		$parts = wp_parse_url( $url );
		$path  = (string) ( $parts['path'] ?? '' );
		$path  = preg_replace( '#^/[a-z]{2}(-[a-z]{2})?/#i', '/', $path ) ?? $path;
		$id    = (int) url_to_postid( untrailingslashit( home_url() ) . $path );
		if ( $id > 0 ) {
			return $id;
		}
		$slug = sanitize_title( basename( untrailingslashit( $path ) ) );
		if ( '' === $slug ) {
			return 0;
		}
		global $wpdb;
		$types = array_values( get_post_types( [ 'public' => true ] ) );
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql   = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ($in) AND post_status IN ('publish','draft','private','pending') ORDER BY ID ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge( [ $slug ], $types )
		);
		return (int) $wpdb->get_var( $sql );
	}

	/** WPML language code of a post, or the site default when unknown. */
	public static function post_language( int $post_id ): string {
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
			return (string) $details['language_code'];
		}
		return (string) apply_filters( 'wpml_default_language', 'en' );
	}

	/** Id of the $lang translation of $post_id, or 0. Never the post itself. */
	public static function translation_id( int $post_id, string $type, string $lang ): int {
		$id = (int) apply_filters( 'wpml_object_id', $post_id, $type, false, $lang );
		if ( $id === $post_id ) {
			return self::post_language( $post_id ) === $lang ? $id : 0;
		}
		return $id;
	}

	/**
	 * @param array{segments: array<string,string>, map: array<string,mixed>} $extracted
	 *
	 * @return array<string,mixed>
	 */
	private static function describe( \WP_Post $post, string $target, array $extracted ): array {
		$type = (string) $post->post_type;
		$lang = self::post_language( (int) $post->ID );
		$obj  = get_post_type_object( $type );

		$chars = 0;
		foreach ( $extracted['segments'] as $s ) {
			$chars += strlen( $s );
		}

		$existing = [ 'id' => 0, 'edit_url' => '', 'view_url' => '', 'status' => '' ];
		if ( '' !== $target && $target !== $lang ) {
			$tid = self::translation_id( (int) $post->ID, $type, $target );
			if ( $tid > 0 ) {
				$existing = [
					'id'       => $tid,
					'edit_url' => (string) get_edit_post_link( $tid, 'raw' ),
					'view_url' => (string) get_permalink( $tid ),
					'status'   => (string) get_post_status( $tid ),
				];
			}
		}

		return [
			'id'           => (int) $post->ID,
			'title'        => (string) $post->post_title,
			'type'         => $type,
			'type_label'   => $obj ? (string) $obj->labels->singular_name : $type,
			'status'       => (string) $post->post_status,
			'language'     => $lang,
			'default_lang' => (string) apply_filters( 'wpml_default_language', 'en' ),
			'edit_url'     => (string) get_edit_post_link( (int) $post->ID, 'raw' ),
			'view_url'     => (string) get_permalink( (int) $post->ID ),
			'elementor'    => ! empty( $extracted['map']['elementor'] ),
			'segment_count' => count( $extracted['segments'] ),
			'chars'        => $chars,
			'existing'     => $existing,
		];
	}

	/**
	 * Assign the target the translated counterparts of the source's terms
	 * (falling back to the same term when WPML has no translation).
	 */
	private static function copy_terms( int $source_id, int $target_id, string $type, string $lang ): void {
		foreach ( get_object_taxonomies( $type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$ids = [];
			foreach ( $terms as $term_id ) {
				$ids[] = (int) apply_filters( 'wpml_object_id', (int) $term_id, $taxonomy, true, $lang );
			}
			wp_set_object_terms( $target_id, array_unique( $ids ), $taxonomy, false );
		}
	}
}
