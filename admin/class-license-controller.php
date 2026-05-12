<?php
/**
 * License-tab admin actions.
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

use BSPE\Connect\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * admin-post handlers for the License tab — activate a key, force a
 * re-check, deactivate (release the domain binding). All three are
 * capability-gated + nonce-verified, redirect with a flash, and log
 * the outcome.
 */
final class License_Controller {

	public const ACTIVATE_ACTION   = 'bspe_connect_license_activate';
	public const ACTIVATE_NONCE    = 'bspe_connect_license_activate';
	public const CHECK_ACTION      = 'bspe_connect_license_check';
	public const CHECK_NONCE       = 'bspe_connect_license_check';
	public const DEACTIVATE_ACTION = 'bspe_connect_license_deactivate';
	public const DEACTIVATE_NONCE  = 'bspe_connect_license_deactivate';

	public const FLASH_TRANSIENT = 'bspe_connect_license_flash_';

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTIVATE_ACTION,   [ self::class, 'handle_activate' ] );
		add_action( 'admin_post_' . self::CHECK_ACTION,      [ self::class, 'handle_check' ] );
		add_action( 'admin_post_' . self::DEACTIVATE_ACTION, [ self::class, 'handle_deactivate' ] );
	}

	public static function handle_activate(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the license.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::ACTIVATE_NONCE );

		$key = isset( $_POST['license_key'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) )
			: '';

		$result = Licensing::activate( $key );

		if ( isset( $result['error'] ) ) {
			self::flash( 'error', (string) $result['error'] );
		} elseif ( 'active' === ( $result['status'] ?? '' ) ) {
			self::flash( 'success', __( 'License activated. BSPE Connect is now functional.', 'bspe-connect' ) );
		} elseif ( 'revoked' === ( $result['status'] ?? '' ) ) {
			self::flash( 'error', __( 'This license has been revoked. Contact BSPE Legal Marketing for a new key.', 'bspe-connect' ) );
		} elseif ( 'domain_mismatch' === ( $result['status'] ?? '' ) ) {
			self::flash( 'error', __( 'This key is registered to a different domain. Contact BSPE Legal Marketing to transfer it.', 'bspe-connect' ) );
		} else {
			self::flash( 'error', sprintf( /* translators: %s: server status */ __( 'License server returned an unexpected status: %s.', 'bspe-connect' ), (string) ( $result['status'] ?? 'unknown' ) ) );
		}

		self::redirect_back();
	}

	public static function handle_check(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the license.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::CHECK_NONCE );

		$result = Licensing::check();

		if ( '' !== ( $result['last_error'] ?? '' ) ) {
			self::flash( 'warn', sprintf( /* translators: %s: error message */ __( 'License check could not reach the server: %s', 'bspe-connect' ), (string) $result['last_error'] ) );
		} elseif ( 'active' === ( $result['status'] ?? '' ) ) {
			self::flash( 'success', __( 'License check succeeded.', 'bspe-connect' ) );
		} elseif ( 'revoked' === ( $result['status'] ?? '' ) ) {
			self::flash( 'error', __( 'License has been revoked by BSPE Legal Marketing.', 'bspe-connect' ) );
		} else {
			self::flash( 'warn', sprintf( /* translators: %s: server status */ __( 'License status: %s', 'bspe-connect' ), (string) ( $result['status'] ?? 'unknown' ) ) );
		}

		self::redirect_back();
	}

	public static function handle_deactivate(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the license.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::DEACTIVATE_NONCE );

		Licensing::deactivate();
		self::flash( 'success', __( 'License deactivated locally. The key can now be re-used on another install.', 'bspe-connect' ) );
		self::redirect_back();
	}

	private static function flash( string $kind, string $text ): void {
		set_transient(
			self::FLASH_TRANSIENT . get_current_user_id(),
			[ 'kind' => $kind, 'text' => $text ],
			60
		);
	}

	/**
	 * Pull (and clear) the latest flash for the current user. Called
	 * by the License tab view to render the inline notice.
	 *
	 * @return array{kind:string,text:string}|null
	 */
	public static function consume_flash(): ?array {
		$key = self::FLASH_TRANSIENT . get_current_user_id();
		$raw = get_transient( $key );
		if ( ! is_array( $raw ) ) {
			return null;
		}
		delete_transient( $key );
		return [
			'kind' => (string) ( $raw['kind'] ?? 'info' ),
			'text' => (string) ( $raw['text'] ?? '' ),
		];
	}

	private static function redirect_back(): void {
		wp_safe_redirect(
			add_query_arg(
				[ 'page' => Admin::PAGE_SLUG, 'tab' => 'license' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
