<?php
/**
 * BSPE Connect — welcome bubble that sits above the bar.
 *
 * @package BSPE\Connect
 *
 * @var array<string,mixed> $bubble      Bubble copy + avatar URL (heading, message, avatar_url).
 * @var string              $pointer_pos CSS percent for the pointer (e.g. "12.5%").
 */

defined( 'ABSPATH' ) || exit;

$heading    = isset( $bubble['heading'] ) ? (string) $bubble['heading'] : '';
$message    = isset( $bubble['message'] ) ? (string) $bubble['message'] : '';
$avatar_url = isset( $bubble['avatar_url'] ) ? (string) $bubble['avatar_url'] : '';
?>
<aside
	class="bspe-connect__bubble"
	data-bspe-bubble
	data-bspe-state="hidden"
	role="complementary"
	aria-label="<?php esc_attr_e( 'Welcome message', 'bspe-connect' ); ?>"
	hidden
	style="--bspe-pointer-pos: <?php echo esc_attr( $pointer_pos ); ?>;"
>
	<div class="bspe-connect__bubble-inner">
		<?php if ( '' !== $avatar_url ) : ?>
			<div class="bspe-connect__bubble-avatar" aria-hidden="true">
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" />
			</div>
		<?php endif; ?>
		<div class="bspe-connect__bubble-body">
			<?php if ( '' !== $heading ) : ?>
				<p class="bspe-connect__bubble-heading"><?php echo esc_html( $heading ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $message ) : ?>
				<p class="bspe-connect__bubble-message"><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		</div>
		<button
			type="button"
			class="bspe-connect__bubble-close"
			data-bspe-bubble-close
			aria-label="<?php esc_attr_e( 'Dismiss welcome message', 'bspe-connect' ); ?>"
		>
			<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
				<path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/>
			</svg>
		</button>
	</div>
</aside>
