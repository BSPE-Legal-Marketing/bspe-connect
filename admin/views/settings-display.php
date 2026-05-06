<?php
/**
 * Stub view for the Display Rules tab.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'Display Rules', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Decide where the bar should appear: site-wide, pages only, posts only, or a custom slug list.', 'bspe-connect' ); ?>
	</p>
	<ul class="bspe-checklist">
		<li><?php esc_html_e( 'Site-wide / pages-only / posts-only modes', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Comma-separated slug exclude/include list', 'bspe-connect' ); ?></li>
	</ul>
</section>
