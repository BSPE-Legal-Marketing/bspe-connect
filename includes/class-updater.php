<?php
/**
 * Self-update integration via plugin-update-checker.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the bundled plugin-update-checker library to the public GitHub
 * repo. Degrades gracefully — if the library is missing the plugin runs
 * normally and only the update check is skipped.
 *
 * Update model:
 *   - Stable channel uses GitHub tags + releases (default).
 *   - Beta channel checks the `beta` branch tip.
 *   - Updates are NEVER silent-installed. Every release surfaces as a
 *     standard "Update available" notification in wp-admin → Plugins.
 *     The site admin clicks Update, WP downloads the release zip, and
 *     the SHA-256 verifier below confirms the zip matches the published
 *     checksum before WP extracts it.
 *
 * Supply-chain defense:
 *   The release workflow attaches bspe-connect.zip.sha256 next to the
 *   zip. The verifier in verify_and_download_zip() fetches the .sha256
 *   file from the matching release, hashes the downloaded zip locally,
 *   and refuses the install on mismatch — so a compromised repo can't
 *   ship a backdoor without ALSO compromising the checksum file in the
 *   same release, which requires the same level of GitHub access.
 *
 *   Safety hatch: defining BSPE_CONNECT_REQUIRE_CHECKSUM = false in
 *   wp-config skips verification (useful only if a checksum upload
 *   somehow fails and the admin needs to unblock updates manually).
 */
final class Updater {

	public const TOKEN_CONSTANT             = 'BSPE_CONNECT_GITHUB_TOKEN';
	public const CHANNEL_CONSTANT           = 'BSPE_CONNECT_UPDATE_CHANNEL';
	public const REQUIRE_CHECKSUM_CONSTANT  = 'BSPE_CONNECT_REQUIRE_CHECKSUM';
	public const REPO_URL                   = 'https://github.com/BSPE-Legal-Marketing/bspe-connect/';
	public const REPO_OWNER_SLUG            = 'BSPE-Legal-Marketing/bspe-connect';
	public const SLUG                       = 'bspe-connect';
	public const ZIP_NAME                   = 'bspe-connect.zip';
	public const CHECKSUM_NAME              = 'bspe-connect.zip.sha256';

