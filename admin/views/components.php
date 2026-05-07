<?php
/**
 * Reusable form-field renderers for the BSPE Connect admin views.
 *
 * Each public method echoes HTML directly. Methods return the option array
 * after merging defaults so callers can chain helpers (e.g. wrap a control
 * in a row with the same opts).
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

defined( 'ABSPATH' ) || exit;

final class Components {

	/* -----------------------------------------------------------------
	 * Layout
	 * ----------------------------------------------------------------- */

	public static function open_form( string $tab, string $action_url ): void {
		?>
		<form class="bspe-form" method="post" action="<?php echo esc_url( $action_url ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Saver::ACTION ); ?>" />
			<input type="hidden" name="_tab" value="<?php echo esc_attr( $tab ); ?>" />
			<?php wp_nonce_field( Settings_Saver::NONCE_ACTION ); ?>
		<?php
	}

	public static function close_form( string $submit_label = 'Save changes' ): void {
		?>
			<div class="bspe-form__actions">
				<button
					type="submit"
					class="bspe-button bspe-button--primary"
					data-bspe-save-button
					title="<?php esc_attr_e( 'No unsaved changes', 'bspe-connect' ); ?>"
				>
					<?php echo esc_html( $submit_label ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	public static function open_card( string $title, string $description = '', array $opts = [] ): void {
		$pill = $opts['pill'] ?? '';
		?>
		<section class="bspe-card bspe-card--settings">
			<header class="bspe-card__head">
				<div class="bspe-card__head-text">
					<h2><?php echo esc_html( $title ); ?></h2>
					<?php if ( '' !== $description ) : ?>
						<p class="bspe-card__lead"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $pill ) : ?>
					<span class="bspe-pill bspe-pill--phase"><?php echo esc_html( $pill ); ?></span>
				<?php endif; ?>
			</header>
		<?php
	}

	public static function close_card(): void {
		echo '</section>';
	}

	/**
	 * Wraps a control inside a labelled row with optional description.
	 *
	 * @param array{
	 *   id?: string,
	 *   description?: string,
	 *   inline?: bool,
	 * } $opts
	 */
	public static function row( string $label, callable $render_control, array $opts = [] ): void {
		$id          = $opts['id']          ?? '';
		$description = $opts['description'] ?? '';
		$inline      = $opts['inline']      ?? false;
		$data        = $opts['data']        ?? [];
		$class       = 'bspe-row' . ( $inline ? ' bspe-row--inline' : '' );

		$data_attrs = '';
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data_attrs .= ' data-' . esc_attr( (string) $key ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs escaped above ?>>
			<div class="bspe-row__label-col">
				<?php if ( '' !== $id ) : ?>
					<label for="<?php echo esc_attr( $id ); ?>" class="bspe-row__label"><?php echo esc_html( $label ); ?></label>
				<?php else : ?>
					<span class="bspe-row__label"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $description ) : ?>
					<p class="bspe-row__description"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
			<div class="bspe-row__control-col">
				<?php $render_control(); ?>
			</div>
		</div>
		<?php
	}

	/* -----------------------------------------------------------------
	 * Inputs
	 * ----------------------------------------------------------------- */

	/**
	 * @param array{
	 *   id?: string,
	 *   placeholder?: string,
	 *   type?: string,
	 *   required?: bool,
	 *   maxlength?: int,
	 *   inputmode?: string,
	 *   autocomplete?: string,
	 *   pattern?: string,
	 * } $opts
	 */
	public static function text( string $name, string $value, array $opts = [] ): void {
		$attrs = self::base_attrs( $name, $opts );
		$type  = (string) ( $opts['type'] ?? 'text' );
		?>
		<input type="<?php echo esc_attr( $type ); ?>"
			class="bspe-input"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		/>
		<?php
	}

	public static function textarea( string $name, string $value, array $opts = [] ): void {
		$attrs = self::base_attrs( $name, $opts );
		$rows  = (int) ( $opts['rows'] ?? 3 );
		?>
		<textarea
			class="bspe-input bspe-textarea"
			name="<?php echo esc_attr( $name ); ?>"
			rows="<?php echo esc_attr( (string) $rows ); ?>"
			<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public static function number( string $name, int $value, array $opts = [] ): void {
		$attrs = self::base_attrs( $name, $opts );
		$min   = $opts['min'] ?? null;
		$max   = $opts['max'] ?? null;
		$step  = $opts['step'] ?? 1;
		$suffix = (string) ( $opts['suffix'] ?? '' );
		?>
		<div class="bspe-number">
			<input type="number"
				class="bspe-input bspe-input--number"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( (string) $value ); ?>"
				<?php echo null !== $min ? 'min="' . esc_attr( (string) $min ) . '"' : ''; ?>
				<?php echo null !== $max ? 'max="' . esc_attr( (string) $max ) . '"' : ''; ?>
				step="<?php echo esc_attr( (string) $step ); ?>"
				<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			/>
			<?php if ( '' !== $suffix ) : ?>
				<span class="bspe-number__suffix"><?php echo esc_html( $suffix ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string,string> $options Map of value => label.
	 */
	public static function select( string $name, string $value, array $options, array $opts = [] ): void {
		$attrs = self::base_attrs( $name, $opts );
		?>
		<div class="bspe-select-wrap">
			<select
				class="bspe-input bspe-select"
				name="<?php echo esc_attr( $name ); ?>"
				<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			>
				<?php foreach ( $options as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( (string) $option_value, $value ); ?>>
						<?php echo esc_html( (string) $option_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Pill-style toggle switch for boolean values.
	 *
	 * @param array{ id?: string, label?: string, description?: string } $opts
	 */
	public static function toggle( string $name, bool $checked, array $opts = [] ): void {
		$id    = $opts['id']    ?? self::auto_id( $name );
		$label = $opts['label'] ?? '';
		?>
		<label class="bspe-toggle" for="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
			<input type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				class="bspe-toggle__input"
				name="<?php echo esc_attr( $name ); ?>"
				value="1"
				<?php checked( $checked ); ?>
				<?php echo isset( $opts['data'] ) ? self::data_attrs( $opts['data'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			/>
			<span class="bspe-toggle__track" aria-hidden="true">
				<span class="bspe-toggle__thumb"></span>
			</span>
			<?php if ( '' !== $label ) : ?>
				<span class="bspe-toggle__label"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Standard checkbox with custom styling.
	 *
	 * @param array{ id?: string, label?: string, disabled?: bool } $opts
	 */
	public static function checkbox( string $name, bool $checked, array $opts = [] ): void {
		$id       = $opts['id']       ?? self::auto_id( $name );
		$label    = $opts['label']    ?? '';
		$disabled = ! empty( $opts['disabled'] );
		?>
		<label class="bspe-check<?php echo $disabled ? ' is-disabled' : ''; ?>" for="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
			<input type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				class="bspe-check__input"
				name="<?php echo esc_attr( $name ); ?>"
				value="1"
				<?php checked( $checked ); ?>
				<?php disabled( $disabled ); ?>
				<?php echo isset( $opts['data'] ) ? self::data_attrs( $opts['data'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			/>
			<span class="bspe-check__box" aria-hidden="true">
				<svg viewBox="0 0 14 14" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M2.5 7.5l3 3 6-7"/>
				</svg>
			</span>
			<?php if ( '' !== $label ) : ?>
				<span class="bspe-check__label"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Radio group rendered as pill buttons.
	 *
	 * @param array<string,string> $options
	 * @param array{ id?: string } $opts
	 */
	public static function radio_pills( string $name, string $value, array $options, array $opts = [] ): void {
		$base_id = $opts['id'] ?? self::auto_id( $name );
		?>
		<div class="bspe-radio-pills" role="radiogroup">
			<?php foreach ( $options as $option_value => $option_label ) :
				$option_value = (string) $option_value;
				$option_id    = $base_id . '-' . sanitize_html_class( $option_value );
				$is_checked   = $value === $option_value;
				?>
				<label class="bspe-radio-pill<?php echo $is_checked ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $option_id ); ?>">
					<input type="radio"
						id="<?php echo esc_attr( $option_id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $option_value ); ?>"
						<?php checked( $is_checked ); ?>
					/>
					<span class="bspe-radio-pill__label"><?php echo esc_html( (string) $option_label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Color picker with built-in WP wp_color_picker.
	 *
	 * @param array{ id?: string } $opts
	 */
	public static function color( string $name, string $value, array $opts = [] ): void {
		$id = $opts['id'] ?? self::auto_id( $name );
		?>
		<div class="bspe-color">
			<input type="text"
				id="<?php echo esc_attr( $id ); ?>"
				class="bspe-color-input"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				data-default-color="<?php echo esc_attr( $value ); ?>"
				data-bspe-color
			/>
		</div>
		<?php
	}

	/**
	 * Media Library picker — stores attachment ID in a hidden input,
	 * shows a preview, with Choose / Replace / Remove buttons.
	 *
	 * @param array{ id?: string, button_text?: string, modal_title?: string } $opts
	 */
	public static function media( string $name, int $attachment_id, array $opts = [] ): void {
		$id           = $opts['id']           ?? self::auto_id( $name );
		$button_text  = (string) ( $opts['button_text']  ?? __( 'Choose image', 'bspe-connect' ) );
		$modal_title  = (string) ( $opts['modal_title']  ?? __( 'Select image', 'bspe-connect' ) );

		$preview_url = $attachment_id > 0 ? (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		?>
		<div class="bspe-media" data-bspe-media data-modal-title="<?php echo esc_attr( $modal_title ); ?>">
			<input type="hidden"
				id="<?php echo esc_attr( $id ); ?>"
				class="bspe-media__id"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( (string) $attachment_id ); ?>"
				data-bspe-media-id
			/>
			<div class="bspe-media__preview" data-bspe-media-preview>
				<?php if ( '' !== $preview_url ) : ?>
					<img src="<?php echo esc_url( $preview_url ); ?>" alt="" />
				<?php else : ?>
					<span class="bspe-media__placeholder" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="1.5"/><path d="M3 16l5-5 5 5 4-4 4 4"/></svg>
					</span>
				<?php endif; ?>
			</div>
			<div class="bspe-media__actions">
				<button type="button" class="bspe-button bspe-button--secondary" data-bspe-media-pick>
					<?php echo esc_html( $button_text ); ?>
				</button>
				<button type="button" class="bspe-button bspe-button--ghost" data-bspe-media-remove<?php echo $attachment_id > 0 ? '' : ' hidden'; ?>>
					<?php esc_html_e( 'Remove', 'bspe-connect' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Icon radio: 4 SVG previews per button type. Each preview is a labeled
	 * radio. The selected one gets a teal-tinted border.
	 *
	 * @param array{ id?: string } $opts
	 */
	public static function icon_radio( string $name, string $value, string $type, array $opts = [] ): void {
		$base_id = $opts['id'] ?? self::auto_id( $name );
		?>
		<div class="bspe-icon-radio" role="radiogroup">
			<?php for ( $i = 1; $i <= 3; $i++ ) :
				$icon_key = $type . '-' . $i;
				$icon_url = BSPE_CONNECT_URL . 'public/assets/icons/' . $icon_key . '.svg';
				$option_id = $base_id . '-' . $i;
				$is_checked = $value === $icon_key;
				?>
				<label class="bspe-icon-radio__item<?php echo $is_checked ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $option_id ); ?>">
					<input type="radio"
						id="<?php echo esc_attr( $option_id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $icon_key ); ?>"
						<?php checked( $is_checked ); ?>
					/>
					<span class="bspe-icon-radio__preview" aria-hidden="true" style="--icon-url: url('<?php echo esc_url( $icon_url ); ?>');"></span>
					<span class="bspe-icon-radio__label"><?php echo esc_html( '0' . $i ); ?></span>
				</label>
			<?php endfor; ?>
		</div>
		<?php
	}

	/**
	 * Visual icon picker for non-brand libraries (Font Awesome, Ionicons,
	 * Dripicons). Renders a curated set of icons for the given button type
	 * + library, showing the live icon glyph from the library's CSS / web
	 * component so the user can see what they'll get on the bar.
	 *
	 * @param string $name    Form input name (e.g. bspe[buttons][call][icon]).
	 * @param string $value   Currently selected icon name.
	 * @param string $type    Button key — 'connect' / 'call' / 'text' / 'email'.
	 * @param string $library Library slug — 'fa-solid' / 'fa-regular' / 'ion-filled' / 'ion-outline' / 'dripicons'.
	 */
	public static function library_icon_picker( string $name, string $value, string $type, string $library ): void {
		$catalog = self::icon_catalog();
		$items   = $catalog[ $type ][ $library ] ?? [];
		if ( empty( $items ) ) {
			echo '<p class="bspe-row__description">' . esc_html__( 'No curated icons for this combination yet — pick a different library.', 'bspe-connect' ) . '</p>';
			return;
		}

		$base_id = self::auto_id( $name );
		?>
		<div class="bspe-icon-radio bspe-icon-radio--library" role="radiogroup">
			<?php foreach ( $items as $i => $icon_name ) :
				$option_id  = $base_id . '-' . sanitize_html_class( $icon_name );
				$is_checked = $value === $icon_name;
				?>
				<label class="bspe-icon-radio__item<?php echo $is_checked ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $option_id ); ?>" title="<?php echo esc_attr( $icon_name ); ?>">
					<input type="radio"
						id="<?php echo esc_attr( $option_id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $icon_name ); ?>"
						<?php checked( $is_checked ); ?>
					/>
					<span class="bspe-icon-radio__preview-lib" aria-hidden="true">
						<?php self::render_library_glyph( $library, $icon_name ); ?>
					</span>
					<span class="bspe-icon-radio__label"><?php echo esc_html( self::short_label( $library, $icon_name, $i ) ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Curated icon catalog keyed by [button type][library]. Surfaces ~3-5
	 * icons per cell that fit the button's purpose. Adding more is a
	 * one-line append; the saver allowlist accepts any kebab-case slug
	 * for the third-party libraries so users can also save custom names
	 * by editing settings in code if they need to.
	 *
	 * @return array<string, array<string, string[]>>
	 */
	public static function icon_catalog(): array {
		return [
			'connect' => [
				'fa-solid'    => [ 'comment', 'comment-dots', 'comments', 'message', 'paper-plane' ],
				'fa-regular'  => [ 'comment', 'comment-dots', 'comments', 'message', 'paper-plane' ],
			],
			'call'    => [
				'fa-solid'    => [ 'phone', 'mobile-screen-button', 'mobile', 'phone-flip', 'phone-volume' ],
				'fa-regular'  => [ 'phone', 'mobile-screen-button', 'mobile', 'phone-flip', 'phone-volume' ],
			],
			'text'    => [
				'fa-solid'    => [ 'comment', 'comment-dots', 'comments', 'message', 'paper-plane' ],
				'fa-regular'  => [ 'comment', 'comment-dots', 'comments', 'message', 'paper-plane' ],
			],
			'email'   => [
				'fa-solid'    => [ 'envelope', 'envelope-open', 'paper-plane', 'at' ],
				'fa-regular'  => [ 'envelope', 'envelope-open', 'paper-plane', 'at' ],
			],
		];
	}

	private static function render_library_glyph( string $library, string $icon_name ): void {
		if ( 'fa-solid' === $library || 'fa-regular' === $library ) {
			$style = substr( $library, 3 );
			echo '<i class="fa-' . esc_attr( $style ) . ' fa-' . esc_attr( $icon_name ) . '" aria-hidden="true"></i>';
		}
	}

	private static function short_label( string $library, string $icon_name, int $index ): string {
		// Use the icon's last word as a quick label, capped to 9 chars.
		$bits = explode( '-', $icon_name );
		$last = end( $bits );
		return strlen( $last ) > 0 ? substr( $last, 0, 12 ) : '0' . ( $index + 1 );
	}

	/* -----------------------------------------------------------------
	 * Internals
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $opts
	 */
	private static function base_attrs( string $name, array $opts ): string {
		$id          = $opts['id']          ?? self::auto_id( $name );
		$placeholder = $opts['placeholder'] ?? '';
		$required    = ! empty( $opts['required'] );
		$maxlength   = $opts['maxlength']   ?? null;
		$inputmode   = $opts['inputmode']   ?? null;
		$autocomplete= $opts['autocomplete']?? null;
		$pattern     = $opts['pattern']     ?? null;
		$disabled    = ! empty( $opts['disabled'] );

		$attrs  = 'id="' . esc_attr( $id ) . '"';
		$attrs .= ' placeholder="' . esc_attr( (string) $placeholder ) . '"';
		if ( $required ) {
			$attrs .= ' required aria-required="true"';
		}
		if ( null !== $maxlength ) {
			$attrs .= ' maxlength="' . esc_attr( (string) $maxlength ) . '"';
		}
		if ( null !== $inputmode ) {
			$attrs .= ' inputmode="' . esc_attr( (string) $inputmode ) . '"';
		}
		if ( null !== $autocomplete ) {
			$attrs .= ' autocomplete="' . esc_attr( (string) $autocomplete ) . '"';
		}
		if ( null !== $pattern ) {
			$attrs .= ' pattern="' . esc_attr( (string) $pattern ) . '"';
		}
		if ( $disabled ) {
			$attrs .= ' disabled';
		}
		if ( isset( $opts['data'] ) && is_array( $opts['data'] ) ) {
			$attrs .= ' ' . self::data_attrs( $opts['data'] );
		}
		return $attrs;
	}

	/**
	 * Auto-generate a usable HTML id from a name attribute like "bspe[a][b]".
	 */
	private static function auto_id( string $name ): string {
		$id = preg_replace( '/[\[\]]+/', '-', $name );
		$id = trim( (string) $id, '-' );
		return 'bspe-' . sanitize_html_class( $id );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function data_attrs( array $data ): string {
		$out = [];
		foreach ( $data as $key => $value ) {
			$out[] = 'data-' . esc_attr( (string) $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return implode( ' ', $out );
	}
}
