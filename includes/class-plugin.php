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

		// Diagnostics logger — needs the admin-post hook for "Clear logs"
		// even when logging is disabled (the button stays available so old
		// entries can be wiped).
		Logger::init();

		// Form submission handler — registered regardless of context because
		// admin-ajax.php is reached from the frontend bar but reports is_admin() true.
		Form_Handler::init();

		// REST API for analytics events — same reason: rest_api_init runs in
		// both admin and frontend contexts.
		Rest::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		} else {
			Frontend::init();
		}
	}
}
