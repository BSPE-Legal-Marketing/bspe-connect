<?php
/**
 * General settings tab — master enable, welcome bubble, display behavior.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;
use BSPE\Connect\Admin\Settings_Saver;

$enabled    = (bool) Settings::get( 'enabled', false );
$bubble     = is_array( Settings::get( 'welcome_bubble', [] ) ) ? Settings::get( 'welcome_bubble', [] ) : [];
$display    = is_array( Settings::get( 'display', [] ) ) ? Settings::get( 'display', [] ) : [];
$action_url = admin_url( 'admin-post.php' );

Components::open_form( 'general', $action_url );

Components::open_card(
	__( 'Master controls', 'bspe-connect' ),
	__( 'Turn the bar on for visitors. Off until you finish configuring buttons and the lead form.', 'bspe-connect' )
);
Components::row(
	__( 'Bar enabled', 'bspe-connect' ),
	static function () use ( $enabled ): void {
		Components::toggle( 'bspe[enabled]', $enabled, [
			'label' => __( 'Show the contact bar to mobile visitors', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Visitors only see the bar when this is on AND a display rule matches AND at least one button is enabled.', 'bspe-connect' ) ]
);
Components::close_card();

Components::open_card(
	__( 'Welcome bubble', 'bspe-connect' ),
	__( 'A small message that floats above the bar to introduce the firm.', 'bspe-connect' )
);
Components::row(
	__( 'Show welcome bubble', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::toggle( 'bspe[welcome_bubble][enabled]', ! empty( $bubble['enabled'] ), [
			'label' => __( 'Display the bubble', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Heading', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::text( 'bspe[welcome_bubble][heading]', (string) ( $bubble['heading'] ?? '' ), [
			'placeholder' => 'Welcome to {firm_name}',
			'maxlength'   => 120,
		] );
	},
	[
		'id'          => 'bspe-welcome_bubble-heading',
		'description' => __( 'Use <code>{firm_name}</code> or <code>{site_name}</code> as placeholders.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Message', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::textarea( 'bspe[welcome_bubble][message]', (string) ( $bubble['message'] ?? '' ), [
			'rows'      => 2,
			'maxlength' => 240,
		] );
	},
	[ 'id' => 'bspe-welcome_bubble-message' ]
);
Components::row(
	__( 'Avatar', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::checkbox( 'bspe[welcome_bubble][show_avatar]', ! empty( $bubble['show_avatar'] ), [
			'label' => __( 'Show an avatar next to the message', 'bspe-connect' ),
		] );
		echo '<div class="bspe-row__sub" data-bspe-show-when="bspe-welcome_bubble-show_avatar">';
		Components::media( 'bspe[welcome_bubble][avatar_id]', (int) ( $bubble['avatar_id'] ?? 0 ), [
			'button_text' => __( 'Choose avatar', 'bspe-connect' ),
			'modal_title' => __( 'Select avatar image', 'bspe-connect' ),
		] );
		echo '</div>';
	}
);
Components::row(
	__( 'When to show', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::radio_pills( 'bspe[welcome_bubble][trigger]', (string) ( $bubble['trigger'] ?? 'auto' ), [
			'auto'  => __( 'After a delay', 'bspe-connect' ),
			'click' => __( 'On Connect click', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Delay', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::number( 'bspe[welcome_bubble][delay]', (int) ( $bubble['delay'] ?? 3 ), [
			'min'    => 0,
			'max'    => 60,
			'suffix' => __( 'seconds', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-welcome_bubble-delay',
		'description' => __( 'How long to wait after the bar appears before showing the bubble.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Repeat rule', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::radio_pills( 'bspe[welcome_bubble][repeat]', (string) ( $bubble['repeat'] ?? 'session' ), [
			'session' => __( 'Once per session', 'bspe-connect' ),
			'once'    => __( 'Once ever', 'bspe-connect' ),
			'always'  => __( 'Every page', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Once dismissed, when should the bubble reappear?', 'bspe-connect' ) ]
);
Components::close_card();

Components::open_card(
	__( 'Display behavior', 'bspe-connect' ),
	__( 'How the bar reacts to scroll, and where the mobile breakpoint sits.', 'bspe-connect' )
);
Components::row(
	__( 'Scroll threshold', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::number( 'bspe[display][scroll_threshold]', (int) ( $display['scroll_threshold'] ?? 200 ), [
			'min'    => 0,
			'max'    => 5000,
			'step'   => 10,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-display-scroll_threshold',
		'description' => __( 'How far the visitor scrolls before the bar slides into view.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Hide on scroll up', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::toggle( 'bspe[display][hide_on_scroll_up]', ! empty( $display['hide_on_scroll_up'] ), [
			'label' => __( 'Slide the bar away when the visitor scrolls up', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Mobile breakpoint', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::number( 'bspe[display][mobile_breakpoint]', (int) ( $display['mobile_breakpoint'] ?? 768 ), [
			'min'    => 320,
			'max'    => 2000,
			'step'   => 10,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-display-mobile_breakpoint',
		'description' => __( 'Screens wider than this never see the bar.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
