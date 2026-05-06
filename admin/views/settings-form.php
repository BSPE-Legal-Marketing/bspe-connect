<?php
/**
 * Stub view for the Form tab.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="bspe-card">
	<header class="bspe-card__head">
		<h2><?php esc_html_e( 'Form', 'bspe-connect' ); ?></h2>
		<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( sprintf( /* translators: %d: phase number */ __( 'Phase %d', 'bspe-connect' ), $current_phase ) ); ?></span>
	</header>
	<p class="bspe-card__lead">
		<?php esc_html_e( 'Lead capture form configuration: visible/required fields, copy, email delivery, and anti-spam.', 'bspe-connect' ); ?>
	</p>
	<ul class="bspe-checklist">
		<li><?php esc_html_e( 'Field visibility & required toggles (Name, Phone, Email, Preferred contact, Message)', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Form headings, submit label, success message', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Mail delivery (to, subject, from)', 'bspe-connect' ); ?></li>
		<li><?php esc_html_e( 'Anti-spam: honeypot, time-trap, rate limit, Cloudflare Turnstile', 'bspe-connect' ); ?></li>
	</ul>
</section>
