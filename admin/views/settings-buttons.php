<?php
/**
 * Stub view for the Buttons tab.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'Buttons', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Per-button configuration: Connect, Call, Text, Email — labels, icons, phone numbers, modes.', 'bspe-connect' ); ?>
	</p>
	<div class="bspe-button-preview">
		<div class="bspe-button-preview__chip"><?php esc_html_e( 'Connect', 'bspe-connect' ); ?></div>
		<div class="bspe-button-preview__chip"><?php esc_html_e( 'Call', 'bspe-connect' ); ?></div>
		<div class="bspe-button-preview__chip"><?php esc_html_e( 'Text', 'bspe-connect' ); ?></div>
		<div class="bspe-button-preview__chip"><?php esc_html_e( 'Email', 'bspe-connect' ); ?></div>
	</div>
</section>
