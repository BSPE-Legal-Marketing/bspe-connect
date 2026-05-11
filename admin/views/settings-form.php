<?php
/**
 * Form settings tab — fields, copy, mail delivery, anti-spam.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$form       = is_array( Settings::get( 'form', [] ) ) ? Settings::get( 'form', [] ) : [];
$fields     = is_array( $form['fields'] ?? null ) ? $form['fields'] : [];
$antispam   = is_array( $form['antispam'] ?? null ) ? $form['antispam'] : [];
$action_url = admin_url( 'admin-post.php' );

$field_definitions = [
	'name'         => __( 'Name', 'bspe-connect' ),
	'phone'        => __( 'Phone (masked)', 'bspe-connect' ),
	'email'        => __( 'Email', 'bspe-connect' ),
	'contact_pref' => __( 'Preferred contact', 'bspe-connect' ),
	'message'      => __( 'Message', 'bspe-connect' ),
];

Components::open_form( 'form', $action_url );

/* ----------------- Field configuration ----------------- */
Components::open_card(
	__( 'Form fields', 'bspe-connect' ),
	__( 'Pick which fields show in the lead form and which are required. Field order is fixed: Name → Phone → Email → Preferred contact → Message.', 'bspe-connect' )
);
?>
<div class="bspe-fields-table" role="table">
	<div class="bspe-fields-table__head" role="row">
		<span role="columnheader"><?php esc_html_e( 'Field', 'bspe-connect' ); ?></span>
		<span role="columnheader"><?php esc_html_e( 'Show', 'bspe-connect' ); ?></span>
		<span role="columnheader"><?php esc_html_e( 'Required', 'bspe-connect' ); ?></span>
	</div>
	<?php foreach ( $field_definitions as $key => $label ) :
		$cfg      = is_array( $fields[ $key ] ?? null ) ? $fields[ $key ] : [];
		$visible  = ! empty( $cfg['visible'] );
		$required = ! empty( $cfg['required'] );
		$visible_id = 'bspe-form-fields-' . $key . '-visible';
		?>
		<div class="bspe-fields-table__row" role="row">
			<span class="bspe-fields-table__name" role="cell"><?php echo esc_html( $label ); ?></span>
			<span class="bspe-fields-table__cell" role="cell">
				<?php
				Components::toggle(
					'bspe[form][fields][' . $key . '][visible]',
					$visible,
					[
						'id'   => $visible_id,
						'data' => [ 'bspe-controls-required' => 'bspe-form-fields-' . $key . '-required' ],
					]
				);
				?>
			</span>
			<span class="bspe-fields-table__cell" role="cell">
				<?php
				Components::checkbox(
					'bspe[form][fields][' . $key . '][required]',
					$required,
					[
						'id'       => 'bspe-form-fields-' . $key . '-required',
						'disabled' => ! $visible,
					]
				);
				?>
			</span>
		</div>
	<?php endforeach; ?>
</div>
<?php
Components::close_card();

/* ----------------- Copy ----------------- */
Components::open_card(
	__( 'Headings & copy', 'bspe-connect' ),
	__( 'Wording shown inside the form modal.', 'bspe-connect' )
);
Components::row(
	__( 'Text form heading', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][text_heading]', (string) ( $form['text_heading'] ?? 'Send us a text' ), [ 'maxlength' => 80 ] );
	},
	[ 'id' => 'bspe-form-text_heading' ]
);
Components::row(
	__( 'Text form subheading', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text(
			'bspe[form][text_subheading]',
			(string) ( $form['text_subheading'] ?? 'Please enter your name and contact info.' ),
			[ 'maxlength' => 200 ]
		);
	},
	[
		'id'          => 'bspe-form-text_subheading',
		'description' => __( 'Smaller line shown below the heading. Tell visitors what to expect after they submit.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Email form heading', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][email_heading]', (string) ( $form['email_heading'] ?? 'Send us an email' ), [ 'maxlength' => 80 ] );
	},
	[ 'id' => 'bspe-form-email_heading' ]
);
Components::row(
	__( 'Email form subheading', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text(
			'bspe[form][email_subheading]',
			(string) ( $form['email_subheading'] ?? 'Please enter your name and contact info.' ),
			[ 'maxlength' => 200 ]
		);
	},
	[ 'id' => 'bspe-form-email_subheading' ]
);
Components::row(
	__( 'Submit button label', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][submit_label]', (string) ( $form['submit_label'] ?? 'Send' ), [ 'maxlength' => 30 ] );
	},
	[ 'id' => 'bspe-form-submit_label' ]
);
Components::row(
	__( 'Success message', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::textarea( 'bspe[form][success_msg]', (string) ( $form['success_msg'] ?? "Thanks. We'll be in touch shortly." ), [
			'rows'      => 2,
			'maxlength' => 240,
		] );
	},
	[ 'id' => 'bspe-form-success_msg' ]
);
Components::close_card();

