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

	public static function init(): void {
		add_action( 'admin_post_' . self::EXPORT_ACTION, [ self::class, 'export_csv' ] );
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
	private static function build_where_clause( array $filters ): array {
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
				(string) $row['id'],
				(string) $row['submitted_at'],
				(string) $row['source_button'],
				(string) $row['name'],
				(string) $row['phone'],
				(string) $row['email'],
				(string) $row['message'],
				(string) ( $row['contact_pref'] ?? '' ),
				(string) $row['page_url'],
				(string) $row['mail_status'],
				(string) $row['ip_hash'],
			] );
		}

		fclose( $out );
		exit;
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
