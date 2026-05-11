<?php
/**
 * Submissions list — paginated table with filters, expandable rows, CSV export.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Admin\Admin;
use BSPE\Connect\Admin\Submissions_Controller;

$filters    = Submissions_Controller::read_filters_from_request();
$result     = Submissions_Controller::query( $filters );
$rows       = $result['rows'];
$total      = $result['total'];
$per_page   = $result['per_page'];
$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
$paged       = $filters['paged'];

$base_url    = Admin::tab_url( 'submissions' );
$export_url  = Submissions_Controller::export_url( $filters );

// One-time flash messages after a delete redirect.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flash
$flash_kind  = isset( $_GET['bspe_flash'] ) ? sanitize_key( wp_unslash( (string) $_GET['bspe_flash'] ) ) : '';
$flash_count = isset( $_GET['bspe_count'] ) ? (int) $_GET['bspe_count'] : 0;
// phpcs:enable

$source_options = [
	'all'   => __( 'All sources', 'bspe-connect' ),
	'text'  => __( 'Text form', 'bspe-connect' ),
	'email' => __( 'Email form', 'bspe-connect' ),
];
$status_options = [
	'all'     => __( 'Any status', 'bspe-connect' ),
	'sent'    => __( 'Sent', 'bspe-connect' ),
	'failed'  => __( 'Failed', 'bspe-connect' ),
	'pending' => __( 'Pending', 'bspe-connect' ),
];
?>

<section class="bspe-card bspe-card--submissions">
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Submissions', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php
				printf(
					/* translators: %d: total submission count */
					esc_html( _n( '%d lead captured.', '%d leads captured.', (int) $total, 'bspe-connect' ) ),
					(int) $total
				);
				?>
			</p>
		</div>
		<div class="bspe-card__head-actions">
			<a class="bspe-button bspe-button--secondary" href="<?php echo esc_url( $export_url ); ?>">
				<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M8 1.5v9M4.5 7l3.5 3.5L11.5 7M2.5 13.5h11"/>
				</svg>
				<?php esc_html_e( 'Export CSV', 'bspe-connect' ); ?>
			</a>

			<?php if ( $total > 0 ) :
				$filters_active = '' !== $filters['from']
					|| '' !== $filters['to']
					|| 'all' !== $filters['source']
					|| 'all' !== $filters['status'];
				?>
				<form method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					class="bspe-inline-form"
					data-bspe-delete-all-form
					data-bspe-delete-count="<?php echo esc_attr( (string) $total ); ?>"
					data-bspe-delete-scoped="<?php echo $filters_active ? '1' : '0'; ?>"
				>
					<?php wp_nonce_field( Submissions_Controller::DELETE_ALL_NONCE ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( Submissions_Controller::DELETE_ALL_ACTION ); ?>" />
					<input type="hidden" name="from"   value="<?php echo esc_attr( $filters['from'] ); ?>" />
					<input type="hidden" name="to"     value="<?php echo esc_attr( $filters['to'] ); ?>" />
					<input type="hidden" name="source" value="<?php echo esc_attr( $filters['source'] ); ?>" />
					<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
					<button type="submit" class="bspe-button bspe-button--danger">
						<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M2.5 4.5h11M6 4.5V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1.5M5 4.5l.5 8a1 1 0 0 0 1 .95h3a1 1 0 0 0 1-.95l.5-8"/>
						</svg>
						<?php
						echo esc_html(
							$filters_active
								? __( 'Delete all matching filters', 'bspe-connect' )
								: __( 'Delete all submissions', 'bspe-connect' )
						);
						?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( 'deleted_selected' === $flash_kind ) : ?>
		<div class="bspe-notice bspe-notice--success" role="status">
			<?php
			printf(
				/* translators: %d: number of rows deleted */
				esc_html( _n( '%d submission deleted.', '%d submissions deleted.', $flash_count, 'bspe-connect' ) ),
				(int) $flash_count
			);
			?>
		</div>
	<?php elseif ( 'deleted_all' === $flash_kind ) : ?>
		<div class="bspe-notice bspe-notice--success" role="status">
			<?php
			printf(
				/* translators: %d: number of rows deleted */
				esc_html( _n( '%d submission deleted (all matching filters).', '%d submissions deleted (all matching filters).', $flash_count, 'bspe-connect' ) ),
				(int) $flash_count
			);
			?>
		</div>
	<?php endif; ?>

	<form method="get" class="bspe-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE_SLUG ); ?>" />
		<input type="hidden" name="tab"  value="submissions" />

		<div class="bspe-filters__group">
			<label for="bspe-filter-from"><?php esc_html_e( 'From', 'bspe-connect' ); ?></label>
			<input type="date" id="bspe-filter-from" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>" class="bspe-input bspe-input--compact" />
		</div>
		<div class="bspe-filters__group">
			<label for="bspe-filter-to"><?php esc_html_e( 'To', 'bspe-connect' ); ?></label>
			<input type="date" id="bspe-filter-to" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>" class="bspe-input bspe-input--compact" />
		</div>
		<div class="bspe-filters__group">
			<label for="bspe-filter-source"><?php esc_html_e( 'Source', 'bspe-connect' ); ?></label>
			<div class="bspe-select-wrap bspe-select-wrap--compact">
				<select id="bspe-filter-source" name="source" class="bspe-input bspe-select">
					<?php foreach ( $source_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['source'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="bspe-filters__group">
			<label for="bspe-filter-status"><?php esc_html_e( 'Mail status', 'bspe-connect' ); ?></label>
			<div class="bspe-select-wrap bspe-select-wrap--compact">
				<select id="bspe-filter-status" name="status" class="bspe-input bspe-select">
					<?php foreach ( $status_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="bspe-filters__actions">
			<button type="submit" class="bspe-button bspe-button--primary"><?php esc_html_e( 'Apply', 'bspe-connect' ); ?></button>
			<a class="bspe-button bspe-button--ghost" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset', 'bspe-connect' ); ?></a>
		</div>
	</form>

	<?php if ( empty( $rows ) ) : ?>
		<div class="bspe-empty">
			<svg viewBox="0 0 48 48" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<rect x="6" y="10" width="36" height="28" rx="4"/>
				<path d="M8 14l16 12 16-12"/>
			</svg>
			<p class="bspe-empty__title"><?php esc_html_e( 'No submissions match your filters yet.', 'bspe-connect' ); ?></p>
			<p class="bspe-empty__hint"><?php esc_html_e( 'Reset the filters or wait for the first lead to come in.', 'bspe-connect' ); ?></p>
		</div>
	<?php else : ?>
		<form method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			class="bspe-bulk-form"
			data-bspe-bulk-form
		>
			<?php wp_nonce_field( Submissions_Controller::DELETE_SELECTED_NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( Submissions_Controller::DELETE_SELECTED_ACTION ); ?>" />

			<div class="bspe-bulk-bar" data-bspe-bulk-bar hidden>
				<span class="bspe-bulk-bar__count">
					<span data-bspe-bulk-count>0</span>
					<?php esc_html_e( 'selected', 'bspe-connect' ); ?>
				</span>
				<button type="submit" class="bspe-button bspe-button--danger" data-bspe-bulk-delete>
					<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M2.5 4.5h11M6 4.5V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1.5M5 4.5l.5 8a1 1 0 0 0 1 .95h3a1 1 0 0 0 1-.95l.5-8"/>
					</svg>
					<?php esc_html_e( 'Delete selected', 'bspe-connect' ); ?>
				</button>
				<button type="button" class="bspe-button bspe-button--ghost" data-bspe-bulk-clear>
					<?php esc_html_e( 'Clear selection', 'bspe-connect' ); ?>
				</button>
			</div>

			<div class="bspe-table-wrap">
				<table class="bspe-table">
					<thead>
						<tr>
							<th class="bspe-table__col-check">
								<input type="checkbox"
									data-bspe-check-all
									aria-label="<?php esc_attr_e( 'Select all submissions on this page', 'bspe-connect' ); ?>"
								/>
							</th>
							<th class="bspe-table__col-toggle" aria-label="<?php esc_attr_e( 'Expand row', 'bspe-connect' ); ?>"></th>
							<th class="bspe-table__col-date"><?php esc_html_e( 'Submitted', 'bspe-connect' ); ?></th>
							<th class="bspe-table__col-source"><?php esc_html_e( 'Source', 'bspe-connect' ); ?></th>
							<th class="bspe-table__col-name"><?php esc_html_e( 'Name', 'bspe-connect' ); ?></th>
							<th class="bspe-table__col-phone"><?php esc_html_e( 'Phone', 'bspe-connect' ); ?></th>
							<th class="bspe-table__col-email"><?php esc_html_e( 'Email', 'bspe-connect' ); ?></th>
							<th class="bspe-table__col-page"><?php esc_html_e( 'Page', 'bspe-connect' ); ?></th>
							<th class="bspe-table__col-status"><?php esc_html_e( 'Status', 'bspe-connect' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) :
						$id           = (int) $row['id'];
						$submitted    = (string) $row['submitted_at'];
						$source       = (string) $row['source_button'];
						$name         = (string) $row['name'];
						$phone        = Submissions_Controller::format_phone( (string) $row['phone'] );
						$email        = (string) $row['email'];
						$message      = (string) $row['message'];
						$pref         = (string) ( $row['contact_pref'] ?? '' );
						$page_url     = (string) $row['page_url'];
						$status       = (string) $row['mail_status'];
						$user_agent   = (string) $row['user_agent'];

						$page_short   = $page_url;
						$parsed       = wp_parse_url( $page_url );
						if ( is_array( $parsed ) && isset( $parsed['path'] ) ) {
							$page_short = (string) $parsed['path'] . ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
						}
						?>
						<tr class="bspe-table__row" data-bspe-row-id="<?php echo esc_attr( (string) $id ); ?>">
							<td class="bspe-table__col-check">
								<input type="checkbox"
									name="ids[]"
									value="<?php echo esc_attr( (string) $id ); ?>"
									data-bspe-row-check
									aria-label="<?php
										echo esc_attr(
											sprintf(
												/* translators: 1: name (or id), 2: submitted_at */
												__( 'Select submission %1$s (submitted %2$s)', 'bspe-connect' ),
												'' !== $name ? $name : '#' . $id,
												$submitted
											)
										);
									?>"
								/>
							</td>
							<td class="bspe-table__col-toggle">
								<button type="button" class="bspe-table__expand" data-bspe-expand aria-expanded="false" aria-label="<?php esc_attr_e( 'Show details', 'bspe-connect' ); ?>">
									<svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4.5l3 3 3-3"/></svg>
								</button>
							</td>
							<td class="bspe-table__col-date">
								<time datetime="<?php echo esc_attr( gmdate( 'c', strtotime( $submitted ) ) ); ?>">
									<?php echo esc_html( mysql2date( 'M j, Y g:i a', $submitted, true ) ); ?>
								</time>
							</td>
							<td class="bspe-table__col-source">
								<span class="bspe-tag bspe-tag--source-<?php echo esc_attr( $source ); ?>"><?php echo esc_html( ucfirst( $source ) ); ?></span>
							</td>
							<td class="bspe-table__col-name"><?php echo esc_html( $name ); ?></td>
							<td class="bspe-table__col-phone"><?php echo esc_html( $phone ); ?></td>
							<td class="bspe-table__col-email">
								<?php if ( '' !== $email ) : ?>
									<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
								<?php endif; ?>
							</td>
							<td class="bspe-table__col-page">
								<?php if ( '' !== $page_url ) : ?>
									<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $page_url ); ?>">
										<?php echo esc_html( $page_short ); ?>
									</a>
								<?php endif; ?>
							</td>
							<td class="bspe-table__col-status">
								<span class="bspe-tag bspe-tag--status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
							</td>
						</tr>
						<tr class="bspe-table__detail" hidden>
							<td colspan="9">
								<div class="bspe-detail">
									<div class="bspe-detail__group">
										<span class="bspe-detail__label"><?php esc_html_e( 'Message', 'bspe-connect' ); ?></span>
										<p class="bspe-detail__value bspe-detail__value--message"><?php echo nl2br( esc_html( '' !== $message ? $message : '—' ) ); ?></p>
									</div>
									<?php if ( '' !== $pref ) : ?>
										<div class="bspe-detail__group">
											<span class="bspe-detail__label"><?php esc_html_e( 'Preferred contact', 'bspe-connect' ); ?></span>
											<p class="bspe-detail__value"><?php echo esc_html( ucfirst( $pref ) ); ?></p>
										</div>
									<?php endif; ?>
									<div class="bspe-detail__group">
										<span class="bspe-detail__label"><?php esc_html_e( 'User agent', 'bspe-connect' ); ?></span>
										<p class="bspe-detail__value bspe-detail__value--mono"><?php echo esc_html( $user_agent ); ?></p>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</form>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="bspe-pagination" aria-label="<?php esc_attr_e( 'Submissions pagination', 'bspe-connect' ); ?>">
				<?php
				$build_page_url = static function ( int $p ) use ( $filters ): string {
					return add_query_arg(
						array_merge(
							[
								'page'   => Admin::PAGE_SLUG,
								'tab'    => 'submissions',
								'paged'  => $p,
							],
							array_filter(
								[
									'from'   => $filters['from'],
									'to'     => $filters['to'],
									'source' => 'all' !== $filters['source'] ? $filters['source'] : '',
									'status' => 'all' !== $filters['status'] ? $filters['status'] : '',
								]
							)
						),
						admin_url( 'admin.php' )
					);
				};
				?>
				<a class="bspe-pagination__btn<?php echo $paged <= 1 ? ' is-disabled' : ''; ?>" href="<?php echo esc_url( $build_page_url( max( 1, $paged - 1 ) ) ); ?>" <?php echo $paged <= 1 ? 'aria-disabled="true"' : ''; ?>>
					<?php esc_html_e( 'Previous', 'bspe-connect' ); ?>
				</a>
				<span class="bspe-pagination__status">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'bspe-connect' ),
						(int) $paged,
						(int) $total_pages
					);
					?>
				</span>
				<a class="bspe-pagination__btn<?php echo $paged >= $total_pages ? ' is-disabled' : ''; ?>" href="<?php echo esc_url( $build_page_url( min( $total_pages, $paged + 1 ) ) ); ?>" <?php echo $paged >= $total_pages ? 'aria-disabled="true"' : ''; ?>>
					<?php esc_html_e( 'Next', 'bspe-connect' ); ?>
				</a>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</section>
