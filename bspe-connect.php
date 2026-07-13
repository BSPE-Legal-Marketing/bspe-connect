<?php
/**
 * Plugin Name:       BSPE Connect
 * Plugin URI:        https://github.com/BSPE-Legal-Marketing/bspe-connect
 * Description:       Mobile contact bar with lead capture, by BSPE Legal Marketing.
 * Version:           3.5.8
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            BSPE Legal Marketing
 * Author URI:        https://bsplegalmarketing.com/
 * License:           Proprietary
 * Text Domain:       bspe-connect
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package BSPE\Connect
 */

defined( 'ABSPATH' ) || exit;

define( 'BSPE_CONNECT_VERSION', '3.5.8' );
define( 'BSPE_CONNECT_FILE', __FILE__ );
define( 'BSPE_CONNECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'BSPE_CONNECT_URL', plugin_dir_url( __FILE__ ) );
define( 'BSPE_CONNECT_BASENAME', plugin_basename( __FILE__ ) );

require_once BSPE_CONNECT_DIR . 'includes/class-plugin.php';
require_once BSPE_CONNECT_DIR . 'includes/class-activator.php';
require_once BSPE_CONNECT_DIR . 'includes/class-deactivator.php';
require_once BSPE_CONNECT_DIR . 'includes/class-settings.php';
require_once BSPE_CONNECT_DIR . 'includes/class-theme-palette.php';
require_once BSPE_CONNECT_DIR . 'includes/class-licensing.php';
require_once BSPE_CONNECT_DIR . 'includes/class-updater.php';
require_once BSPE_CONNECT_DIR . 'includes/class-submissions.php';
require_once BSPE_CONNECT_DIR . 'includes/class-events.php';
require_once BSPE_CONNECT_DIR . 'includes/class-logger.php';
require_once BSPE_CONNECT_DIR . 'includes/class-mailer.php';
require_once BSPE_CONNECT_DIR . 'includes/class-webhook.php';
require_once BSPE_CONNECT_DIR . 'includes/class-form-handler.php';
require_once BSPE_CONNECT_DIR . 'includes/class-rest.php';
require_once BSPE_CONNECT_DIR . 'includes/class-frontend.php';
require_once BSPE_CONNECT_DIR . 'includes/class-hide-users-rest.php';
require_once BSPE_CONNECT_DIR . 'includes/class-external-links.php';
require_once BSPE_CONNECT_DIR . 'includes/class-qr-indexer.php';
require_once BSPE_CONNECT_DIR . 'includes/class-in-post-widget.php';
require_once BSPE_CONNECT_DIR . 'admin/class-admin.php';
require_once BSPE_CONNECT_DIR . 'admin/class-settings-saver.php';
require_once BSPE_CONNECT_DIR . 'admin/class-submissions-controller.php';
require_once BSPE_CONNECT_DIR . 'admin/class-analytics-controller.php';
require_once BSPE_CONNECT_DIR . 'admin/class-license-controller.php';
require_once BSPE_CONNECT_DIR . 'admin/views/components.php';

register_activation_hook( __FILE__, [ 'BSPE\\Connect\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BSPE\\Connect\\Deactivator', 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function () {
		\BSPE\Connect\Plugin::instance()->boot();
	}
);
