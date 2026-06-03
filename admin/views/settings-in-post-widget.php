<?php
/**
 * In-Post Widget tab — inject a saved shortcode into blog post
 * content. Posts only. Placement is before the first h2-h6 heading,
 * or before an iframe if one sits above that heading.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$cfg        = is_array( Settings::get( 'in_post_widget', [] ) ) ? Settings::get( 'in_post_widget', [] ) : [];
$action_url = admin_url( 'admin-post.php' );

Components::open_form( 'in_post_widget', $action_url );

/* ----------------- Enable toggle ----------------- */
Components::open_card(
	__( 'In-Post Widget', 'bspe-connect' ),
	__( 'Inject a saved shortcode into blog post content. Commonly used to drop an Elementor template, opt-in form, or CTA right above the article body — where reader engagement peaks.', 'bspe-connect' )
);
Components::row(
	__( 'Enable', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::toggle( 'bspe[in_post_widget][enabled]', ! empty( $cfg['enabled'] ), [
			'label' => __( 'Insert the widget into blog posts', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'When off, the plugin does not touch the_content at all — zero impact on page rendering. Pages and custom post types are never affected, regardless of this toggle.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Shortcode ----------------- */
Components::open_card(
	__( 'Shortcode', 'bspe-connect' ),
	__( 'Paste any shortcode supported by this site. Elementor templates use [elementor-template id="123"]; other page builders have their own. The output is rendered through WordPress\'s standard do_shortcode pipeline.', 'bspe-connect' )
);
Components::row(
	__( 'Shortcode to inject', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::textarea( 'bspe[in_post_widget][shortcode]', (string) ( $cfg['shortcode'] ?? '' ), [
			'rows'        => 4,
			'placeholder' => '[elementor-template id="123"]',
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-shortcode',
		'description' => __( 'Test the shortcode on a real post before relying on it. If the shortcode renders nothing, the widget will silently render nothing.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Bottom margin', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::number( 'bspe[in_post_widget][margin_bottom_px]', (int) ( $cfg['margin_bottom_px'] ?? 20 ), [
			'min'    => 0,
			'max'    => 200,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-margin_bottom_px',
		'description' => __( 'Default 20 px. Space below the widget so it doesn\'t sit flush against the heading or iframe that follows it.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Placement (mostly automatic) ----------------- */
Components::open_card(
	__( 'Placement', 'bspe-connect' ),
	__( 'Automatic. The widget is inserted before the first heading found in the post (h2 through h6 — h1 is the post title and isn\'t used in the body). If a Google Map embed sits above that heading, the widget goes before the map instead — so the CTA appears above the office-location map. Other embeds (YouTube, Pinterest, etc.) are ignored and never capture the widget.', 'bspe-connect' )
);
Components::row(
	__( 'Fallback: after paragraph #', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::number( 'bspe[in_post_widget][fallback_after_paragraph]', (int) ( $cfg['fallback_after_paragraph'] ?? 1 ), [
			'min'  => 1,
			'max'  => 10,
			'step' => 1,
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-fallback_after_paragraph',
		'description' => __( 'Only used when the post has no heading at all. Drops the widget after this many paragraphs instead. Default 1. If the post has fewer paragraphs than this number, the widget is appended at the end.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Exclude post IDs', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::text( 'bspe[in_post_widget][exclude_ids]', (string) ( $cfg['exclude_ids'] ?? '' ), [
			'placeholder' => '12, 34, 56',
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-exclude_ids',
		'description' => __( 'Comma-separated post IDs that should never receive the widget — useful for articles where the widget would be redundant or off-tone.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