	public static function init(): void {
		$puc_path = BSPE_CONNECT_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $puc_path ) ) {
			return;
		}
		require_once $puc_path;

		// Token is OPTIONAL. The repo is public, so PUC works without auth.
		// A token still helps when configured — it raises GitHub's API rate
		// limit from 60/hour (anonymous) to 5,000/hour and is required if
		// the repo is ever flipped back to private.
		$token   = ( defined( self::TOKEN_CONSTANT ) && '' !== trim( (string) constant( self::TOKEN_CONSTANT ) ) )
			? (string) constant( self::TOKEN_CONSTANT )
			: '';
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

			if ( '' !== $token ) {
				$checker->setAuthentication( $token );
			}

			$vcs_api = $checker->getVcsApi();
			if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
				// Prefer the bspe-connect.zip asset attached by the GitHub Actions
				// release workflow. Falls back to source archive if missing.
				$vcs_api->enableReleaseAssets( '/bspe-connect\.zip$/i' );
			}

			// SHA-256 verification gate. Hooks the upgrader download step so
			// we can fetch the published checksum, verify the downloaded zip
			// matches, and refuse the install on mismatch. Skipped only on
			// beta channel (branch tip has no released checksum) and when
			// REQUIRE_CHECKSUM is explicitly set to false in wp-config.
			if ( self::checksum_required() && 'beta' !== $channel ) {
				add_filter( 'upgrader_pre_download', [ self::class, 'verify_and_download_zip' ], 10, 4 );
			}
		} catch ( \Throwable $e ) {
			error_log( '[BSPE Connect] Updater init failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Intercept WP's zip download for our plugin, verify the SHA-256 against
	 * the published checksum, and only then hand the local file back to WP
	 * for extraction. Returning a non-bool / non-string defers to default WP
	 * behavior (any other plugin's update is untouched).
	 *
	 * @param bool|string|\WP_Error $reply      Current upstream reply.
	 * @param string                $package    The download URL.
	 * @param \WP_Upgrader|null     $upgrader   The active upgrader.
	 * @param array<string,mixed>   $hook_extra Hook context (includes ['plugin']).
	 *
	 * @return bool|string|\WP_Error
	 */
	public static function verify_and_download_zip( $reply, $package, $upgrader = null, $hook_extra = [] ) {
		unset( $upgrader );

		// Only act on our plugin. Other plugin updates flow through
		// untouched so we don't accidentally break the rest of the site.
		$plugin_basename = is_array( $hook_extra ) ? (string) ( $hook_extra['plugin'] ?? '' ) : '';
		if ( BSPE_CONNECT_BASENAME !== $plugin_basename ) {
			return $reply;
		}

		// Only act on our own download URLs. If something else has hijacked
		// the package (unusual but possible in dev setups), pass through.
		if ( false === stripos( (string) $package, 'github.com' ) || false === stripos( (string) $package, self::REPO_OWNER_SLUG ) ) {
			return $reply;
		}

		// download_url() lives in wp-admin/includes/file.php and isn't
		// guaranteed loaded in every context (cron, REST). Pull it in.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$version = self::extract_version_from_url( (string) $package );
		if ( '' === $version ) {
			self::report_security_failure( 'Could not determine version from package URL; refusing install.', [
				'package' => $package,
			] );
			return new \WP_Error(
				'bspe_checksum_unknown_version',
				__( 'BSPE Connect: could not determine release version, refusing install for safety. Try again or contact BSPE.', 'bspe-connect' )
			);
		}

		$expected_sha = self::fetch_published_sha( $version );
		if ( '' === $expected_sha ) {
			self::report_security_failure( 'Published SHA-256 checksum file not found; refusing install.', [
				'version' => $version,
			] );
			return new \WP_Error(
				'bspe_checksum_missing',
				sprintf(
					/* translators: %s: version number */
					__( 'BSPE Connect: no published SHA-256 checksum for v%s, refusing install for safety. Define BSPE_CONNECT_REQUIRE_CHECKSUM as false in wp-config to override.', 'bspe-connect' ),
					$version
				)
			);
		}

		// Download the zip ourselves so we can hash before handing the file
		// path back to WP. download_url() saves to a sys_get_temp_dir() file
		// and returns the path; on failure it returns WP_Error.
		$local = download_url( (string) $package );
		if ( is_wp_error( $local ) ) {
			return $local;
		}

		$actual_sha = (string) hash_file( 'sha256', $local );
		if ( '' === $actual_sha ) {
			wp_delete_file( $local );
			self::report_security_failure( 'hash_file() returned empty result; refusing install.', [
				'version' => $version,
			] );
			return new \WP_Error( 'bspe_checksum_hash_failed', __( 'BSPE Connect: could not hash the downloaded zip, refusing install.', 'bspe-connect' ) );
		}

		if ( ! hash_equals( strtolower( $expected_sha ), strtolower( $actual_sha ) ) ) {
			wp_delete_file( $local );
			self::report_security_failure( 'SHA-256 mismatch — refusing install (potential tampered release).', [
				'version'     => $version,
				'expected'    => $expected_sha,
				'actual'      => $actual_sha,
			] );
			return new \WP_Error(
				'bspe_checksum_mismatch',
				sprintf(
					/* translators: 1: expected SHA, 2: actual SHA */
					__( 'BSPE Connect: SHA-256 mismatch (expected %1$s, got %2$s). The release zip does not match the published checksum — refusing install.', 'bspe-connect' ),
					$expected_sha,
					$actual_sha
				)
			);
		}

		Logger::log( 'info', 'Plugin update SHA-256 verified', [
			'version' => $version,
			'sha256'  => $actual_sha,
		] );

		return $local;
	}

	/**
	 * Read BSPE_CONNECT_REQUIRE_CHECKSUM and default to true. Letting site
	 * owners disable verification only via an explicit wp-config constant
	 * (not a checkbox) keeps the safety hatch out of reach of compromised
	 * admin sessions.
	 */
	private static function checksum_required(): bool {
		if ( ! defined( self::REQUIRE_CHECKSUM_CONSTANT ) ) {
			return true;
		}
		return (bool) constant( self::REQUIRE_CHECKSUM_CONSTANT );
	}

	/**
	 * Pull "1.2.3" out of a GitHub release-asset URL like
	 * https://github.com/.../releases/download/v1.2.3/bspe-connect.zip.
	 */
	private static function extract_version_from_url( string $url ): string {
		if ( preg_match( '#/releases/download/v?([0-9]+\.[0-9]+\.[0-9]+(?:[\.\-][0-9a-zA-Z\.\-]+)?)/#', $url, $m ) ) {
			return (string) $m[1];
		}
		return '';
	}

	/**
	 * Fetch the bspe-connect.zip.sha256 asset from the matching GitHub
	 * release and return the first 64 hex chars (the digest). Returns ''
	 * on any failure so the caller can refuse the install.
	 */
	private static function fetch_published_sha( string $version ): string {
		$url = sprintf(
			'https://github.com/%s/releases/download/v%s/%s',
			self::REPO_OWNER_SLUG,
			$version,
			self::CHECKSUM_NAME
		);

		$response = wp_remote_get( $url, [
			'timeout'     => 15,
			'redirection' => 5,
			'user-agent'  => 'BSPE-Connect-Updater/' . BSPE_CONNECT_VERSION,
		] );

		if ( is_wp_error( $response ) ) {
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return '';
		}

		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( '' === $body ) {
			return '';
		}

		// File format is `sha256sum` output: "<64-hex>  <filename>".
		if ( preg_match( '/^([0-9a-fA-F]{64})\b/', $body, $m ) ) {
			return strtolower( (string) $m[1] );
		}
		return '';
	}

	/**
	 * Surface a security event through every channel admins might check.
	 * Logger only writes when diagnostics is enabled, so we also push to
	 * error_log() unconditionally. The WP_Error returned by the caller
	 * surfaces in the wp-admin update UI.
	 *
	 * @param array<string,mixed> $context
	 */
	private static function report_security_failure( string $message, array $context = [] ): void {
		Logger::log( 'error', 'Update verification: ' . $message, $context );
		error_log( '[BSPE Connect] Update verification: ' . $message . ' ' . wp_json_encode( $context ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
