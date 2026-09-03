<?php
/**
 * Translate tab — admin-ajax endpoints driving the chunked page translation.
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

use BSPE\Connect\Claude_Client;
use BSPE\Connect\Licensing;
use BSPE\Connect\Logger;
use BSPE\Connect\Settings;
use BSPE\Connect\Translate_Extractor;
use BSPE\Connect\Translate_Job;
use BSPE\Connect\WPML_Status;

defined( 'ABSPATH' ) || exit;

/**
 * Flow driven by admin.js:
 *
 *   lookup  URL -> post id, language, existing translation, size
 *   start   builds a job (extract + batches), nothing written yet
 *   step    translates one batch, stores it in the job
 *   apply   creates / updates the WPML translation post from the job
 *   cancel  drops the job
 *
 * Every handler: manage_options + nonce, JSON in / JSON out.
 */
final class Translate_Controller {

	public const NONCE = 'bspe_connect_translate';

	private const ACTIONS = [ 'lookup', 'start', 'step', 'apply', 'cancel' ];

	public static function init(): void {
		foreach ( self::ACTIONS as $action ) {
			add_action( 'wp_ajax_bspe_connect_translate_' . $action, [ self::class, 'handle_' . $action ] );
		}
	}

	/** True when the Translate workflow can run on this site. */
	public static function available(): bool {
		return Licensing::is_functional()
			&& WPML_Status::wpml_active()
			&& '' !== (string) Settings::get( 'translate.api_key', '' );
	}

	/**
	 * Human-readable reasons the workflow is unavailable (empty = ok).
	 *
	 * @return string[]
	 */
	public static function blockers(): array {
		$out = [];
		if ( ! Licensing::is_functional() ) {
			$out[] = __( 'The plugin license is not active.', 'bspe-connect' );
		}
		if ( ! WPML_Status::wpml_active() ) {
			$out[] = __( 'WPML is not active on this site. Translations are created as WPML-linked posts, so WPML is required.', 'bspe-connect' );
		}
		if ( '' === (string) Settings::get( 'translate.api_key', '' ) ) {
			$out[] = __( 'No Claude API key saved yet. Enter one below and save.', 'bspe-connect' );
		}
		return $out;
	}

	/* -----------------------------------------------------------------
	 * Handlers
	 * ----------------------------------------------------------------- */

