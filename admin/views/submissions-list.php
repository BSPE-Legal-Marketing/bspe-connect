<?php
/**
 * Stub view for the Submissions & Analytics tab. Submissions table arrives
 * in Phase 4; analytics dashboard arrives in Phase 5.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'Submissions & Analytics', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Lead submissions table with filters and CSV export, plus the conversion funnel and top-pages dashboard, will live here.', 'bspe-connect' ); ?>
	</p>
	<ul class="bspe-checklist">
		<li><?php esc_html_e( 'Paginated submissions table with date / source / status filters', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'CSV export of the current filtered view', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Per-event-type counts, conversion funnel, top pages', 'bspe-connect' ); ?></li>
	</ul>
</section>
