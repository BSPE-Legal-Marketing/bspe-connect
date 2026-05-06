<?php
/**
 * BSPE Connect — success state shown inside the modal after a successful
 * submission. The success message text is swapped client-side from the
 * AJAX response so it can reflect the latest setting without a page reload.
 *
 * @package BSPE\Connect
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;

$default_msg = (string) Settings::get( 'form.success_msg', '' );
if ( '' === $default_msg ) {
	$default_msg = __( "Thanks. We'll be in touch shortly.", 'bspe-connect' );
}
?>
<div class="bspe-connect__success-inner">
	<div class="bspe-connect__success-icon" aria-hidden="true">
		<svg viewBox="0 0 48 48" width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2" opacity="0.18"/>
			<circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2" stroke-dasharray="138" stroke-dashoffset="0" class="bspe-connect__success-ring"/>
			<path d="M14 24l7 7 13-15" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="bspe-connect__success-check"/>
		</svg>
	</div>
	<p class="bspe-connect__success-message" data-bspe-success-message>
		<?php echo esc_html( $default_msg ); ?>
	</p>
</div>