/* ----------------- Email delivery ----------------- */
Components::open_card(
	__( 'Email delivery', 'bspe-connect' ),
	__( 'Where lead emails are sent and how they\'re addressed. Available variables: {site_name}, {firm_name}, {source}, {page_url}, {name}, {phone}, {email}, {message}, {contact_pref}.', 'bspe-connect' )
);
Components::row(
	__( 'Send to', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][mail_to]', (string) ( $form['mail_to'] ?? '' ), [
			'placeholder' => 'leads@yourfirm.com, intake@yourfirm.com',
			'maxlength'   => 500,
			'inputmode'   => 'email',
		] );
	},
	[
		'id'          => 'bspe-form-mail_to',
		'description' => __( 'Comma-separated for multiple recipients.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Subject', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][mail_subject]', (string) ( $form['mail_subject'] ?? 'New lead from {site_name}: {source}' ), [
			'maxlength' => 200,
		] );
	},
	[ 'id' => 'bspe-form-mail_subject' ]
);
Components::row(
	__( 'From email', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][mail_from]', (string) ( $form['mail_from'] ?? '' ), [
			'placeholder' => 'no-reply@yourfirm.com',
			'inputmode'   => 'email',
			'maxlength'   => 254,
			'type'        => 'email',
		] );
	},
	[
		'id'          => 'bspe-form-mail_from',
		'description' => __( 'Should be on the same domain as your site to avoid SPF/DKIM rejections.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'From name', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::text( 'bspe[form][mail_from_name]', (string) ( $form['mail_from_name'] ?? '{site_name}' ), [
			'maxlength' => 100,
		] );
	},
	[ 'id' => 'bspe-form-mail_from_name' ]
);
Components::close_card();

/* ----------------- Anti-spam ----------------- */
Components::open_card(
	__( 'Anti-spam', 'bspe-connect' ),
	__( 'Stack of progressively stronger spam filters. Honeypot + time-trap + rate-limit handle 95% of bots; Turnstile catches the rest.', 'bspe-connect' )
);
Components::row(
	__( 'Honeypot field', 'bspe-connect' ),
	static function () use ( $antispam ): void {
		Components::toggle( 'bspe[form][antispam][honeypot]', ! empty( $antispam['honeypot'] ), [
			'label' => __( 'Add a hidden trap field bots fill in', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Recommended. When a bot fills it the submission is silently dropped.', 'bspe-connect' ) ]
);
Components::row(
	__( 'Minimum form-fill time', 'bspe-connect' ),
	static function () use ( $antispam ): void {
		Components::number( 'bspe[form][antispam][min_seconds]', (int) ( $antispam['min_seconds'] ?? 2 ), [
			'min'    => 0,
			'max'    => 60,
			'suffix' => __( 'seconds', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-form-antispam-min_seconds',
		'description' => __( 'Submissions faster than this are silently dropped.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Rate limit per IP', 'bspe-connect' ),
	static function () use ( $antispam ): void {
		Components::number( 'bspe[form][antispam][rate_limit]', (int) ( $antispam['rate_limit'] ?? 5 ), [
			'min'    => 0,
			'max'    => 1000,
			'suffix' => __( 'per hour', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-form-antispam-rate_limit',
		'description' => __( 'Set to 0 to disable. IP is hashed, never stored raw.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Cloudflare Turnstile', 'bspe-connect' ),
	static function () use ( $antispam ): void {
		Components::toggle( 'bspe[form][antispam][turnstile_enabled]', ! empty( $antispam['turnstile_enabled'] ), [
			'label' => __( 'Add a captcha widget to the form', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Free service from Cloudflare. Sign up at <a href="https://www.cloudflare.com/products/turnstile/" target="_blank" rel="noopener">cloudflare.com/turnstile</a> for the keys.', 'bspe-connect' ) ]
);
Components::row(
	__( 'Turnstile site key', 'bspe-connect' ),
	static function () use ( $antispam ): void {
		Components::text( 'bspe[form][antispam][turnstile_site_key]', (string) ( $antispam['turnstile_site_key'] ?? '' ), [
			'placeholder' => '0x4AAAAAAA...',
			'maxlength'   => 200,
			'autocomplete'=> 'off',
		] );
	},
	[ 'id' => 'bspe-form-antispam-turnstile_site_key' ]
);
Components::row(
	__( 'Turnstile secret key', 'bspe-connect' ),
	static function () use ( $antispam ): void {
		Components::text( 'bspe[form][antispam][turnstile_secret_key]', (string) ( $antispam['turnstile_secret_key'] ?? '' ), [
			'placeholder' => '0x4AAAAAAA...',
			'maxlength'   => 200,
			'autocomplete'=> 'off',
			'type'        => 'password',
		] );
	},
	[
		'id'          => 'bspe-form-antispam-turnstile_secret_key',
		'description' => __( 'Stored in WP options. For higher security, define <code>BSPE_CONNECT_TURNSTILE_SECRET</code> in <code>wp-config.php</code> — when set, that constant takes precedence over this field.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Submissions retention ----------------- */
Components::open_card(
	__( 'Submissions retention', 'bspe-connect' ),
	__( 'Automatically delete old form submissions to keep the database tidy. Set to 0 to keep every submission forever (default).', 'bspe-connect' )
);
Components::row(
	__( 'Keep submissions for', 'bspe-connect' ),
	static function () use ( $form ): void {
		Components::number( 'bspe[form][retention_days]', (int) ( $form['retention_days'] ?? 0 ), [
			'min'    => 0,
			'max'    => 3650,
			'step'   => 1,
			'suffix' => __( 'days', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-form-retention_days',
		'description' => __( 'A background task runs once a day and deletes submission rows older than this. Already-sent emails are unaffected (they live in the recipient&rsquo;s inbox). The separate analytics-events table has its own retention. Common values: <code>0</code> = keep forever; <code>365</code> = one year; <code>730</code> = two years.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
