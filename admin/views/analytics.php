<?php
/**
 * Reserved for the dedicated analytics view. The Submissions & Analytics
 * tab currently uses submissions-list.php; in Phase 5 we may split this
 * out into its own route.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'Analytics', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Funnel visualization and per-event-type counts arrive in Phase 5.', 'bspe-connect' ); ?>
	</p>
</section>
