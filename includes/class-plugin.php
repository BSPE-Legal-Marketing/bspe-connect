<?php
/**
 * Plugin bootstrap.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton. Boots admin and updater. Phases 2-5 will register
 * additional services (Frontend, FormHandler, Analytics, Rest) here.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'bspe-connect', false, dirname( BSPE_CONNECT_BASENAME ) . '/languages' );

		Updater::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		} else {
			Frontend::init();
		}
	}
}
