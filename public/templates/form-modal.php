<?php
/**
 * BSPE Connect — bottom-sheet form modal.
 *
 * Renders a single form whose source (`text` / `email`) is set by JS based
 * on which button opened the modal. Heading + subheading text are also
 * swapped client-side from the data-* attributes on the modal root so a
 * single DOM tree serves both flows.
 *
 * Field labels are visually-hidden for screen readers; visible label text
 * comes from the placeholder.
 *
 * @package BSPE\Connect
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;

$form_settings = Settings::get( 'form', [] );
$form_settings = is_array( $form_settings ) ? $form_settings : [];

$fields_cfg       = is_array( $form_settings['fields'] ?? null ) ? $form_settings['fields'] : [];
$text_heading     = (string) ( $form_settings['text_heading']     ?? __( 'Send us a text', 'bspe-connect' ) );
$email_heading    = (string) ( $form_settings['email_heading']    ?? __( 'Send us an email', 'bspe-connect' ) );
$text_subheading  = (string) ( $form_settings['text_subheading']  ?? __( 'Please enter your name and contact info.', 'bspe-connect' ) );
$email_subheading = (string) ( $form_settings['email_subheading'] ?? __( 'Please enter your name and contact info.', 'bspe-connect' ) );
$submit_label     = (string) ( $form_settings['submit_label']     ?? __( 'Send', 'bspe-connect' ) );

$turnstile_enabled  = (bool) ( $form_settings['antispam']['turnstile_enabled']  ?? false );
$turnstile_site_key = (string) ( $form_settings['antispam']['turnstile_site_key'] ?? '' );

$is_visible = static function ( string $name ) use ( $fields_cfg ): bool {
	if ( ! isset( $fields_cfg[ $name ] ) ) {
		return in_array( $name, [ 'name', 'phone', 'email', 'message' ], true );
	}
	return ! empty( $fields_cfg[ $name ]['visible'] );
};
$is_required = static function ( string $name ) use ( $fields_cfg ): bool {
	if ( ! isset( $fields_cfg[ $name ] ) ) {
		return in_array( $name, [ 'name', 'phone', 'email', 'message' ], true );
	}
	return ! empty( $fields_cfg[ $name ]['required'] );
};

/**
 * Builds an "Email *" placeholder string with a hairspace + asterisk on
 * required fields (matches the visual cue we used to put on the label).
 */
$placeholder_for = static function ( string $base, bool $required ): string {
	return $required ? $base . ' *' : $base;
};

$render_label = static function ( string $for_id, string $label ): void {
	// Visually hidden — kept for screen readers (.bspe-connect__label
	// has clip:rect(0 0 0 0)).
	printf(
		'<label for="%1$s" class="bspe-connect__label">%2$s</label>',
		esc_attr( $for_id ),
		esc_html( $label )
	);
};

