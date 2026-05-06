<?php
/**
 * BSPE Connect — bottom-fixed contact bar (mobile only).
 *
 * @package BSPE\Connect
 *
 * @var array<int, array<string,mixed>> $buttons        Resolved button list (ordered).
 * @var bool                            $bubble_enabled Whether the welcome bubble should render.
 * @var array<string,mixed>             $bubble         Bubble copy + avatar URL (heading, message, avatar_url).
 * @var string                          $pointer_pos    CSS percent for the bubble pointer (e.g. "12.5%").
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $buttons ) ) {
	return;
}
?>
<div
	class="bspe-connect"
	id="bspe-connect"
	role="region"
	aria-label="<?php esc_attr_e( 'Contact options', 'bspe-connect' ); ?>"
	data-bspe-state="hidden"
>
	<div
		class="bspe-connect__bar"
		data-bspe-bar
		data-bspe-state="hidden"
		data-bspe-button-count="<?php echo esc_attr( (string) count( $buttons ) ); ?>"
	>
		<?php foreach ( $buttons as $btn ) : ?>
			<?php
			$is_link    = 'a' === ( $btn['tag'] ?? 'button' );
			$key        = (string) $btn['key'];
			$label      = (string) $btn['label'];
			$image_src  = isset( $btn['image_src'] ) ? (string) $btn['image_src'] : '';
			$mode       = (string) ( $btn['mode'] ?? '' );
			$icon_url   = (string) ( $btn['icon_url'] ?? '' );
			$show_image = 'connect' === $key && 'image' === $mode && '' !== $image_src;

			$tag_attrs = sprintf(
				'class="bspe-connect__btn bspe-connect__btn--%1$s" data-action="%1$s"',
				esc_attr( $key )
			);
			if ( '' !== $mode ) {
				$tag_attrs .= ' data-bspe-mode="' . esc_attr( $mode ) . '"';
			}
			if ( $is_link ) {
				$tag_attrs .= ' href="' . esc_url( (string) $btn['href'] ) . '"';
			} else {
				$tag_attrs .= ' type="button"';
			}
			?>
			<<?php echo $is_link ? 'a' : 'button'; ?> <?php echo $tag_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs were escaped above ?>>
				<span class="bspe-connect__icon" aria-hidden="true">
					<?php if ( $show_image ) : ?>
						<img
							class="bspe-connect__icon-img"
							src="<?php echo esc_url( $image_src ); ?>"
							alt=""
						/>
					<?php elseif ( '' !== $icon_url ) : ?>
						<span
							class="bspe-connect__icon-svg"
							style="--bspe-icon-url: url(<?php echo esc_url( $icon_url ); ?>);"
						></span>
					<?php endif; ?>
				</span>
				<span class="bspe-connect__label"><?php echo esc_html( $label ); ?></span>
			</<?php echo $is_link ? 'a' : 'button'; ?>>
		<?php endforeach; ?>
	</div>

	<?php
	if ( $bubble_enabled ) {
		require BSPE_CONNECT_DIR . 'public/templates/welcome-bubble.php';
	}

	// Form modal — rendered alongside the bar so JS can show it without a fetch.
	require BSPE_CONNECT_DIR . 'public/templates/form-modal.php';
	?>
</div>
