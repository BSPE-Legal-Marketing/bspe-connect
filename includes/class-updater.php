<?php
/**
 * Self-update integration via plugin-update-checker.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the bundled plugin-update-checker library to the private GitHub
 * repo. Degrades gracefully — if the library is missing or the GitHub PAT
 * is undefined, the plugin runs normally and only the update check is
 * skipped (with a dismissible admin notice).
 */
final class Updater {

	public const TOKEN_CONSTANT   = 'BSPE_CONNECT_GITHUB_TOKEN';
	public const CHANNEL_CONSTANT = 'BSPE_CONNECT_UPDATE_CHANNEL';
	public const REPO_URL         = 'https://github.com/BSPE-Legal-Marketing/bspe-connect/';

	public static function init(): void {
		$puc_path = BSPE_CONNECT_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $puc_path ) ) {
			return;
		}
		require_once $puc_path;

		if ( ! defined( self::TOKEN_CONSTANT ) || empty( constant( self::TOKEN_CONSTANT ) ) ) {
			add_action( 'admin_notices', [ self::class, 'render_missing_token_notice' ] );
			return;
		}

		$token   = constant( self::TOKEN_CONSTANT );
		$channel = defined( self::CHANNEL_CONSTANT ) ? constant( self::CHANNEL_CONSTANT ) : 'stable';
		if ( ! in_array( $channel, [ 'stable', 'beta' ], true ) ) {
			$channel = 'stable';
		}

		try {
			$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				self::REPO_URL,
				BSPE_CONNECT_FILE,
				'bspe-connect'
			);
			$checker->setBranch( $channel );
			$checker->setAuthentication( $token );

			$vcs_api = $checker->getVcsApi();
			if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
				$vcs_api->enableReleaseAssets();
			}

			add_filter( 'auto_update_plugin', [ self::class, 'maybe_auto_update' ], 10, 2 );
		} catch ( \Throwable $e ) {
			error_log( '[BSPE Connect] Updater init failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Renders a dismissible admin notice when the GitHub token isn't
	 * configured. Shown only on plugin pages so we don't nag site admins.
	 */
	public static function render_missing_token_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false === strpos( (string) $screen->id, 'bspe-connect' ) && 'plugins' !== $screen->id ) {
			return;
		}
		$message = sprintf(
			/* translators: %s: name of the PHP constant to define */
			esc_html__( 'BSPE Connect: GitHub token not configured — auto-updates disabled. Add %s to wp-config.php to enable updates.', 'bspe-connect' ),
			'<code>' . esc_html( self::TOKEN_CONSTANT ) . '</code>'
		);
		echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Auto-update decision per release. Phase 6 will parse the release-notes
	 * marker `Auto-Update: yes`. For Phase 1 this is a passthrough.
	 *
	 * @param bool|null $update Default decision from WP core.
	 * @param object    $item   The item being checked.
	 *
	 * @return bool|null
	 */
	public static function maybe_auto_update( $update, $item ) {
		unset( $item );
		return $update;
	}
}
