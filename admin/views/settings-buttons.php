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
	'brand'       => __( 'Brand SVGs (bundled)', 'bspe-connect' ),
	'fa-solid'    => __( 'Font Awesome — Solid (filled)', 'bspe-connect' ),
	'fa-regular'  => __( 'Font Awesome — Regular (outline)', 'bspe-connect' ),
	'ion-filled'  => __( 'Ionicons — Filled', 'bspe-connect' ),
	'ion-outline' => __( 'Ionicons — Outline', 'bspe-connect' ),
	'dripicons'   => __( 'Dripicons (outline only)', 'bspe-connect' ),
	'none'        => __( 'No icon (label only)', 'bspe-connect' ),
];

$library_help = static function ( string $type ): string {
	$examples = [
		'connect' => [ 'fa' => 'comments',     'ion' => 'chatbubbles', 'drip' => 'dripicons-message' ],
		'call'    => [ 'fa' => 'phone',        'ion' => 'call',        'drip' => 'dripicons-phone' ],
		'text'    => [ 'fa' => 'message',      'ion' => 'chatbox',     'drip' => 'dripicons-message-reply' ],
		'email'   => [ 'fa' => 'envelope',     'ion' => 'mail',        'drip' => 'dripicons-mail' ],
	];
	$ex = $examples[ $type ] ?? $examples['call'];

	return sprintf(
		/* translators: 1-3: example slugs per library */
		__( 'Type the icon slug from your selected library:<br>• <strong>Font Awesome</strong> (<a href="https://fontawesome.com/icons?ic=free" target="_blank" rel="noopener">browse</a>) — e.g. <code>%1$s</code> (no <code>fa-</code> prefix)<br>• <strong>Ionicons</strong> (<a href="https://ionic.io/ionicons" target="_blank" rel="noopener">browse</a>) — e.g. <code>%2$s</code> (no <code>-outline</code> suffix)<br>• <strong>Dripicons</strong> (<a href="http://demo.amitjakhu.com/dripicons/" target="_blank" rel="noopener">browse</a>) — full class, e.g. <code>%3$s</code>', 'bspe-connect' ),
		esc_html( $ex['fa'] ),
		esc_html( $ex['ion'] ),
		esc_html( $ex['drip'] )
	);
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
$connect_lib = (string) ( $connect['icon_library'] ?? 'none' );
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $connect_lib, $library_options ): void {
		Components::select(
			'bspe[buttons][connect][icon_library]',
			$connect_lib,
			$library_options,
			[ 'data' => [ 'bspe-icon-library-select' => 'connect' ] ]
		);
	},
	[ 'description' => __( 'Switch live between bundled brand SVGs, Font Awesome, Ionicons, Dripicons, or no icon at all.', 'bspe-connect' ) ]
);
Components::row(
	__( 'Icon', 'bspe-connect' ),
	static function () use ( $connect ): void {
		Components::icon_radio( 'bspe[buttons][connect][icon]', (string) ( $connect['icon'] ?? 'connect-1' ), 'connect' );
	},
	[ 'data' => [ 'bspe-icon-pane' => 'brand', 'bspe-button' => 'connect' ] ]
);
foreach ( [ 'fa-solid', 'fa-regular', 'ion-filled', 'ion-outline', 'dripicons' ] as $lib ) :
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $connect, $lib ): void {
			Components::library_icon_picker( 'bspe[buttons][connect][icon]', (string) ( $connect['icon'] ?? '' ), 'connect', $lib );
		},
		[ 'data' => [ 'bspe-icon-pane' => $lib, 'bspe-button' => 'connect' ] ]
	);
endforeach;
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
$call_lib = (string) ( $call['icon_library'] ?? 'brand' );
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $call_lib, $library_options ): void {
		Components::select(
			'bspe[buttons][call][icon_library]',
			$call_lib,
			$library_options,
			[ 'data' => [ 'bspe-icon-library-select' => 'call' ] ]
		);
	}
);
Components::row(
	__( 'Icon', 'bspe-connect' ),
	static function () use ( $call ): void {
		Components::icon_radio( 'bspe[buttons][call][icon]', (string) ( $call['icon'] ?? 'call-1' ), 'call' );
	},
	[ 'data' => [ 'bspe-icon-pane' => 'brand', 'bspe-button' => 'call' ] ]
);
foreach ( [ 'fa-solid', 'fa-regular', 'ion-filled', 'ion-outline', 'dripicons' ] as $lib ) :
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $call, $lib ): void {
			Components::library_icon_picker( 'bspe[buttons][call][icon]', (string) ( $call['icon'] ?? '' ), 'call', $lib );
		},
		[ 'data' => [ 'bspe-icon-pane' => $lib, 'bspe-button' => 'call' ] ]
	);
endforeach;
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
$text_lib = (string) ( $text['icon_library'] ?? 'brand' );
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $text_lib, $library_options ): void {
		Components::select(
			'bspe[buttons][text][icon_library]',
			$text_lib,
			$library_options,
			[ 'data' => [ 'bspe-icon-library-select' => 'text' ] ]
		);
	}
);
Components::row(
	__( 'Icon', 'bspe-connect' ),
	static function () use ( $text ): void {
		Components::icon_radio( 'bspe[buttons][text][icon]', (string) ( $text['icon'] ?? 'text-1' ), 'text' );
	},
	[ 'data' => [ 'bspe-icon-pane' => 'brand', 'bspe-button' => 'text' ] ]
);
foreach ( [ 'fa-solid', 'fa-regular', 'ion-filled', 'ion-outline', 'dripicons' ] as $lib ) :
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $text, $lib ): void {
			Components::library_icon_picker( 'bspe[buttons][text][icon]', (string) ( $text['icon'] ?? '' ), 'text', $lib );
		},
		[ 'data' => [ 'bspe-icon-pane' => $lib, 'bspe-button' => 'text' ] ]
	);
endforeach;
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
$email_lib = (string) ( $email['icon_library'] ?? 'brand' );
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $email_lib, $library_options ): void {
		Components::select(
			'bspe[buttons][email][icon_library]',
			$email_lib,
			$library_options,
			[ 'data' => [ 'bspe-icon-library-select' => 'email' ] ]
		);
	}
);
Components::row(
	__( 'Icon', 'bspe-connect' ),
	static function () use ( $email ): void {
		Components::icon_radio( 'bspe[buttons][email][icon]', (string) ( $email['icon'] ?? 'email-1' ), 'email' );
	},
	[ 'data' => [ 'bspe-icon-pane' => 'brand', 'bspe-button' => 'email' ] ]
);
foreach ( [ 'fa-solid', 'fa-regular', 'ion-filled', 'ion-outline', 'dripicons' ] as $lib ) :
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $email, $lib ): void {
			Components::library_icon_picker( 'bspe[buttons][email][icon]', (string) ( $email['icon'] ?? '' ), 'email', $lib );
		},
		[ 'data' => [ 'bspe-icon-pane' => $lib, 'bspe-button' => 'email' ] ]
	);
endforeach;
Components::close_card();

Components::close_form();