$render_error = static function ( string $field ): void {
	printf(
		'<p class="bspe-connect__field-error" data-bspe-error="%s" role="alert" hidden></p>',
		esc_attr( $field )
	);
};
?>
<div class="bspe-connect__modal" data-bspe-modal data-bspe-state="hidden" hidden
	data-text-heading="<?php echo esc_attr( $text_heading ); ?>"
	data-email-heading="<?php echo esc_attr( $email_heading ); ?>"
	data-text-subheading="<?php echo esc_attr( $text_subheading ); ?>"
	data-email-subheading="<?php echo esc_attr( $email_subheading ); ?>">

	<div class="bspe-connect__modal-backdrop" data-bspe-modal-backdrop aria-hidden="true"></div>

	<div
		class="bspe-connect__modal-sheet"
		role="dialog"
		aria-modal="true"
		aria-labelledby="bspe-connect-modal-heading"
	>
		<div class="bspe-connect__modal-handle" aria-hidden="true"></div>

		<header class="bspe-connect__modal-header">
			<div class="bspe-connect__modal-header-text">
				<h2 id="bspe-connect-modal-heading" class="bspe-connect__modal-heading" data-bspe-modal-heading>
					<?php echo esc_html( $email_heading ); ?>
				</h2>
				<p class="bspe-connect__modal-subheading" data-bspe-modal-subheading>
					<?php echo esc_html( $email_subheading ); ?>
				</p>
			</div>
			<button
				type="button"
				class="bspe-connect__modal-close"
				data-bspe-modal-close
				aria-label="<?php esc_attr_e( 'Close form', 'bspe-connect' ); ?>"
			>
				<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
					<path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/>
				</svg>
			</button>
		</header>

		<div class="bspe-connect__modal-body">

			<form
				class="bspe-connect__form"
				data-bspe-form
				action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				method="post"
				novalidate
			>
				<input type="hidden" name="action" value="<?php echo esc_attr( \BSPE\Connect\Form_Handler::ACTION ); ?>" />
				<input type="hidden" name="bspe_connect_nonce" value="<?php echo esc_attr( wp_create_nonce( \BSPE\Connect\Form_Handler::NONCE_ACTION ) ); ?>" />
				<input type="hidden" name="bspe_connect_form_ts" value="<?php echo esc_attr( (string) time() ); ?>" />
				<input type="hidden" name="bspe_source" value="email" data-bspe-source />
				<input type="hidden" name="bspe_page_url" value="" data-bspe-page-url />

				<!-- Honeypot — visually hidden via CSS, intentionally not type=hidden so
				     bots fill it and we silently drop the submission. -->
				<div class="bspe-connect__honeypot" aria-hidden="true">
					<label>
						<?php esc_html_e( 'Website (leave empty)', 'bspe-connect' ); ?>
						<input type="text" name="bspe_website" value="" tabindex="-1" autocomplete="off" />
					</label>
				</div>

				<?php if ( $is_visible( 'name' ) ) : ?>
					<div class="bspe-connect__field" data-field="name">
						<?php $render_label( 'bspe-connect-name', __( 'Name', 'bspe-connect' ) ); ?>
						<input
							id="bspe-connect-name"
							class="bspe-connect__input"
							type="text"
							name="name"
							autocomplete="name"
							inputmode="text"
							maxlength="255"
							placeholder="<?php echo esc_attr( $placeholder_for( __( 'Name', 'bspe-connect' ), $is_required( 'name' ) ) ); ?>"
							<?php echo $is_required( 'name' ) ? 'required aria-required="true"' : ''; ?>
						/>
						<?php $render_error( 'name' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $is_visible( 'phone' ) ) : ?>
					<div class="bspe-connect__field" data-field="phone">
						<?php $render_label( 'bspe-connect-phone', __( 'Phone', 'bspe-connect' ) ); ?>
						<input
							id="bspe-connect-phone"
							class="bspe-connect__input"
							type="tel"
							name="phone"
							autocomplete="tel"
							inputmode="tel"
							placeholder="<?php echo esc_attr( $placeholder_for( __( 'Phone', 'bspe-connect' ), $is_required( 'phone' ) ) ); ?>"
							maxlength="20"
							data-bspe-phone-mask
							<?php echo $is_required( 'phone' ) ? 'required aria-required="true"' : ''; ?>
						/>
						<?php $render_error( 'phone' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $is_visible( 'email' ) ) : ?>
					<div class="bspe-connect__field" data-field="email">
						<?php $render_label( 'bspe-connect-email', __( 'Email', 'bspe-connect' ) ); ?>
						<input
							id="bspe-connect-email"
							class="bspe-connect__input"
							type="email"
							name="email"
							autocomplete="email"
							inputmode="email"
							maxlength="255"
							placeholder="<?php echo esc_attr( $placeholder_for( __( 'Email', 'bspe-connect' ), $is_required( 'email' ) ) ); ?>"
							<?php echo $is_required( 'email' ) ? 'required aria-required="true"' : ''; ?>
						/>
						<?php $render_error( 'email' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $is_visible( 'contact_pref' ) ) : ?>
					<div class="bspe-connect__field" data-field="contact_pref">
						<?php $render_label( 'bspe-connect-contact-pref', __( 'Preferred contact', 'bspe-connect' ) ); ?>
						<select
							id="bspe-connect-contact-pref"
							class="bspe-connect__input bspe-connect__select"
							name="contact_pref"
							<?php echo $is_required( 'contact_pref' ) ? 'required aria-required="true"' : ''; ?>
						>
							<option value=""><?php esc_html_e( 'Preferred contact method', 'bspe-connect' ); ?></option>
							<option value="any"><?php esc_html_e( 'Any', 'bspe-connect' ); ?></option>
							<option value="phone"><?php esc_html_e( 'Phone', 'bspe-connect' ); ?></option>
							<option value="text"><?php esc_html_e( 'Text', 'bspe-connect' ); ?></option>
							<option value="email"><?php esc_html_e( 'Email', 'bspe-connect' ); ?></option>
						</select>
						<?php $render_error( 'contact_pref' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $is_visible( 'message' ) ) : ?>
					<div class="bspe-connect__field" data-field="message">
						<?php $render_label( 'bspe-connect-message', __( 'Message', 'bspe-connect' ) ); ?>
						<textarea
							id="bspe-connect-message"
							class="bspe-connect__input bspe-connect__textarea"
							name="message"
							rows="4"
							placeholder="<?php echo esc_attr( $placeholder_for( __( 'Message', 'bspe-connect' ), $is_required( 'message' ) ) ); ?>"
							<?php echo $is_required( 'message' ) ? 'required aria-required="true"' : ''; ?>
						></textarea>
						<?php $render_error( 'message' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $turnstile_enabled && '' !== $turnstile_site_key ) : ?>
					<div class="bspe-connect__turnstile">
						<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>" data-theme="light"></div>
					</div>
				<?php endif; ?>

				<button type="submit" class="bspe-connect__submit" data-bspe-submit>
					<span class="bspe-connect__submit-label" data-bspe-submit-label>
						<?php echo esc_html( $submit_label ); ?>
					</span>
					<span class="bspe-connect__submit-spinner" data-bspe-submit-spinner aria-hidden="true"></span>
				</button>

				<p class="bspe-connect__form-error" data-bspe-form-error role="alert" hidden></p>
			</form>

			<div class="bspe-connect__success" data-bspe-modal-success hidden>
				<?php require BSPE_CONNECT_DIR . 'public/templates/success-state.php'; ?>
			</div>

		</div>
	</div>
</div>
