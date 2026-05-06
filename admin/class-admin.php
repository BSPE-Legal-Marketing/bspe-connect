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
	 * @var array<string, array{label:string, view:string, phase:int}>
	 */
	private const TABS = [
		'general'     => [
			'label' => 'General',
			'view'  => 'settings-general.php',
			'phase' => 4,
		],
		'buttons'     => [
			'label' => 'Buttons',
			'view'  => 'settings-buttons.php',
			'phase' => 4,
		],
		'form'        => [
			'label' => 'Form',
			'view'  => 'settings-form.php',
			'phase' => 4,
		],
		'design'      => [
			'label' => 'Design',
			'view'  => 'settings-design.php',
			'phase' => 4,
		],
		'display'     => [
			'label' => 'Display Rules',
			'view'  => 'settings-display.php',
			'phase' => 4,
		],
		'submissions' => [
			'label' => 'Submissions & Analytics',
			'view'  => 'submissions-list.php',
			'phase' => 5,
		],
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
	 * @return array<string, array{label:string, view:string, phase:int}>
	 */
	public static function tabs(): array {
		return self::TABS;
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
