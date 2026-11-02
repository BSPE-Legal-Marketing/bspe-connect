<?php
/**
 * Privacy tab — cookie notice, opt-out, Global Privacy Control and the
 * tracker safeguards (class-privacy.php + public/assets/privacy-*.js).
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$privacy    = is_array( Settings::get( 'privacy', [] ) ) ? Settings::get( 'privacy', [] ) : [];
$action_url = admin_url( 'admin-post.php' );
$wp_policy  = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';

Components::open_form( 'privacy', $action_url );

Components::open_card(
	__( 'Privacy notice', 'bspe-connect' ),
	__( 'A small, non-blocking notice that appears about 20 seconds into a visit (never on arrival, never over an open chat, menu or popup), plus an opt-out that stops Clarity, CallRail, Meta Pixel and Google Analytics. Browsers sending Global Privacy Control are opted out automatically. Works with NitroPack and LiteSpeed: every visitor gets the same cached page and all decisions happen in the browser.', 'bspe-connect' )
);
Components::row(
	__( 'Enable', 'bspe-connect' ),
	static function () use ( $privacy ): void {
		Components::toggle( 'bspe[privacy][enabled]', ! empty( $privacy['enabled'] ), [
			'label' => __( 'Show the privacy notice and honour opt-outs on this site', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Purge the page cache after changing anything on this tab.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Profile', 'bspe-connect' ),
	static function () use ( $privacy ): void {
		Components::radio_pills( 'bspe[privacy][profile]', (string) ( $privacy['profile'] ?? 'us' ), [
			'us'     => __( 'US opt-out', 'bspe-connect' ),
			'us_ca'  => __( 'US + California', 'bspe-connect' ),
			'strict' => __( 'Strict opt-in', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( '<strong>US opt-out</strong>: tracking runs from the first page view; the notice offers OK / Opt out. <strong>US + California</strong>: the same, plus "Do Not Sell or Share My Personal Information" wording and the California opt-out icon. <strong>Strict opt-in</strong> (EU, UK, Turkey, Moldova): nothing that tracks runs until the visitor clicks Accept.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Privacy Policy URL', 'bspe-connect' ),
	static function () use ( $privacy, $wp_policy ): void {
		Components::text( 'bspe[privacy][policy_url]', (string) ( $privacy['policy_url'] ?? '' ), [
			'type'        => 'url',
			'placeholder' => '' !== $wp_policy ? $wp_policy : 'https://example.com/privacy-policy/',
		] );
	},
	[
		'description' => '' !== $wp_policy
			? __( 'Leave empty to use the page set under Settings → Privacy (shown as the placeholder).', 'bspe-connect' )
			: __( 'No Privacy Policy page is set under Settings → Privacy, so enter the URL here or the notice will have no policy link.', 'bspe-connect' ),
	]
);
Components::row(
	__( '"Your Privacy Choices" link', 'bspe-connect' ),
	static function () use ( $privacy ): void {
		Components::toggle( 'bspe[privacy][auto_link]', ! empty( $privacy['auto_link'] ), [
			'label' => __( 'Add the link automatically at the very bottom of every page', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Better: add it to the site footer yourself and switch this off. Any link to <code>#privacy-choices</code> opens the choices, or use the shortcode <code>[bspe_privacy_choices]</code>.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::open_card(
	__( 'Tracker safeguards', 'bspe-connect' ),
	__( 'Always on while the notice is enabled, whatever the visitor chooses. Clarity masks every form, input and chat widget in its recordings, and Meta Pixel\'s automatic events (which read button and form text) are switched off.', 'bspe-connect' )
);
Components::row(
	__( 'No Meta Pixel on these pages', 'bspe-connect' ),
	static function () use ( $privacy ): void {
		Components::textarea( 'bspe[privacy][pixel_off_paths]', (string) ( $privacy['pixel_off_paths'] ?? '' ), [
			'rows'        => 4,
			'placeholder' => "/contact*\n/free-consultation*",
			'maxlength'   => 2000,
		] );
	},
	[
		'description' => __( 'One path per line; <code>*</code> matches anything. A language folder in front is ignored, so <code>/contact*</code> also covers <code>/es/contact-us/</code>; add translated slugs (e.g. <code>/contacto*</code>) as their own lines. Use this for contact and intake form pages.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
