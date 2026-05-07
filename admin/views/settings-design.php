<?php
/**
 * Design settings tab — firm name, color pickers, font selection.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$design     = is_array( Settings::get( 'design', [] ) ) ? Settings::get( 'design', [] ) : [];
$colors     = is_array( $design['colors'] ?? null ) ? $design['colors'] : [];
$action_url = admin_url( 'admin-post.php' );

$google_fonts = [
	'DM Sans'           => 'DM Sans',
	'Inter'             => 'Inter',
	'Lato'              => 'Lato',
	'Roboto'            => 'Roboto',
	'Open Sans'         => 'Open Sans',
	'Source Sans 3'     => 'Source Sans 3',
	'Poppins'           => 'Poppins',
	'Manrope'           => 'Manrope',
	'Nunito'            => 'Nunito',
	'Work Sans'         => 'Work Sans',
	'Plus Jakarta Sans' => 'Plus Jakarta Sans',
	'IBM Plex Sans'     => 'IBM Plex Sans',
	'Figtree'           => 'Figtree',
	'Montserrat'        => 'Montserrat',
	'Public Sans'       => 'Public Sans',
];

Components::open_form( 'design', $action_url );

/* ----------------- Firm name ----------------- */
Components::open_card(
	__( 'Firm', 'bspe-connect' ),
	__( 'Used in template variables like {firm_name}.', 'bspe-connect' )
);
Components::row(
	__( 'Firm name', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::text( 'bspe[design][firm_name]', (string) ( $design['firm_name'] ?? '' ), [
			'placeholder' => __( 'Yourfirm Legal', 'bspe-connect' ),
			'maxlength'   => 120,
		] );
	},
	[
		'id'          => 'bspe-design-firm_name',
		'description' => __( 'If empty, falls back to the WordPress site title.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Colors ----------------- */
Components::open_card(
	__( 'Colors', 'bspe-connect' ),
	__( 'Per-surface colors used by the bar, buttons, welcome bubble, and form modal. Hex values; 3- and 6-digit accepted.', 'bspe-connect' )
);

$color_rows = [
	[ 'bar_bg',    __( 'Bar background', 'bspe-connect' ),     '#351E28' ],
	[ 'button_bg', __( 'Button background', 'bspe-connect' ),  '#351E28' ],
	[ 'button_fg', __( 'Button text & icon', 'bspe-connect' ), '#FAF7F2' ],
	[ 'bubble_bg', __( 'Bubble background', 'bspe-connect' ),  '#FAF7F2' ],
	[ 'bubble_fg', __( 'Bubble text', 'bspe-connect' ),        '#351E28' ],
	[ 'accent',    __( 'Accent (focus, CTA)', 'bspe-connect' ),'#3AAFB9' ],
];
foreach ( $color_rows as $row ) {
	[ $key, $label, $default ] = $row;
	Components::row(
		$label,
		static function () use ( $key, $colors, $default ): void {
			Components::color( 'bspe[design][colors][' . $key . ']', (string) ( $colors[ $key ] ?? $default ) );
		},
		[ 'id' => 'bspe-design-colors-' . $key ]
	);
}
Components::close_card();

/* ----------------- Sizing & layout ----------------- */
Components::open_card(
	__( 'Sizing & layout', 'bspe-connect' ),
	__( 'Fine-tune the bar button icon size, label size, padding, and the gap between icon and label.', 'bspe-connect' )
);
Components::row(
	__( 'Icon size', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][icon_size]', (int) ( $design['icon_size'] ?? 16 ), [
			'min'    => 12,
			'max'    => 48,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-icon_size',
		'description' => __( 'Default 16 px. Applies to every bar button icon.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Label size', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][label_size]', (int) ( $design['label_size'] ?? 12 ), [
			'min'    => 8,
			'max'    => 20,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-label_size',
		'description' => __( 'Default 12 px. Adjusts the text under each icon.', 'bspe-connect' ),
	]
);
// Per-side button padding. Each input controls one edge so the user can
// fine-tune top / right / bottom / left independently. Legacy
// button_padding_y (pre-v2.2.0) populates top + bottom on first read so
// upgrading installs don't snap back to defaults.
$legacy_pad_y = (int) ( $design['button_padding_y'] ?? -1 );
$pad_defaults = [
	'top'    => $legacy_pad_y >= 0 ? $legacy_pad_y : 6,
	'right'  => 4,
	'bottom' => $legacy_pad_y >= 0 ? $legacy_pad_y : 6,
	'left'   => 4,
];

Components::row(
	__( 'Button padding — top', 'bspe-connect' ),
	static function () use ( $design, $pad_defaults ): void {
		Components::number( 'bspe[design][button_padding_top]', (int) ( $design['button_padding_top'] ?? $pad_defaults['top'] ), [
			'min'    => 0,
			'max'    => 32,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-button_padding_top',
		'description' => __( 'Default 6 px. Space above the icon inside each bar button.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Button padding — right', 'bspe-connect' ),
	static function () use ( $design, $pad_defaults ): void {
		Components::number( 'bspe[design][button_padding_right]', (int) ( $design['button_padding_right'] ?? $pad_defaults['right'] ), [
			'min'    => 0,
			'max'    => 32,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-button_padding_right',
		'description' => __( 'Default 4 px. Space to the right of the icon + label.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Button padding — bottom', 'bspe-connect' ),
	static function () use ( $design, $pad_defaults ): void {
		Components::number( 'bspe[design][button_padding_bottom]', (int) ( $design['button_padding_bottom'] ?? $pad_defaults['bottom'] ), [
			'min'    => 0,
			'max'    => 32,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-button_padding_bottom',
		'description' => __( 'Default 6 px. Space below the label.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Button padding — left', 'bspe-connect' ),
	static function () use ( $design, $pad_defaults ): void {
		Components::number( 'bspe[design][button_padding_left]', (int) ( $design['button_padding_left'] ?? $pad_defaults['left'] ), [
			'min'    => 0,
			'max'    => 32,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-button_padding_left',
		'description' => __( 'Default 4 px. Space to the left of the icon + label.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Icon ↔ label gap', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][icon_label_gap]', (int) ( $design['icon_label_gap'] ?? 2 ), [
			'min'    => 0,
			'max'    => 16,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-icon_label_gap',
		'description' => __( 'Default 2 px. Vertical space between the icon and the label below it.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Label style ----------------- */
Components::open_card(
	__( 'Label style', 'bspe-connect' ),
	__( 'Weight and casing for the label under each bar button.', 'bspe-connect' )
);
Components::row(
	__( 'Label weight', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::select(
			'bspe[design][label_weight]',
			(string) (int) ( $design['label_weight'] ?? 500 ),
			[
				'400' => __( 'Regular (400)',  'bspe-connect' ),
				'500' => __( 'Medium (500) — default', 'bspe-connect' ),
				'600' => __( 'Semibold (600)', 'bspe-connect' ),
				'700' => __( 'Bold (700)',     'bspe-connect' ),
			]
		);
	},
	[
		'id'          => 'bspe-design-label_weight',
		'description' => __( 'Heavier labels feel more like calls-to-action; lighter labels feel quieter.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Uppercase labels', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::toggle( 'bspe[design][label_uppercase]', ! empty( $design['label_uppercase'] ?? true ), [
			'label' => __( 'Render labels in UPPERCASE on the bar', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'On by default for the brand look. Turn off to render labels in their saved case.', 'bspe-connect' ) ]
);
Components::close_card();

/* ----------------- Typography ----------------- */
Components::open_card(
	__( 'Typography', 'bspe-connect' ),
	__( 'Inherit from the active theme, or load a curated Google Font for the bar.', 'bspe-connect' )
);
Components::row(
	__( 'Font source', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::radio_pills( 'bspe[design][font_mode]', (string) ( $design['font_mode'] ?? 'inherit' ), [
			'inherit' => __( 'Inherit from theme', 'bspe-connect' ),
			'google'  => __( 'Google Font', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Google Font', 'bspe-connect' ),
	static function () use ( $design, $google_fonts ): void {
		Components::select( 'bspe[design][google_font]', (string) ( $design['google_font'] ?? 'DM Sans' ), $google_fonts );
	},
	[
		'id'          => 'bspe-design-google_font',
		'description' => __( 'Loaded from fonts.googleapis.com when Font source is Google Font.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
