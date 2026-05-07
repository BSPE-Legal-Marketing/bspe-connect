<?php
/**
 * Diagnostic logger — bounded ring buffer in wp_options, surfaced in
 * the admin "Logs" tab.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only event log capped at MAX_ENTRIES (newest wins). Writes only
 * happen when `diagnostics.logging_enabled` is on, so sites that don't
 * need debugging don't accumulate option-table bloat. Existing entries
 * persist when the toggle flips off — the user clears them explicitly
 * via the "Clear logs" button.
 */
final class Logger {

	public const OPTION_KEY  = 'bspe_connect_log';
	public const MAX_ENTRIES = 200;

	public const CLEAR_ACTION = 'bspe_connect_clear_logs';
	public const CLEAR_NONCE  = 'bspe_connect_clear_logs';

	private const LEVELS = [ 'info', 'warn', 'error' ];

	public static function init(): void {
		add_action( 'admin_post_' . self::CLEAR_ACTION, [ self::class, 'handle_clear' ] );
	}

	public static function is_enabled(): bool {
		return (bool) Settings::get( 'diagnostics.logging_enabled', false );
	}

	/**
	 * Append a log entry. No-op when logging is disabled.
	 *
	 * @param string              $level   One of: info / warn / error.
	 * @param string              $message Short human-readable summary.
	 * @param array<string,mixed> $context Extra structured fields.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'info';
		}

		$entries   = self::all();
		$entries[] = [
			'time'    => current_time( 'mysql' ),
			'level'   => $level,
			'message' => substr( (string) $message, 0, 500 ),
			'context' => self::clean_context( $context ),
		];

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		// Autoload off — the log can grow to ~200 entries × ~1KB each
		// and we only need it on the Logs admin page.
		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * @return array<int, array{time:string, level:string, message:string, context:array}>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		return is_array( $stored ) ? $stored : [];
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	public static function handle_clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear logs.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::CLEAR_NONCE );
		self::clear();

		$redirect = add_query_arg(
			[ 'page' => 'bspe-connect', 'tab' => 'logs', 'cleared' => '1' ],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Redact common secret keys, cap string lengths, drop deeply nested
	 * non-scalars. Recurses one level deep — log context isn't meant to
	 * carry full object trees.
	 *
	 * @param array<string,mixed> $context
	 *
	 * @return array<string,mixed>
	 */
	private static function clean_context( array $context, int $depth = 0 ): array {
		$out = [];
		foreach ( $context as $key => $value ) {
			$key = (string) $key;

			// Hide values for keys that look like credentials.
			if ( preg_match( '/(token|secret|password|api[_-]?key|nonce)/i', $key ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}

			if ( is_string( $value ) ) {
				$out[ $key ] = substr( $value, 0, 1000 );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
			} elseif ( is_array( $value ) && $depth < 3 ) {
				$out[ $key ] = self::clean_context( $value, $depth + 1 );
			} else {
				$out[ $key ] = '[' . gettype( $value ) . ']';
			}
		}
		return $out;
	}
}
