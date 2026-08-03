<?php
/**
 * Hide untranslated languages from the WPML language switcher.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Removes language links from WPML's switcher when the current page has
 * no real, published translation in that language. Stock WPML keeps the
 * link and sends visitors to the untranslated original (or a 404),
 * which reads as a broken site in that language.
 *
 * The filter is registered whenever the toggle is on. If WPML is not
 * active, 'wpml_ls_languages' never fires and the hook costs nothing,
 * so no load-order gymnastics are needed. wpml_active() exists for the
 * admin UI (settings row description and the nudge notice).
 *
 * Toggle: utilities.wpml_hide_untranslated (default OFF; an admin
 * notice on the plugin pages suggests enabling it when WPML is
 * detected).
 */
final class Hide_Untranslated_Languages {

	public static function init(): void {
		if ( ! (bool) Settings::get( 'utilities.wpml_hide_untranslated', false ) ) {
			return;
		}
		// Priority 20: after WPML builds the list, before themes print it.
		add_filter( 'wpml_ls_languages', [ self::class, 'filter_languages' ], 20 );
	}

	/**
	 * True when WPML (SitePress) is loaded on this site. Safe to call
	 * from admin hooks (admin_notices runs long after plugins_loaded).
	 */
	public static function wpml_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( '\SitePress' );
	}

	/**
	 * Drop switcher entries whose translation is missing, unpublished,
	 * or a mere duplicate of the original.
	 *
	 * @param mixed $languages WPML switcher entries keyed by language code.
	 * @return mixed
	 */
	public static function filter_languages( $languages ) {
		if ( ! is_array( $languages ) || is_admin() ) {
			return $languages;
		}

		$object_id = 0;
		if ( is_singular() ) {
			$object_id = get_queried_object_id();
		} elseif ( is_home() ) {
			$object_id = (int) get_option( 'page_for_posts' ); // the blog page
		}

		if ( ! $object_id ) {
			return $languages; // archives, search, 404: left alone
		}

		$post_type = get_post_type( $object_id );

		foreach ( $languages as $code => $language ) {
			if ( ! empty( $language['active'] ) ) {
				continue; // never remove the current language
			}

			$translated_id = apply_filters( 'wpml_object_id', $object_id, $post_type, false, $code );

			// No translation at all.
			if ( ! $translated_id ) {
				unset( $languages[ $code ] );
				continue;
			}

			// Exists, but only as an automatic duplicate of the original.
			if ( get_post_meta( $translated_id, '_icl_lang_duplicate_of', true ) ) {
				unset( $languages[ $code ] );
				continue;
			}

			// Draft / pending / private.
			if ( 'publish' !== get_post_status( $translated_id ) ) {
				unset( $languages[ $code ] );
			}
		}

		return $languages;
	}
}
