<?php
/**
 * Read-only WPML diagnostics for the admin UI.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Reports the state of WPML's own "Skip language" behavior so admins can
 * see, from inside BSPE Connect, whether languages without translation
 * are being hidden from the switcher and why the option can appear to
 * "not work".
 *
 * Replaces the short-lived utilities.wpml_hide_untranslated filter from
 * v3.6.6: WPML ships this natively (WPML, Languages, Language switcher
 * options, "Skip language"), so the plugin now only OBSERVES and
 * explains instead of filtering.
 *
 * The one real gap in WPML's native option: duplicated content. Pages
 * created with WPML's "Duplicate" feature count as translations, so
 * their languages keep showing in the switcher even with Skip on. That
 * is the usual reason the option seems broken on a site. The published
 * duplicate count is surfaced for exactly that diagnosis.
 */
final class WPML_Status {

	/**
	 * True when WPML (SitePress) is loaded on this site. Safe from any
	 * admin hook (those run long after plugins_loaded).
	 */
	public static function wpml_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( '\SitePress' );
	}

	/**
	 * Whether WPML is set to skip languages without translation.
	 *
	 * WPML stores this in the icl_sitepress_settings option as
	 * icl_lso_link_empty ("link to home of language for missing
	 * translations"): truthy = link to the language homepage,
	 * falsy = skip the language. The admin UI radio "What to do for
	 * languages without translation" writes the same key.
	 *
	 * @return bool|null True = Skip language. False = Link to home.
	 *                   Null = WPML settings not readable (or key never
	 *                   saved, meaning WPML's default applies).
	 */
	public static function skip_enabled(): ?bool {
		$settings = get_option( 'icl_sitepress_settings' );
		if ( ! is_array( $settings ) ) {
			return null;
		}
		if ( ! array_key_exists( 'icl_lso_link_empty', $settings ) ) {
			return null;
		}
		return empty( $settings['icl_lso_link_empty'] );
	}

	/**
	 * Number of PUBLISHED posts/pages that are WPML duplicates of
	 * another language's original. Each one counts as a "translation"
	 * to WPML, so Skip language will not hide its language even though
	 * the visitor just gets a copy of the original text.
	 *
	 * Single indexed-meta-key query; only ever called when rendering
	 * the plugin's own admin page.
	 */
	public static function published_duplicate_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_icl_lang_duplicate_of'
			 AND p.post_status = 'publish'"
		);
	}
}
