<?php
/**
 * Open external links in a new tab.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Tiny frontend script that scans every <a href="..."> on the page,
 * identifies links pointing to a different hostname than the current
 * site, and adds target="_blank" + rel="noopener noreferrer" to them.
 *
 * The work happens client-side rather than rewriting HTML in a PHP
 * filter because (a) HTML in `the_content` is only one source of
 * links on a typical page — header menus, sidebars, footer widgets
 * all bypass that filter — and (b) post-hoc DOM scanning catches
 * dynamic content too.
 *
 * Toggle: utilities.external_links_new_tab in plugin settings.
 */
final class External_Links {

	public const HANDLE = 'bspe-connect-external-links';

	public static function init(): void {
		if ( ! (bool) Settings::get( 'utilities.external_links_new_tab', true ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		wp_enqueue_script(
			self::HANDLE,
			BSPE_CONNECT_URL . 'public/assets/external-links.js',
			[],
			BSPE_CONNECT_VERSION,
			true
		);
	}
}
