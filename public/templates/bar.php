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
			$is_link      = 'a' === ( $btn['tag'] ?? 'button' );
			$key          = (string) $btn['key'];
			$label        = (string) $btn['label'];
			$image_src    = isset( $btn['image_src'] ) ? (string) $btn['image_src'] : '';
			$mode         = (string) ( $btn['mode'] ?? '' );
			$icon_library = (string) ( $btn['icon_library'] ?? 'brand' );
			$icon_name    = (string) ( $btn['icon'] ?? '' );
			$icon_url     = (string) ( $btn['icon_url'] ?? '' );
			$show_image   = 'connect' === $key && 'image' === $mode && '' !== $image_src;

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
				<?php if ( 'none' !== $icon_library ) : ?>
					<span class="bspe-connect__icon" aria-hidden="true">
						<?php
						if ( $show_image ) :
							?>
							<img class="bspe-connect__icon-img" src="<?php echo esc_url( $image_src ); ?>" alt="" />
							<?php
						elseif ( 'brand' === $icon_library && '' !== $icon_url ) :
							?>
							<span class="bspe-connect__icon-svg" style="--bspe-icon-url: url(<?php echo esc_url( $icon_url ); ?>);"></span>
							<?php
						elseif ( 0 === strpos( $icon_library, 'fa-' ) && '' !== $icon_name ) :
							$fa_style = substr( $icon_library, 3 );
							$fa_class = 'fa-' . preg_replace( '/[^a-z0-9-]/i', '', $fa_style ) . ' fa-' . preg_replace( '/[^a-z0-9-]/i', '', $icon_name );
							?>
							<i class="bspe-connect__icon-fa <?php echo esc_attr( $fa_class ); ?>" aria-hidden="true"></i>
							<?php
						elseif ( 0 === strpos( $icon_library, 'ion-' ) && '' !== $icon_name ) :
							$ion_variant = substr( $icon_library, 4 );
							$ion_clean   = preg_replace( '/[^a-z0-9-]/i', '', $icon_name );
							$ion_full    = ( 'outline' === $ion_variant && substr( $ion_clean, -8 ) !== '-outline' )
								? $ion_clean . '-outline'
								: $ion_clean;
							?>
							<ion-icon class="bspe-connect__icon-ion" name="<?php echo esc_attr( $ion_full ); ?>" aria-hidden="true"></ion-icon>
							<?php
						elseif ( 'dripicons' === $icon_library && '' !== $icon_name ) :
							$drip_class = preg_replace( '/[^a-z0-9-]/i', '', $icon_name );
							?>
							<i class="bspe-connect__icon-drip <?php echo esc_attr( $drip_class ); ?>" aria-hidden="true"></i>
							<?php
						endif;
						?>
					</span>
				<?php endif; ?>
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
