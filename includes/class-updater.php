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
 *
 * Stable channel uses GitHub tags + releases. Beta channel checks the
 * `beta` branch tip. Releases tagged with `Auto-Update: yes` in their
 * notes (or in the matching readme.txt changelog block) are silently
 * applied via WP's auto_update_plugin filter; everything else surfaces
 * as a standard one-click update.
 */
final class Updater {

	public const TOKEN_CONSTANT   = 'BSPE_CONNECT_GITHUB_TOKEN';
	public const CHANNEL_CONSTANT = 'BSPE_CONNECT_UPDATE_CHANNEL';
	public const REPO_URL         = 'https://github.com/BSPE-Legal-Marketing/bspe-connect/';
	public const SLUG             = 'bspe-connect';

	/** Marker that opt-ins a release into silent auto-update. */
	public const AUTO_UPDATE_MARKER = 'Auto-Update: yes';

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
				self::SLUG
			);

			// Beta channel = branch-tip checks. Stable channel = GitHub tags
			// + releases (PUC default when setBranch is not called).
			if ( 'beta' === $channel ) {
				$checker->setBranch( 'beta' );
			}

			$checker->setAuthentication( $token );

			$vcs_api = $checker->getVcsApi();
			if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
				// Prefer the bspe-connect.zip asset attached by the GitHub Actions
				// release workflow. Falls back to source archive if missing.
				$vcs_api->enableReleaseAssets( '/bspe-connect\.zip$/i' );
			}

			// Annotate each fetched update with the auto-update flag so WP's
			// auto_update_plugin filter can decide silently.
			$checker->addResultFilter( [ self::class, 'tag_auto_update_flag' ] );

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
	 * Result filter: scan the fetched update info for the Auto-Update marker
	 * and stamp the result with `bspe_auto_update`. WP serializes the result
	 * into the `update_plugins` site transient, so the flag persists for
	 * the auto_update_plugin filter to read later.
	 *
	 * @param object|null $info  PUC plugin-info object, or null if no update.
	 * @param mixed       $result Optional second arg from PUC; unused here.
	 *
	 * @return object|null
	 */
	public static function tag_auto_update_flag( $info, $result = null ) {
		unset( $result );
		if ( ! is_object( $info ) ) {
			return $info;
		}

		$info->bspe_auto_update = self::release_opts_into_auto_update( $info );
		return $info;
	}

	/**
	 * Look in every plausible location for the Auto-Update: yes marker.
	 * Order: upgrade_notice → sections (changelog included) → release name
	 * → fallback to a cached transient set by an explicit GitHub fetch.
	 */
	private static function release_opts_into_auto_update( object $info ): bool {
		$haystacks = [];

		if ( ! empty( $info->upgrade_notice ) ) {
			$haystacks[] = (string) $info->upgrade_notice;
		}

		if ( ! empty( $info->sections ) && is_array( $info->sections ) ) {
			foreach ( $info->sections as $body ) {
				$haystacks[] = (string) $body;
			}
		}

		if ( ! empty( $info->name ) ) {
			$haystacks[] = (string) $info->name;
		}

		// Last-ditch lookup against an explicit GitHub release body cache
		// (populated only if a site administrator runs `bspe-connect prefetch-release`
		// — wired in Phase 6.x; safe no-op otherwise).
		if ( ! empty( $info->version ) ) {
			$cached = get_transient( 'bspe_connect_release_body_' . $info->version );
			if ( is_string( $cached ) && '' !== $cached ) {
				$haystacks[] = $cached;
			}
		}

		foreach ( $haystacks as $body ) {
			$plain = wp_strip_all_tags( $body );
			if ( '' !== $plain && false !== stripos( $plain, self::AUTO_UPDATE_MARKER ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * WP auto-update decision per release. Returns true to silently auto-
	 * install, false to require manual action, or the WP default for
	 * everything that isn't us.
	 *
	 * @param bool|null $update Default decision from WP core.
	 * @param object    $item   The item being checked.
	 *
	 * @return bool|null
	 */
	public static function maybe_auto_update( $update, $item ) {
		$plugin_basename = isset( $item->plugin ) ? (string) $item->plugin : ( isset( $item->slug ) ? (string) $item->slug : '' );
		if ( BSPE_CONNECT_BASENAME !== $plugin_basename && self::SLUG !== $plugin_basename ) {
			return $update;
		}

		// Prefer the flag stamped onto the cached update entry by tag_auto_update_flag().
		$plugins = get_site_transient( 'update_plugins' );
		if ( is_object( $plugins ) && isset( $plugins->response[ BSPE_CONNECT_BASENAME ] ) ) {
			$entry = $plugins->response[ BSPE_CONNECT_BASENAME ];
			if ( isset( $entry->bspe_auto_update ) ) {
				return (bool) $entry->bspe_auto_update;
			}
		}

		return $update;
	}
}
