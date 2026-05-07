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

$brand_palette = [
	[ 'name' => __( 'Plum Noir', 'bspe-connect' ),     'hex' => '#351E28', 'role' => __( 'Primary', 'bspe-connect' ) ],
	[ 'name' => __( 'Midnight Navy', 'bspe-connect' ), 'hex' => '#0D1B2A', 'role' => __( 'Secondary', 'bspe-connect' ) ],
	[ 'name' => __( 'Warm Ivory', 'bspe-connect' ),    'hex' => '#FAF7F2', 'role' => __( 'Background', 'bspe-connect' ) ],
	[ 'name' => __( 'Logo Teal', 'bspe-connect' ),     'hex' => '#3AAFB9', 'role' => __( 'Pop / CTA', 'bspe-connect' ) ],
	[ 'name' => __( 'Gold', 'bspe-connect' ),          'hex' => '#D4AF37', 'role' => __( 'Texture only', 'bspe-connect' ) ],
];

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

/* ----------------- Brand palette reference ----------------- */
Components::open_card(
	__( 'BSPE brand palette', 'bspe-connect' ),
	__( 'Reference colors. Default values below preserve the brand; you can override per-element using the Colors card.', 'bspe-connect' )
);
?>
<div class="bspe-palette">
	<?php foreach ( $brand_palette as $swatch ) : ?>
		<div class="bspe-swatch">
			<span class="bspe-swatch__chip" style="background:<?php echo esc_attr( $swatch['hex'] ); ?>;" aria-hidden="true"></span>
			<span class="bspe-swatch__name"><?php echo esc_html( $swatch['name'] ); ?></span>
			<span class="bspe-swatch__hex"><?php echo esc_html( $swatch['hex'] ); ?></span>
			<span class="bspe-swatch__role"><?php echo esc_html( $swatch['role'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
<?php
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

/* ----------------- Sizing ----------------- */
Components::open_card(
	__( 'Sizing', 'bspe-connect' ),
	__( 'Fine-tune how big the icons and text appear inside each bar button.', 'bspe-connect' )
);
Components::row(
	__( 'Icon size', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][icon_size]', (int) ( $design['icon_size'] ?? 18 ), [
			'min'    => 12,
			'max'    => 48,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-icon_size',
		'description' => __( 'Default 18 px. Applies to all four bar buttons.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Label size', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][label_size]', (int) ( $design['label_size'] ?? 11 ), [
			'min'    => 8,
			'max'    => 20,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-label_size',
		'description' => __( 'Default 11 px. Adjusts the text below each icon.', 'bspe-connect' ),
	]
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
