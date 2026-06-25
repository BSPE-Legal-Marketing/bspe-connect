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
			'placeholder' => 'ticketcrushers',
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
	__( 'Button icon', 'bspe-connect' ),
	static function () use ( $chat ): void {
		Components::text( 'bspe[chat][button_icon]', (string) ( $chat['button_icon'] ?? 'comment-dots' ), [
			'placeholder' => 'comment-dots',
		] );
	},
	[
		'id'          => 'bspe-chat-button_icon',
		'description' => __( 'A Font Awesome solid icon name (no <em>fa-</em> prefix), e.g. <em>comment-dots</em>, <em>comments</em>, <em>headset</em>. Leave blank for a label-only button.', 'bspe-connect' ),
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
