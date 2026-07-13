<?php
/**
 * Chat tab — live-chat provider integration (Intaker / Custom) plus an
 * optional Chat button on the bar.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$chat       = is_array( Settings::get( 'chat', [] ) ) ? Settings::get( 'chat', [] ) : [];
$action_url = admin_url( 'admin-post.php' );
$provider   = (string) ( $chat['provider'] ?? 'intaker' );

$library_options = [
	'none'       => __( 'No icon (label only)', 'bspe-connect' ),
	'fa-solid'   => __( 'Font Awesome — Solid (filled)', 'bspe-connect' ),
	'fa-regular' => __( 'Font Awesome — Regular (outline)', 'bspe-connect' ),
];
$chat_icon_lib = (string) ( $chat['button_icon_library'] ?? 'fa-solid' );
if ( ! in_array( $chat_icon_lib, [ 'none', 'fa-solid', 'fa-regular' ], true ) ) {
	$chat_icon_lib = 'fa-solid';
}

Components::open_form( 'chat', $action_url );

/* ----------------- Master ----------------- */
Components::open_card(
	__( 'Live chat', 'bspe-connect' ),
	__( 'Load a live-chat provider on the site. The provider\'s own floating launcher stays visible, and (optionally) a Chat button is added to the bar that opens the same chat.', 'bspe-connect' )
);
Components::row(
	__( 'Enable chat', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::toggle( 'bspe[chat][enabled]', ! empty( $chat['enabled'] ), [
			'label' => __( 'Load the chat provider on the frontend', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'When off, no chat script is loaded and the Chat button never renders.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Provider', 'bspe-connect' ),
	static function () use ( $provider ): void {
		Components::select( 'bspe[chat][provider]', $provider, [
			'intaker' => __( 'Intaker', 'bspe-connect' ),
			'custom'  => __( 'Custom (paste embed script)', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Pick Intaker to enter just your account ID, or Custom to paste any provider\'s embed script.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Intaker ----------------- */
Components::open_card(
	__( 'Intaker', 'bspe-connect' ),
	__( 'Used when Provider is set to Intaker.', 'bspe-connect' )
);
Components::row(
	__( 'Account ID', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::text( 'bspe[chat][intaker_odl]', (string) ( $chat['intaker_odl'] ?? '' ), [
			'placeholder' => __( 'client id', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-chat-intaker_odl',
		'description' => __( 'The account identifier at the end of your Intaker embed snippet — the last value in the line that ends with <em>(window, document, \'script\', \'Intaker\', \'…\')</em>.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Custom ----------------- */
Components::open_card(
	__( 'Custom embed', 'bspe-connect' ),
	__( 'Used when Provider is set to Custom.', 'bspe-connect' )
);
Components::row(
	__( 'Embed script', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::textarea( 'bspe[chat][custom_script]', (string) ( $chat['custom_script'] ?? '' ), [
			'rows'        => 6,
			'placeholder' => "<script>...</script>",
		] );
	},
	[
		'id'          => 'bspe-chat-custom_script',
		'description' => __( 'Paste the full snippet your chat provider gave you, including the &lt;script&gt; tags. It is output verbatim in the site footer. Only administrators can edit this.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Bar button ----------------- */
Components::open_card(
	__( 'Chat button', 'bspe-connect' ),
	__( 'An optional button on the contact bar that opens the chat. Works alongside the provider\'s own floating launcher.', 'bspe-connect' )
);
Components::row(
	__( 'Show Chat button', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::toggle( 'bspe[chat][show_button]', ! empty( $chat['show_button'] ), [
			'label' => __( 'Add a Chat button to the bar', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Leave off if you only want the provider\'s native floating launcher.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Button label', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::text( 'bspe[chat][button_label]', (string) ( $chat['button_label'] ?? 'Chat' ), [
			'maxlength' => 40,
		] );
	},
	[ 'id' => 'bspe-chat-button_label' ]
);
Components::row(
	__( 'Icon library', 'bspe-connect' ),
	static function () use ( $chat_icon_lib, $library_options ): void {
		Components::select(
			'bspe[chat][button_icon_library]',
			$chat_icon_lib,
			$library_options,
			[ 'data' => [ 'bspe-icon-library-select' => 'chat' ] ]
		);
	},
	[ 'description' => __( 'Pick "No icon" for a label-only button, or a Font Awesome variant, then choose a glyph below — same picker as the other buttons.', 'bspe-connect' ) ]
);
foreach ( [ 'fa-solid', 'fa-regular' ] as $lib ) :
	Components::row(
		__( 'Icon', 'bspe-connect' ),
		static function () use ( $chat, $lib ): void {
			Components::library_icon_picker( 'bspe[chat][button_icon]', (string) ( $chat['button_icon'] ?? 'comment-dots' ), 'chat', $lib );
		},
		[ 'data' => [ 'bspe-icon-pane' => $lib, 'bspe-button' => 'chat' ] ]
	);
endforeach;
Components::close_card();

/* ----------------- Launcher position (Intaker) ----------------- */
Components::open_card(
	__( 'Intaker launcher position', 'bspe-connect' ),
	__( 'Intaker shows its own floating chat launcher on mobile. Set how far up from the bottom of the screen it sits, and how big it is. Applies to the Intaker provider only.', 'bspe-connect' )
);
Components::row(
	__( 'Distance from bottom', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::number( 'bspe[chat][launcher_bottom_px]', (int) ( $chat['launcher_bottom_px'] ?? 36 ), [
			'min'    => 0,
			'max'    => 400,
			'step'   => 2,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-chat-launcher_bottom_px',
		'description' => __( 'How far the launcher sits from the bottom of the screen. Default 36 keeps it low in the corner. Raise it (e.g. 80–100) to lift the launcher above the bar instead.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Launcher size', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::number( 'bspe[chat][launcher_scale]', (int) ( $chat['launcher_scale'] ?? 85 ), [
			'min'    => 30,
			'max'    => 100,
			'step'   => 1,
			'suffix' => __( '%', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-chat-launcher_scale',
		'description' => __( 'Scale of the Intaker launcher. 100% is Intaker\'s default size; lower values shrink it. Default 85.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Hide Intaker\'s Call button', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::toggle( 'bspe[chat][hide_intaker_call]', ! empty( $chat['hide_intaker_call'] ), [
			'label' => __( 'Hide Intaker\'s own floating "Call us" button', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'On by default. The bar already has a Call button, so Intaker\'s separate green Call launcher is usually a redundant duplicate. Turn off to let Intaker show it.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Advanced ----------------- */
Components::open_card(
	__( 'Advanced', 'bspe-connect' ),
	__( 'Only needed if the Chat button doesn\'t open the chat.', 'bspe-connect' )
);
Components::row(
	__( 'Open selector override', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::text( 'bspe[chat][open_selector]', (string) ( $chat['open_selector'] ?? '' ), [
			'placeholder' => '.icw--launcher--item',
		] );
	},
	[
		'id'          => 'bspe-chat-open_selector',
		'description' => __( 'CSS selector of the provider\'s launcher element that the Chat button clicks to open the chat. Leave blank to use the built-in default for the selected provider (Intaker is handled automatically).', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
