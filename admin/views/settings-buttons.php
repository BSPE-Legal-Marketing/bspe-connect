<?php
/**
 * Buttons settings tab — per-button cards (Connect, Call, Text, Email).
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$buttons    = is_array( Settings::get( 'buttons', [] ) ) ? Settings::get( 'buttons', [] ) : [];
$action_url = admin_url( 'admin-post.php' );

$connect = is_array( $buttons['connect'] ?? null ) ? $buttons['connect'] : [];
$call    = is_array( $buttons['call']    ?? null ) ? $buttons['call']    : [];
$text    = is_array( $buttons['text']    ?? null ) ? $buttons['text']    : [];
$email   = is_array( $buttons['email']   ?? null ) ? $buttons['email']   : [];

$format_phone = static function ( string $digits ): string {
	$d = preg_replace( '/\D/', '', $digits ) ?? '';
	if ( strlen( $d ) === 10 ) {
		return sprintf( '(%s) %s-%s', substr( $d, 0, 3 ), substr( $d, 3, 3 ), substr( $d, 6, 4 ) );
	}
	return $d;
};

$library_options = [
	'none'       => __( 'No icon (label only)', 'bspe-connect' ),
	'fa-solid'   => __( 'Font Awesome — Solid (filled)', 'bspe-connect' ),
	'fa-regular' => __( 'Font Awesome — Regular (outline)', 'bspe-connect' ),
];

/**
 * Render the icon-library select + visual fa-solid / fa-regular pickers
 * for one button, all wired up for the live-swap JS.
 *
 * @param string $key       Button key (connect / call / text / email).
 * @param array  $cfg       Saved button settings.
 * @param array  $options   Library options map.
 */
$render_icon_picker = static function ( string $key, array $cfg, array $options ): void {
	$current_lib = (string) ( $cfg['icon_library'] ?? 'fa-solid' );
	if ( ! in_array( $current_lib, [ 'none', 'fa-solid', 'fa-regular' ], true ) ) {
		$current_lib = 'fa-solid';
	}

	Components::row(
		__( 'Icon library', 'bspe-connect' ),
		static function () use ( $key, $current_lib, $options ): void {
			Components::select(
				'bspe[buttons][' . $key . '][icon_library]',
				$current_lib,
				$options,
				[ 'data' => [ 'bspe-icon-library-select' => $key ] ]
			);
		},
		[ 'description' => __( 'Pick "No icon" for a label-only button, or one of the Font Awesome variants for a filled / outline glyph.', 'bspe-connect' ) ]
	);

	foreach ( [ 'fa-solid', 'fa-regular' ] as $lib ) :
		Components::row(
			__( 'Icon', 'bspe-connect' ),
			static function () use ( $cfg, $key, $lib ): void {
				Components::library_icon_picker( 'bspe[buttons][' . $key . '][icon]', (string) ( $cfg['icon'] ?? '' ), $key, $lib );
			},
			[ 'data' => [ 'bspe-icon-pane' => $lib, 'bspe-button' => $key ] ]
		);
	endforeach;
};

Components::open_form( 'buttons', $action_url );