	public static function handle_lookup(): void {
		self::guard();
		$url = isset( $_POST['url'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ) ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( [ 'message' => __( 'Paste the page URL first.', 'bspe-connect' ) ] );
		}

		$post_id = self::resolve_url( $url );
		if ( $post_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'No post or page found at that URL on this site.', 'bspe-connect' ) ] );
		}
		wp_send_json_success( self::describe( $post_id ) );
	}

	public static function handle_start(): void {
		self::guard();
		$post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$target    = isset( $_POST['target'] ) ? sanitize_key( (string) wp_unslash( $_POST['target'] ) ) : '';
		$overwrite = ! empty( $_POST['overwrite'] );

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'That post no longer exists.', 'bspe-connect' ) ] );
		}
		if ( ! array_key_exists( $target, Claude_Client::LANGUAGES ) ) {
			wp_send_json_error( [ 'message' => __( 'Unsupported target language.', 'bspe-connect' ) ] );
		}

		$info = self::describe( $post_id );
		if ( $info['language'] === $target ) {
			wp_send_json_error( [ 'message' => __( 'This page is already in the target language.', 'bspe-connect' ) ] );
		}
		$existing = (int) ( $info['translations'][ $target ]['id'] ?? 0 );
		if ( $existing > 0 && ! $overwrite ) {
			wp_send_json_error( [ 'message' => __( 'A translation already exists. Tick "Overwrite the existing translation" to replace its content.', 'bspe-connect' ) ] );
		}

		$extracted = Translate_Extractor::extract( $post_id );
		if ( empty( $extracted['segments'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Nothing translatable was found on this page.', 'bspe-connect' ) ] );
		}

		$job = Translate_Job::create( $post_id, (string) $info['language'], $target, $overwrite, $extracted['segments'], $extracted['map'] );

		wp_send_json_success( [
			'job'      => $job['id'],
			'batches'  => count( $job['batches'] ),
			'segments' => count( $job['segments'] ),
			'chars'    => Translate_Job::total_chars( $job ),
		] );
	}

	public static function handle_step(): void {
		self::guard();
		$job = self::job_from_request();

		$batches = (array) $job['batches'];
		$next    = (int) $job['next'];
		if ( $next >= count( $batches ) ) {
			wp_send_json_success( self::progress( $job ) );
		}

		$client = Claude_Client::from_settings();
		if ( ! $client ) {
			wp_send_json_error( [ 'message' => __( 'No Claude API key saved.', 'bspe-connect' ) ] );
		}

		$ids   = (array) $batches[ $next ];
		$batch = [];
		foreach ( $ids as $id ) {
			if ( isset( $job['segments'][ $id ] ) ) {
				$batch[ $id ] = (string) $job['segments'][ $id ];
			}
		}

		$result = $client->translate_batch(
			$batch,
			(string) $job['target_lang'],
			(string) $job['source_lang'],
			[ 'firm_name' => self::firm_name() ]
		);

		// If a batch is too big for one response, split it in two and retry
		// on the next step instead of failing the whole job.
		if ( is_wp_error( $result ) && 'max_tokens' === $result->get_error_code() && count( $ids ) > 1 ) {
			$half = (int) ceil( count( $ids ) / 2 );
			array_splice( $batches, $next, 1, [ array_slice( $ids, 0, $half ), array_slice( $ids, $half ) ] );
			$job['batches'] = $batches;
			Translate_Job::save( $job );
			wp_send_json_success( self::progress( $job ) + [ 'split' => true ] );
		}
		if ( is_wp_error( $result ) ) {
			Logger::log( 'error', 'Translate: batch failed', [
				'job'   => $job['id'],
				'batch' => $next,
				'error' => $result->get_error_message(),
			] );
			wp_send_json_error( [ 'message' => $result->get_error_message(), 'retry' => true ] );
		}

		foreach ( $result['translations'] as $id => $text ) {
			$job['done'][ $id ] = $text;
		}
		$job['usage']['input']  += (int) $result['usage']['input'];
		$job['usage']['output'] += (int) $result['usage']['output'];
		$job['next']             = $next + 1;
		Translate_Job::save( $job );

		wp_send_json_success( self::progress( $job ) );
	}

	public static function handle_apply(): void {
		self::guard();
		$job = self::job_from_request();

		if ( (int) $job['next'] < count( (array) $job['batches'] ) ) {
			wp_send_json_error( [ 'message' => __( 'The job is not finished yet.', 'bspe-connect' ) ] );
		}
		$missing = array_diff( array_keys( (array) $job['segments'] ), array_keys( (array) $job['done'] ) );
		if ( ! empty( $missing ) ) {
			wp_send_json_error( [ 'message' => sprintf( __( '%d segments were never translated. Start over.', 'bspe-connect' ), count( $missing ) ) ] );
		}

		$source_id = (int) $job['source_id'];
		$source    = get_post( $source_id );
		if ( ! $source ) {
			wp_send_json_error( [ 'message' => __( 'The source post disappeared.', 'bspe-connect' ) ] );
		}
		$target = (string) $job['target_lang'];
		$type   = (string) $source->post_type;

		$existing_id = self::translation_id( $source_id, $type, $target );
		$created     = false;

		if ( $existing_id > 0 ) {
			if ( empty( $job['overwrite'] ) ) {
				wp_send_json_error( [ 'message' => __( 'A translation appeared meanwhile. Start over and tick Overwrite.', 'bspe-connect' ) ] );
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
				wp_send_json_error( [ 'message' => $target_id->get_error_message() ] );
			}
			$created = true;

			$trid = apply_filters( 'wpml_element_trid', null, $source_id, 'post_' . $type );
			do_action( 'wpml_set_element_language_details', [
				'element_id'           => $target_id,
				'element_type'         => 'post_' . $type,
				'trid'                 => $trid,
				'language_code'        => $target,
				'source_language_code' => (string) $job['source_lang'],
			] );
		}

		$applied = Translate_Extractor::apply( $source_id, (int) $target_id, (array) $job['done'], (array) $job['map'] );
		if ( is_wp_error( $applied ) ) {
			if ( $created ) {
				wp_delete_post( (int) $target_id, true );
			}
			wp_send_json_error( [ 'message' => $applied->get_error_message() ] );
		}

		self::copy_terms( $source_id, (int) $target_id, $type, $target );

		Translate_Job::delete( (string) $job['id'] );

		Logger::log( 'info', 'Translate: page translated', [
			'source_id'     => $source_id,
			'target_id'     => (int) $target_id,
			'lang'          => $target,
			'created'       => $created,
			'batches'       => count( (array) $job['batches'] ),
			'segments'      => count( (array) $job['segments'] ),
			'input_tokens'  => (int) $job['usage']['input'],
			'output_tokens' => (int) $job['usage']['output'],
			'model'         => (string) Settings::get( 'translate.model', 'claude-opus-5' ),
		] );

		wp_send_json_success( [
			'id'       => (int) $target_id,
			'created'  => $created,
			'edit_url' => (string) get_edit_post_link( (int) $target_id, 'raw' ),
			'view_url' => (string) get_permalink( (int) $target_id ),
			'usage'    => $job['usage'],
		] );
	}

	public static function handle_cancel(): void {
		self::guard();
		$id = isset( $_POST['job'] ) ? (string) wp_unslash( $_POST['job'] ) : '';
		Translate_Job::delete( $id );
		wp_send_json_success();
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	private static function guard(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'No permission.', 'bspe-connect' ) ], 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! self::available() ) {
			wp_send_json_error( [ 'message' => implode( ' ', self::blockers() ) ], 400 );
		}
	}

	/** @return array<string,mixed> */
	private static function job_from_request(): array {
		$id  = isset( $_POST['job'] ) ? (string) wp_unslash( $_POST['job'] ) : '';
		$job = Translate_Job::load( $id );
		if ( ! $job ) {
			wp_send_json_error( [ 'message' => __( 'This translation job expired or was cancelled. Start again.', 'bspe-connect' ) ] );
		}
		return $job;
	}

	/**
	 * @param array<string,mixed> $job
	 *
	 * @return array<string,int|bool>
	 */
	private static function progress( array $job ): array {
		$total = count( (array) $job['batches'] );
		return [
			'next'       => (int) $job['next'],
			'total'      => $total,
			'finished'   => (int) $job['next'] >= $total,
			'chars_done' => Translate_Job::done_chars( $job ),
			'chars'      => Translate_Job::total_chars( $job ),
		];
	}

	private static function firm_name(): string {
		$hint = trim( (string) Settings::get( 'translate.firm_name_hint', '' ) );
		if ( '' !== $hint ) {
			return $hint;
		}
		return trim( (string) Settings::get( 'design.firm_name', '' ) );
	}

	/**
	 * URL -> post id. url_to_postid() first; then retry with the WPML
	 * language directory / ?lang= stripped, since WPML's rewrite for
	 * translated URLs may not resolve from an admin request.
	 */
	public static function resolve_url( string $url ): int {
		$id = (int) url_to_postid( $url );
		if ( $id > 0 ) {
			return $id;
		}
		$parts = wp_parse_url( $url );
		$path  = (string) ( $parts['path'] ?? '' );
		$path  = preg_replace( '#^/[a-z]{2}(-[a-z]{2})?/#i', '/', $path ) ?? $path;
		$home  = untrailingslashit( home_url() );
		$id    = (int) url_to_postid( $home . $path );
		if ( $id > 0 ) {
			return $id;
		}
		// Last resort: match the final path segment as a slug across public types.
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

	/** WPML language code of a post, or the default language when unknown. */
	public static function post_language( int $post_id, string $type ): string {
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
			return (string) $details['language_code'];
		}
		unset( $type );
		return (string) apply_filters( 'wpml_default_language', 'en' );
	}

	/** Id of the $lang translation of $post_id, or 0. Never returns the post itself. */
	public static function translation_id( int $post_id, string $type, string $lang ): int {
		$id = apply_filters( 'wpml_object_id', $post_id, $type, false, $lang );
		$id = (int) $id;
		if ( $id === $post_id ) {
			return self::post_language( $post_id, $type ) === $lang ? $id : 0;
		}
		return $id;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function describe( int $post_id ): array {
		$post = get_post( $post_id );
		$type = (string) $post->post_type;
		$lang = self::post_language( $post_id, $type );

		$translations = [];
		foreach ( array_keys( Claude_Client::LANGUAGES ) as $code ) {
			if ( $code === $lang ) {
				continue;
			}
			$tid = self::translation_id( $post_id, $type, $code );
			$translations[ $code ] = [
				'id'       => $tid,
				'edit_url' => $tid > 0 ? (string) get_edit_post_link( $tid, 'raw' ) : '',
				'status'   => $tid > 0 ? (string) get_post_status( $tid ) : '',
			];
		}

		$extracted = Translate_Extractor::extract( $post_id );
		$chars     = 0;
		foreach ( $extracted['segments'] as $s ) {
			$chars += strlen( $s );
		}
		$obj = get_post_type_object( $type );

		return [
			'id'           => $post_id,
			'title'        => (string) $post->post_title,
			'type'         => $type,
			'type_label'   => $obj ? (string) $obj->labels->singular_name : $type,
			'status'       => (string) $post->post_status,
			'language'     => $lang,
			'default_lang' => (string) apply_filters( 'wpml_default_language', 'en' ),
			'edit_url'     => (string) get_edit_post_link( $post_id, 'raw' ),
			'elementor'    => ! empty( $extracted['map']['elementor'] ),
			'segments'     => count( $extracted['segments'] ),
			'chars'        => $chars,
			'translations' => $translations,
		];
	}

	/**
	 * Assign the target post the translated counterparts of the source's
	 * terms (WPML translates terms separately; fall back to the same term
	 * when no translation exists, which is what WPML's duplicate does).
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
