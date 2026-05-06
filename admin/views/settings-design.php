<?php
/**
 * Stub view for the Design tab. Showcases the brand palette so the page
 * still feels alive even before the color pickers are wired up.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

$palette = [
	[
		'name'  => __( 'Plum Noir', 'bspe-connect' ),
		'hex'   => '#351E28',
		'role'  => __( 'Primary', 'bspe-connect' ),
	],
	[
		'name'  => __( 'Midnight Navy', 'bspe-connect' ),
		'hex'   => '#0D1B2A',
		'role'  => __( 'Secondary', 'bspe-connect' ),
	],
	[
		'name'  => __( 'Warm Ivory', 'bspe-connect' ),
		'hex'   => '#FAF7F2',
		'role'  => __( 'Background', 'bspe-connect' ),
	],
	[
		'name'  => __( 'Logo Teal', 'bspe-connect' ),
		'hex'   => '#3AAFB9',
		'role'  => __( 'Pop / CTA', 'bspe-connect' ),
	],
	[
		'name'  => __( 'Gold', 'bspe-connect' ),
		'hex'   => '#D4AF37',
		'role'  => __( 'Texture only', 'bspe-connect' ),
	],
];
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'Design', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Firm name, color pickers, and font selection will live here. Defaults use the BSPE brand palette below.', 'bspe-connect' ); ?>
	</p>
	<div class="bspe-palette">
		<?php foreach ( $palette as $swatch ) : ?>
			<div class="bspe-swatch">
				<span class="bspe-swatch__chip" style="background:<?php echo esc_attr( $swatch['hex'] ); ?>;" aria-hidden="true"></span>
				<span class="bspe-swatch__name"><?php echo esc_html( $swatch['name'] ); ?></span>
				<span class="bspe-swatch__hex"><?php echo esc_html( $swatch['hex'] ); ?></span>
				<span class="bspe-swatch__role"><?php echo esc_html( $swatch['role'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</section>
