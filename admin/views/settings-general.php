<?php
/**
 * Stub view for the General tab. Phase 4 fills this in with master enable,
 * welcome bubble settings, and display behavior controls.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'General', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Master enable, welcome bubble, and display behavior settings will live here. Wired up in Phase 4.', 'bspe-connect' ); ?>
	</p>
	<ul class="bspe-checklist">
		<li><?php esc_html_e( 'Master enable toggle', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Welcome bubble heading, message, avatar, trigger, repeat rule', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Scroll threshold and mobile breakpoint', 'bspe-connect' ); ?></li>
	</ul>
</section>
