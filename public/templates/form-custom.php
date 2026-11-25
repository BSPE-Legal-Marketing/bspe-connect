<?php
/**
 * BSPE Connect — custom (multi-step) form body.
 *
 * Included by form-modal.php inside the <form> when Custom_Form::is_active().
 * Renders the progress indicator, one <fieldset> per step, and the Back /
 * Next / Submit navigation. Every input is named bspe_f[<key>] so it can never
 * collide with the handler's own control fields (action, nonce, honeypot…).
 *
 * Step switching and per-step validation live in bspe-connect.js; the server
 * re-validates everything against the stored schema (Custom_Form::collect).
 *
 * @package BSPE\Connect
 *
 * @var bool   $turnstile_enabled
 * @var string $turnstile_site_key
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Custom_Form;

$cf_config  = Custom_Form::config();
$cf_steps   = (array) $cf_config['steps'];
$cf_total   = count( $cf_steps );
$cf_roles   = array_flip( Custom_Form::effective_roles( $cf_config ) ); // key => role
$cf_format  = (string) $cf_config['progress_format'];
$cf_submit  = (string) ( $cf_config['submit_label'] ?: __( 'Submit', 'bspe-connect' ) );
$cf_next    = (string) ( $cf_config['next_label'] ?: __( 'Next', 'bspe-connect' ) );
$cf_back    = (string) ( $cf_config['back_label'] ?: __( 'Back', 'bspe-connect' ) );
$cf_show_pg = ! empty( $cf_config['show_progress'] ) && $cf_total > 1;

$cf_progress_text = static function ( int $current ) use ( $cf_format, $cf_total ): string {
	return strtr( $cf_format, [ '{current}' => (string) $current, '{total}' => (string) $cf_total ] );
};

$cf_autocomplete = static function ( array $field ) use ( $cf_roles ): string {
	$by_role = [
		'first_name' => 'given-name',
		'last_name'  => 'family-name',
		'full_name'  => 'name',
		'email'      => 'email',
		'phone'      => 'tel',
	];
	$role = $cf_roles[ $field['key'] ] ?? '';
	if ( isset( $by_role[ $role ] ) ) {
		return $by_role[ $role ];
	}
	$by_key = [
		'city'    => 'address-level2',
		'state'   => 'address-level1',
		'zip'     => 'postal-code',
		'zipcode' => 'postal-code',
		'address' => 'street-address',
		'company' => 'organization',
	];
	return $by_key[ $field['key'] ] ?? 'off';
};

$cf_allowed_label_html = [
	'a'      => [ 'href' => [], 'target' => [], 'rel' => [] ],
	'strong' => [],
	'b'      => [],
	'em'     => [],
	'i'      => [],
];

$cf_render_field = static function ( array $field ) use ( $cf_autocomplete, $cf_allowed_label_html ): void {
	$key         = (string) $field['key'];
	$type        = (string) $field['type'];
	$id          = 'bspe-cf-' . $key;
	$name        = Custom_Form::POST_KEY . '[' . $key . ']';
	$label       = Custom_Form::plain_label( $field );
	$required    = ! empty( $field['required'] );
	$placeholder = '' !== (string) $field['placeholder'] ? (string) $field['placeholder'] : $label;
	$placeholder = $required ? $placeholder . ' *' : $placeholder;
	$default     = (string) ( $field['default'] ?? '' );
	$help        = (string) ( $field['help'] ?? '' );
	$visible_lbl = ! empty( $field['show_label'] ) || in_array( $type, [ 'radio', 'checkboxes', 'date' ], true );
	$req_attr    = $required ? ' required aria-required="true"' : '';
	$describedby = '' !== $help ? ' aria-describedby="' . esc_attr( $id . '-help' ) . '"' : '';

	if ( 'hidden' === $type ) {
		printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $name ), esc_attr( $default ) );
		return;
	}

	$classes = [ 'bspe-connect__field', 'bspe-connect__field--' . $type ];
	if ( 'half' === ( $field['width'] ?? 'full' ) ) {
		$classes[] = 'is-half';
	}
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		data-field="<?php echo esc_attr( $key ); ?>"
		data-type="<?php echo esc_attr( $type ); ?>"
		<?php echo $required ? 'data-required="1"' : ''; ?>
		<?php if ( 'number' === $type ) : ?>
			data-min="<?php echo esc_attr( null === ( $field['min'] ?? null ) ? '' : (string) $field['min'] ); ?>"
			data-max="<?php echo esc_attr( null === ( $field['max'] ?? null ) ? '' : (string) $field['max'] ); ?>"
		<?php endif; ?>
		<?php if ( 'tel' === $type ) : ?>
			data-phone-format="<?php echo esc_attr( (string) ( $field['phone_format'] ?? 'us' ) ); ?>"
		<?php endif; ?>
	>
		<?php if ( in_array( $type, [ 'radio', 'checkboxes' ], true ) ) : ?>
			<span class="bspe-connect__group-label" id="<?php echo esc_attr( $id . '-label' ); ?>">
				<?php echo esc_html( $label ); ?><?php echo $required ? ' <span class="bspe-connect__required" aria-hidden="true">*</span>' : ''; ?>
			</span>
			<div class="bspe-connect__choices" role="<?php echo 'radio' === $type ? 'radiogroup' : 'group'; ?>" aria-labelledby="<?php echo esc_attr( $id . '-label' ); ?>"<?php echo $describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
				<?php foreach ( (array) $field['options'] as $i => $opt ) :
					$opt_id = $id . '-' . $i;
					?>
					<label class="bspe-connect__choice" for="<?php echo esc_attr( $opt_id ); ?>">
						<input
							id="<?php echo esc_attr( $opt_id ); ?>"
							type="<?php echo 'radio' === $type ? 'radio' : 'checkbox'; ?>"
							name="<?php echo esc_attr( 'radio' === $type ? $name : $name . '[]' ); ?>"
							value="<?php echo esc_attr( (string) $opt['value'] ); ?>"
							<?php checked( $default, (string) $opt['value'] ); ?>
						/>
						<span><?php echo esc_html( (string) $opt['label'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

		<?php elseif ( 'checkbox' === $type ) : ?>
			<label class="bspe-connect__choice bspe-connect__choice--single" for="<?php echo esc_attr( $id ); ?>">
				<input id="<?php echo esc_attr( $id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $req_attr . $describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static / escaped. ?> <?php checked( '' !== $default && '0' !== $default ); ?> />
				<span><?php echo wp_kses( (string) $field['label'], $cf_allowed_label_html ); ?><?php echo $required ? ' <span class="bspe-connect__required" aria-hidden="true">*</span>' : ''; ?></span>
			</label>

		<?php else : ?>
			<label for="<?php echo esc_attr( $id ); ?>" class="<?php echo $visible_lbl ? 'bspe-connect__label-visible' : 'bspe-connect__label'; ?>">
				<?php echo esc_html( $label ); ?><?php echo ( $visible_lbl && $required ) ? ' <span class="bspe-connect__required" aria-hidden="true">*</span>' : ''; ?>
			</label>

			<?php if ( 'textarea' === $type ) : ?>
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					class="bspe-connect__input bspe-connect__textarea"
					name="<?php echo esc_attr( $name ); ?>"
					rows="<?php echo esc_attr( (string) ( $field['rows'] ?? 4 ) ); ?>"
					maxlength="<?php echo esc_attr( (string) ( $field['maxlength'] ?? 5000 ) ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					<?php echo $req_attr . $describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static / escaped. ?>
				><?php echo esc_textarea( $default ); ?></textarea>

			<?php elseif ( 'select' === $type ) : ?>
				<select
					id="<?php echo esc_attr( $id ); ?>"
					class="bspe-connect__input bspe-connect__select"
					name="<?php echo esc_attr( $name ); ?>"
					autocomplete="<?php echo esc_attr( $cf_autocomplete( $field ) ); ?>"
					<?php echo $req_attr . $describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static / escaped. ?>
				>
					<option value=""><?php echo esc_html( $placeholder ); ?></option>
					<?php foreach ( (array) $field['options'] as $opt ) : ?>
						<option value="<?php echo esc_attr( (string) $opt['value'] ); ?>" <?php selected( $default, (string) $opt['value'] ); ?>><?php echo esc_html( (string) $opt['label'] ); ?></option>
					<?php endforeach; ?>
				</select>

			<?php else :
				$input_type = 'text';
				$extra      = '';
				switch ( $type ) {
					case 'email':
						$input_type = 'email';
						$extra      = ' inputmode="email" maxlength="255"';
						break;
					case 'tel':
						$input_type = 'tel';
						$extra      = ' inputmode="tel" maxlength="20"' . ( 'us' === ( $field['phone_format'] ?? 'us' ) ? ' data-bspe-phone-mask' : '' );
						break;
					case 'number':
						$extra = ' inputmode="decimal" maxlength="20"';
						break;
					case 'date':
						$input_type = 'date';
						break;
					default:
						$extra = ' maxlength="' . esc_attr( (string) ( $field['maxlength'] ?? 255 ) ) . '"';
				}
				?>
				<input
					id="<?php echo esc_attr( $id ); ?>"
					class="bspe-connect__input"
					type="<?php echo esc_attr( $input_type ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $default ); ?>"
					autocomplete="<?php echo esc_attr( $cf_autocomplete( $field ) ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					<?php echo $extra . $req_attr . $describedby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static / escaped. ?>
				/>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( '' !== $help ) : ?>
			<p class="bspe-connect__help" id="<?php echo esc_attr( $id . '-help' ); ?>"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
		<p class="bspe-connect__field-error" data-bspe-error="<?php echo esc_attr( $key ); ?>" role="alert" hidden></p>
	</div>
	<?php
};
?>
<div class="bspe-connect__steps" data-bspe-steps data-step-count="<?php echo esc_attr( (string) $cf_total ); ?>">

	<?php if ( $cf_show_pg ) : ?>
		<div class="bspe-connect__progress" data-bspe-progress data-format="<?php echo esc_attr( $cf_format ); ?>">
			<span class="bspe-connect__progress-label" data-bspe-progress-label aria-live="polite"><?php echo esc_html( $cf_progress_text( 1 ) ); ?></span>
			<span class="bspe-connect__progress-track" aria-hidden="true">
				<span class="bspe-connect__progress-fill" data-bspe-progress-fill style="width:<?php echo esc_attr( (string) round( 100 / max( 1, $cf_total ), 2 ) ); ?>%"></span>
			</span>
		</div>
	<?php endif; ?>

	<?php foreach ( $cf_steps as $cf_i => $cf_step ) :
		$cf_title    = (string) ( $cf_step['title'] ?? '' );
		$cf_subtitle = (string) ( $cf_step['subtitle'] ?? '' );
		$cf_is_last  = ( $cf_i === $cf_total - 1 );
		?>
		<fieldset class="bspe-connect__step" data-bspe-step="<?php echo esc_attr( (string) $cf_i ); ?>" <?php echo 0 === $cf_i ? '' : 'hidden'; ?>>
			<legend class="<?php echo '' !== $cf_title ? 'bspe-connect__step-title' : 'bspe-connect__label'; ?>">
				<?php echo esc_html( '' !== $cf_title ? $cf_title : $cf_progress_text( $cf_i + 1 ) ); ?>
			</legend>
			<?php if ( '' !== $cf_subtitle ) : ?>
				<p class="bspe-connect__step-subtitle"><?php echo esc_html( $cf_subtitle ); ?></p>
			<?php endif; ?>

			<div class="bspe-connect__grid">
				<?php foreach ( (array) $cf_step['fields'] as $cf_field ) {
					$cf_render_field( (array) $cf_field );
				} ?>
			</div>

			<?php if ( $cf_is_last && $turnstile_enabled && '' !== $turnstile_site_key ) : ?>
				<div class="bspe-connect__turnstile">
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>" data-theme="light"></div>
				</div>
			<?php endif; ?>
		</fieldset>
	<?php endforeach; ?>

	<div class="bspe-connect__step-nav<?php echo $cf_total > 1 ? '' : ' is-single'; ?>">
		<button type="button" class="bspe-connect__back" data-bspe-back hidden>
			<svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3L5 8l5 5"/></svg>
			<span><?php echo esc_html( $cf_back ); ?></span>
		</button>
		<?php if ( $cf_total > 1 ) : ?>
			<button type="button" class="bspe-connect__submit bspe-connect__next" data-bspe-next>
				<span class="bspe-connect__submit-label"><?php echo esc_html( $cf_next ); ?></span>
			</button>
		<?php endif; ?>
		<button type="submit" class="bspe-connect__submit" data-bspe-submit <?php echo $cf_total > 1 ? 'hidden' : ''; ?>>
			<span class="bspe-connect__submit-label" data-bspe-submit-label><?php echo esc_html( $cf_submit ); ?></span>
			<span class="bspe-connect__submit-spinner" data-bspe-submit-spinner aria-hidden="true"></span>
		</button>
	</div>
</div>