/* ----------------- Connect ----------------- */
Components::open_card(
	__( 'Connect', 'bspe-connect' ),
	__( 'The first button. Toggles the welcome bubble. Defaults to label-only — pick a library below to add an icon.', 'bspe-connect' )
);
Components::row(
	__( 'Enabled', 'bspe-connect' ),
	static function () use ( $connect ): void {
		Components::toggle( 'bspe[buttons][connect][enabled]', ! empty( $connect['enabled'] ), [
			'label' => __( 'Show this button on the bar', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Label', 'bspe-connect' ),
	static function () use ( $connect ): void {
		Components::text( 'bspe[buttons][connect][label]', (string) ( $connect['label'] ?? 'Connect' ), [
			'maxlength' => 24,
		] );
	},
	[ 'id' => 'bspe-buttons-connect-label' ]
);
$render_icon_picker( 'connect', $connect, $library_options );
Components::close_card();

/* ----------------- Call ----------------- */
Components::open_card(
	__( 'Call', 'bspe-connect' ),
	__( 'Opens the phone dialer with the configured number.', 'bspe-connect' )
);
Components::row(
	__( 'Enabled', 'bspe-connect' ),
	static function () use ( $call ): void {
		Components::toggle( 'bspe[buttons][call][enabled]', ! empty( $call['enabled'] ), [
			'label' => __( 'Show this button on the bar', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Phone', 'bspe-connect' ),
	static function () use ( $call, $format_phone ): void {
		Components::text( 'bspe[buttons][call][phone]', $format_phone( (string) ( $call['phone'] ?? '' ) ), [
			'placeholder' => '(555) 123-4567',
			'inputmode'   => 'tel',
			'maxlength'   => 16,
			'data'        => [ 'bspe-phone-mask' => '1' ],
		] );
	},
	[
		'id'          => 'bspe-buttons-call-phone',
		'description' => __( 'US numbers only. Stored as 10 digits and rendered as a tel: link.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Label', 'bspe-connect' ),
	static function () use ( $call ): void {
		Components::text( 'bspe[buttons][call][label]', (string) ( $call['label'] ?? 'Call' ), [
			'maxlength' => 24,
		] );
	},
	[ 'id' => 'bspe-buttons-call-label' ]
);
$render_icon_picker( 'call', $call, $library_options );
Components::close_card();

/* ----------------- Text ----------------- */
Components::open_card(
	__( 'Text', 'bspe-connect' ),
	__( 'Opens the SMS app with the firm number, OR opens the inline lead form.', 'bspe-connect' )
);
Components::row(
	__( 'Enabled', 'bspe-connect' ),
	static function () use ( $text ): void {
		Components::toggle( 'bspe[buttons][text][enabled]', ! empty( $text['enabled'] ), [
			'label' => __( 'Show this button on the bar', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Mode', 'bspe-connect' ),
	static function () use ( $text ): void {
		Components::radio_pills( 'bspe[buttons][text][mode]', (string) ( $text['mode'] ?? 'sms' ), [
			'sms'    => __( 'SMS link', 'bspe-connect' ),
			'inline' => __( 'Inline form', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Phone', 'bspe-connect' ),
	static function () use ( $text, $format_phone ): void {
		Components::text( 'bspe[buttons][text][phone]', $format_phone( (string) ( $text['phone'] ?? '' ) ), [
			'placeholder' => '(555) 123-4567',
			'inputmode'   => 'tel',
			'maxlength'   => 16,
			'data'        => [ 'bspe-phone-mask' => '1' ],
		] );
	},
	[
		'id'          => 'bspe-buttons-text-phone',
		'description' => __( 'Used when Mode is SMS link. Ignored for the Inline form.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Label', 'bspe-connect' ),
	static function () use ( $text ): void {
		Components::text( 'bspe[buttons][text][label]', (string) ( $text['label'] ?? 'Text' ), [
			'maxlength' => 24,
		] );
	},
	[ 'id' => 'bspe-buttons-text-label' ]
);
$render_icon_picker( 'text', $text, $library_options );
Components::close_card();

/* ----------------- Email ----------------- */
Components::open_card(
	__( 'Email', 'bspe-connect' ),
	__( 'Always opens the inline lead form modal.', 'bspe-connect' )
);
Components::row(
	__( 'Enabled', 'bspe-connect' ),
	static function () use ( $email ): void {
		Components::toggle( 'bspe[buttons][email][enabled]', ! empty( $email['enabled'] ), [
			'label' => __( 'Show this button on the bar', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Label', 'bspe-connect' ),
	static function () use ( $email ): void {
		Components::text( 'bspe[buttons][email][label]', (string) ( $email['label'] ?? 'Email' ), [
			'maxlength' => 24,
		] );
	},
	[ 'id' => 'bspe-buttons-email-label' ]
);
$render_icon_picker( 'email', $email, $library_options );
Components::close_card();

Components::close_form();
