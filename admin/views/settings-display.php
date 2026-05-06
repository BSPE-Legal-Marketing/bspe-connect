<?php
/**
 * Display Rules tab — where the bar appears.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$rules      = is_array( Settings::get( 'display_rules', [] ) ) ? Settings::get( 'display_rules', [] ) : [];
$action_url = admin_url( 'admin-post.php' );

$mode_options = [
	'sitewide'              => __( 'Site-wide', 'bspe-connect' ),
	'pages_only'            => __( 'Pages only', 'bspe-connect' ),
	'posts_only'            => __( 'Posts only', 'bspe-connect' ),
	'pages_except'          => __( 'Pages only, except these slugs', 'bspe-connect' ),
	'posts_except'          => __( 'Posts only, except these slugs', 'bspe-connect' ),
	'sitewide_except_pages' => __( 'Site-wide, exclude these page slugs', 'bspe-connect' ),
	'sitewide_except_posts' => __( 'Site-wide, exclude these post slugs', 'bspe-connect' ),
];

Components::open_form( 'display', $action_url );

Components::open_card(
	__( 'Where to show the bar', 'bspe-connect' ),
	__( 'Pick a rule below. Slug lists accept commas or new lines and are matched against post / page slugs (the URL fragment after the trailing slash).', 'bspe-connect' )
);
Components::row(
	__( 'Rule', 'bspe-connect' ),
	static function () use ( $rules, $mode_options ): void {
		Components::select( 'bspe[display_rules][mode]', (string) ( $rules['mode'] ?? 'sitewide' ), $mode_options, [
			'data' => [ 'bspe-display-mode' => '1' ],
		] );
	},
	[ 'id' => 'bspe-display_rules-mode' ]
);
Components::row(
	__( 'Slug list', 'bspe-connect' ),
	static function () use ( $rules ): void {
		Components::textarea( 'bspe[display_rules][slugs]', (string) ( $rules['slugs'] ?? '' ), [
			'rows'        => 4,
			'placeholder' => "contact, thank-you\nprivacy-policy",
			'maxlength'   => 4000,
		] );
	},
	[
		'id'          => 'bspe-display_rules-slugs',
		'description' => __( 'Examples: <code>about-us, contact</code> or one slug per line. Slugs are sanitized via sanitize_title() on save.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
