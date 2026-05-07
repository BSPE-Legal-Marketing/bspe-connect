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
];

$library_help = static function ( string $library, string $type ): string {
	switch ( $library ) {
		case 'fa-solid':
		case 'fa-regular':
			return sprintf(
				/* translators: 1: library URL, 2: example token */
				__( 'Browse names at <a href="%1$s" target="_blank" rel="noopener">fontawesome.com/icons</a> — type the slug (e.g. <code>%2$s</code>), without the <code>fa-</code> prefix.', 'bspe-connect' ),
				'https://fontawesome.com/icons?ic=free',
				[ 'connect' => 'comments', 'call' => 'phone', 'text' => 'message', 'email' => 'envelope' ][ $type ] ?? 'phone'
			);
		case 'ion-filled':
		case 'ion-outline':
			return sprintf(
				/* translators: 1: library URL, 2: example token */
				__( 'Browse names at <a href="%1$s" target="_blank" rel="noopener">ionic.io/ionicons</a> — type the base name (e.g. <code>%2$s</code>), without the <code>-outline</code> suffix.', 'bspe-connect' ),
				'https://ionic.io/ionicons',
				[ 'connect' => 'chatbubbles', 'call' => 'call', 'text' => 'chatbox', 'email' => 'mail' ][ $type ] ?? 'call'
			);
		case 'dripicons':
			return sprintf(
				/* translators: 1: library URL, 2: example token */
				__( 'Browse names at <a href="%1$s" target="_blank" rel="noopener">demo.amitjakhu.com/dripicons</a> — paste the full class (e.g. <code>%2$s</code>).', 'bspe-connect' ),
				'http://demo.amitjakhu.com/dripicons/',
				[ 'connect' => 'dripicons-message', 'call' => 'dripicons-phone', 'text' => 'dripicons-message-reply', 'email' => 'dripicons-mail' ][ $type ] ?? 'dripicons-phone'
			);
		default:
			return __( 'Pick one of the four bundled brand icons below.', 'bspe-connect' );
	}
};

Components::open_form( 'buttons', $action_url );

/* ----------------- Connect ----------------- */
Components::open_card(
	__( 'Connect', 'bspe-connect' ),
	__( 'The first button. Optionally toggles the welcome bubble.', 'bspe-connect' )
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
	__( 'Mode', 'bspe-connect' ),
	static function () use ( $connect ): void {
		Components::radio_pills( 'bspe[buttons][connect][mode]', (string) ( $connect['mode'] ?? 'text' ), [
			'text'  => __( 'Text label', 'bspe-connect' ),
			'image' => __( 'Custom image', 'bspe-connect' ),
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
Components::row(
	__( 'Custom image', 'bspe-connect' ),
	static function () use ( $connect ): void {
		Components::media( 'bspe[buttons][connect][image_id]', (int) ( $connect['image_id'] ?? 0 ), [
			'button_text' => __( 'Choose image', 'bspe-connect' ),
			'modal_title' => __( 'Select Connect image', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Used when Mode is set to Custom image. A square image works best.', 'bspe-connect' ) ]
);
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $connect, $library_options ): void {
		Components::select(
			'bspe[buttons][connect][icon_library]',
			(string) ( $connect['icon_library'] ?? 'brand' ),
			$library_options
		);
	},
	[ 'description' => __( 'Switch to Font Awesome, Ionicons, or Dripicons for filled / outline variants. Save changes to update the icon picker below.', 'bspe-connect' ) ]
);
$connect_lib = (string) ( $connect['icon_library'] ?? 'brand' );
if ( 'brand' === $connect_lib ) {
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $connect ): void {
			Components::icon_radio( 'bspe[buttons][connect][icon]', (string) ( $connect['icon'] ?? 'connect-1' ), 'connect' );
		},
		[ 'description' => __( 'Used when Mode is Text label.', 'bspe-connect' ) ]
	);
} else {
	Components::row(
		__( 'Icon name', 'bspe-connect' ),
		static function () use ( $connect ): void {
			Components::text( 'bspe[buttons][connect][icon]', (string) ( $connect['icon'] ?? '' ), [
				'placeholder' => 'comments',
				'maxlength'   => 60,
			] );
		},
		[
			'id'          => 'bspe-buttons-connect-icon-name',
			'description' => $library_help( $connect_lib, 'connect' ),
		]
	);
}
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
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $call, $library_options ): void {
		Components::select(
			'bspe[buttons][call][icon_library]',
			(string) ( $call['icon_library'] ?? 'brand' ),
			$library_options
		);
	}
);
$call_lib = (string) ( $call['icon_library'] ?? 'brand' );
if ( 'brand' === $call_lib ) {
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $call ): void {
			Components::icon_radio( 'bspe[buttons][call][icon]', (string) ( $call['icon'] ?? 'call-1' ), 'call' );
		}
	);
} else {
	Components::row(
		__( 'Icon name', 'bspe-connect' ),
		static function () use ( $call ): void {
			Components::text( 'bspe[buttons][call][icon]', (string) ( $call['icon'] ?? '' ), [
				'placeholder' => 'phone',
				'maxlength'   => 60,
			] );
		},
		[
			'id'          => 'bspe-buttons-call-icon-name',
			'description' => $library_help( $call_lib, 'call' ),
		]
	);
}
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
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $text, $library_options ): void {
		Components::select(
			'bspe[buttons][text][icon_library]',
			(string) ( $text['icon_library'] ?? 'brand' ),
			$library_options
		);
	}
);
$text_lib = (string) ( $text['icon_library'] ?? 'brand' );
if ( 'brand' === $text_lib ) {
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $text ): void {
			Components::icon_radio( 'bspe[buttons][text][icon]', (string) ( $text['icon'] ?? 'text-1' ), 'text' );
		}
	);
} else {
	Components::row(
		__( 'Icon name', 'bspe-connect' ),
		static function () use ( $text ): void {
			Components::text( 'bspe[buttons][text][icon]', (string) ( $text['icon'] ?? '' ), [
				'placeholder' => 'message',
				'maxlength'   => 60,
			] );
		},
		[
			'id'          => 'bspe-buttons-text-icon-name',
			'description' => $library_help( $text_lib, 'text' ),
		]
	);
}
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
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $email, $library_options ): void {
		Components::select(
			'bspe[buttons][email][icon_library]',
			(string) ( $email['icon_library'] ?? 'brand' ),
			$library_options
		);
	}
);
$email_lib = (string) ( $email['icon_library'] ?? 'brand' );
if ( 'brand' === $email_lib ) {
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $email ): void {
			Components::icon_radio( 'bspe[buttons][email][icon]', (string) ( $email['icon'] ?? 'email-1' ), 'email' );
		}
	);
} else {
	Components::row(
		__( 'Icon name', 'bspe-connect' ),
		static function () use ( $email ): void {
			Components::text( 'bspe[buttons][email][icon]', (string) ( $email['icon'] ?? '' ), [
				'placeholder' => 'envelope',
				'maxlength'   => 60,
			] );
		},
		[
			'id'          => 'bspe-buttons-email-icon-name',
			'description' => $library_help( $email_lib, 'email' ),
		]
	);
}
Components::close_card();

Components::close_form();
