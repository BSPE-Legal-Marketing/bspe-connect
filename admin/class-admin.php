<?php
/**
 * Admin menu, settings page, and asset enqueueing.
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the BSPE Connect admin menu and renders the tab shell. Tab
 * content is filled in by view files under admin/views/. Phase 4 will
 * wire those views to real settings handlers.
 */
final class Admin {

	public const PAGE_SLUG  = 'bspe-connect';
	public const MENU_TITLE = 'BSPE Connect';
	public const CAPABILITY = 'manage_options';

	/**
	 * @var array<string, array{label:string, view:string, phase:int, icon:string, hint:string}>
	 */
	private const TABS = [
		'general'     => [
			'label' => 'General',
			'view'  => 'settings-general.php',
			'phase' => 4,
			'icon'  => 'gear',
			'hint'  => 'Master enable, welcome bubble, scroll behavior',
		],
		'buttons'     => [
			'label' => 'Buttons',
			'view'  => 'settings-buttons.php',
			'phase' => 4,
			'icon'  => 'buttons',
			'hint'  => 'Connect, Call, Text, Email — labels, icons, modes',
		],
		'form'        => [
			'label' => 'Form',
			'view'  => 'settings-form.php',
			'phase' => 4,
			'icon'  => 'form',
			'hint'  => 'Field visibility, copy, mail delivery, anti-spam',
		],
		'design'      => [
			'label' => 'Design',
			'view'  => 'settings-design.php',
			'phase' => 4,
			'icon'  => 'design',
			'hint'  => 'Firm name, color picker, font selection',
		],
		'display'     => [
			'label' => 'Display Rules',
			'view'  => 'settings-display.php',
			'phase' => 4,
			'icon'  => 'display',
			'hint'  => 'Where the bar appears: site-wide / pages / posts / slugs',
		],
		'submissions' => [
			'label' => 'Submissions',
			'view'  => 'submissions-list.php',
			'phase' => 5,
			'icon'  => 'analytics',
			'hint'  => 'Lead submissions table, CSV export, analytics dashboard',
		],
	];

	/**
	 * Inline SVG icons for the sidebar. Single-color, uses currentColor so
	 * the active/hover states inherit from the surrounding link.
	 *
	 * @var array<string, string>
	 */
	private const ICONS = [
		'gear'      => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="2.5"/><path d="M10 1.5v2M10 16.5v2M3.5 3.5l1.4 1.4M15.1 15.1l1.4 1.4M1.5 10h2M16.5 10h2M3.5 16.5l1.4-1.4M15.1 4.9l1.4-1.4"/></svg>',
		'buttons'   => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="6" width="15" height="8" rx="2.5"/><path d="M6.5 10h7"/></svg>',
		'form'      => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 3.5h13v13h-13z"/><path d="M6.5 7h7M6.5 10h7M6.5 13h4"/></svg>',
		'design'    => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5C5.85 2.5 2.5 5.85 2.5 10s3.35 7.5 7.5 7.5c1.1 0 1.7-.85 1.4-1.7-.4-1.05.4-2.05 1.5-2.05h1.6c1.4 0 2.5-1.1 2.5-2.5C17 6.7 13.85 2.5 10 2.5z"/><circle cx="6.5" cy="9" r=".8" fill="currentColor"/><circle cx="9" cy="6" r=".8" fill="currentColor"/><circle cx="13" cy="6.5" r=".8" fill="currentColor"/></svg>',
		'display'   => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 10s2.8-5 8-5 8 5 8 5-2.8 5-8 5-8-5-8-5z"/><circle cx="10" cy="10" r="2"/></svg>',
		'analytics' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 17h14"/><rect x="5" y="11" width="2.5" height="5" rx=".4"/><rect x="9" y="7" width="2.5" height="9" rx=".4"/><rect x="13" y="3.5" width="2.5" height="12.5" rx=".4"/></svg>',
	];

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'BSPE Connect', 'bspe-connect' ),
			self::MENU_TITLE,
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ self::class, 'render_page' ],
			'dashicons-format-chat',
			30
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'bspe-connect-admin',
			BSPE_CONNECT_URL . 'admin/assets/admin.css',
			[],
			BSPE_CONNECT_VERSION
		);
		wp_enqueue_script(
			'bspe-connect-admin',
			BSPE_CONNECT_URL . 'admin/assets/admin.js',
			[],
			BSPE_CONNECT_VERSION,
			true
		);
	}

	/**
	 * Returns the active tab id. Falls back to 'general' if the tab query
	 * arg is missing or unknown.
	 */
	public static function active_tab(): string {
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation
		return array_key_exists( $requested, self::TABS ) ? $requested : 'general';
	}

	/**
	 * @return array<string, array{label:string, view:string, phase:int, icon:string, hint:string}>
	 */
	public static function tabs(): array {
		return self::TABS;
	}

	/**
	 * Returns raw SVG markup for one of the registered sidebar icons. Output
	 * is whitelisted via wp_kses() at the call site to avoid double-encoding
	 * stroke attributes.
	 */
	public static function icon( string $key ): string {
		return self::ICONS[ $key ] ?? '';
	}

	/**
	 * Allow-list of SVG attributes for wp_kses output of sidebar icons.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function svg_kses(): array {
		$global = [
			'fill'             => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'viewBox'          => true,
			'xmlns'            => true,
			'aria-hidden'      => true,
			'd'                => true,
			'cx'               => true,
			'cy'               => true,
			'r'                => true,
			'x'                => true,
			'y'                => true,
			'width'            => true,
			'height'           => true,
			'rx'               => true,
			'ry'               => true,
		];
		return [
			'svg'    => $global,
			'circle' => $global,
			'rect'   => $global,
			'path'   => $global,
			'g'      => $global,
		];
	}

	public static function tab_url( string $tab ): string {
		return add_query_arg(
			[
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			],
			admin_url( 'admin.php' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bspe-connect' ) );
		}
		$active = self::active_tab();
		$tabs   = self::tabs();
		require BSPE_CONNECT_DIR . 'admin/views/shell.php';
	}
}
