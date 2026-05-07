<?php
/**
 * Analytics dashboard — conversion funnel, per-event counts, top pages.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Admin\Admin;
use BSPE\Connect\Admin\Analytics_Controller;
use BSPE\Connect\Events;

$window  = Analytics_Controller::active_window();
$summary = Analytics_Controller::summary( $window );

$counts               = $summary['counts'];
$funnel               = $summary['funnel'];
$top_submission_pages = $summary['top_submission_pages'];
$top_bar_pages        = $summary['top_bar_pages'];

$max_funnel_count = 0;
foreach ( $funnel as $stage ) {
	if ( $stage['count'] > $max_funnel_count ) {
		$max_funnel_count = $stage['count'];
	}
}

$event_labels = [
	'bar_shown'        => __( 'Bar shown', 'bspe-connect' ),
	'bubble_shown'     => __( 'Bubble shown', 'bspe-connect' ),
	'bubble_dismissed' => __( 'Bubble dismissed', 'bspe-connect' ),
	'connect_click'    => __( 'Connect click', 'bspe-connect' ),
	'call_click'       => __( 'Call click', 'bspe-connect' ),
	'text_click'       => __( 'Text click', 'bspe-connect' ),
	'email_click'      => __( 'Email click', 'bspe-connect' ),
	'form_open'        => __( 'Form opened', 'bspe-connect' ),
	'form_submit'      => __( 'Form submitted', 'bspe-connect' ),
	'form_success'     => __( 'Form success', 'bspe-connect' ),
	'form_error'       => __( 'Form error', 'bspe-connect' ),
];

$event_groups = [
	__( 'Visibility', 'bspe-connect' ) => [ 'bar_shown', 'bubble_shown', 'bubble_dismissed' ],
	__( 'Buttons',    'bspe-connect' ) => [ 'connect_click', 'call_click', 'text_click', 'email_click' ],
	__( 'Form',       'bspe-connect' ) => [ 'form_open', 'form_submit', 'form_success', 'form_error' ],
];

$total_events = array_sum( $counts );

$base_url = Admin::tab_url( 'analytics' );
$tested   = isset( $_GET['tested'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tested'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only feedback
?>

<?php if ( '1' === $tested ) : ?>
	<div class="bspe-notice" role="status">
		<span class="bspe-notice__icon" aria-hidden="true">
			<svg viewBox="0 0 14 14" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 7.5l3 3 6-7"/></svg>
		</span>
		<?php esc_html_e( 'Test event inserted. The funnel and tiles below should reflect it on the next page load.', 'bspe-connect' ); ?>
	</div>
<?php elseif ( '0' === $tested ) : ?>
	<div class="bspe-notice" role="status" style="background: rgba(192,57,43,.1); border-color: rgba(192,57,43,.28); color: #c0392b;">
		<?php esc_html_e( 'Test event INSERT returned 0 — the DB write failed. Check the Logs tab.', 'bspe-connect' ); ?>
	</div>
<?php endif; ?>

<section class="bspe-card bspe-card--analytics">
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Analytics', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php
				printf(
					/* translators: 1: time window in days, 2: total events */
					esc_html( _n( 'Last %1$d day · %2$s events recorded.', 'Last %1$d days · %2$s events recorded.', (int) $window, 'bspe-connect' ) ),
					(int) $window,
					esc_html( number_format_i18n( $total_events ) )
				);
				?>
			</p>
		</div>
		<div style="display: inline-flex; gap: 12px; align-items: center;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
				<input type="hidden" name="action" value="<?php echo esc_attr( Analytics_Controller::TEST_ACTION ); ?>" />
				<?php wp_nonce_field( Analytics_Controller::TEST_NONCE ); ?>
				<button type="submit" class="bspe-button bspe-button--ghost" title="<?php esc_attr_e( 'Insert a single bar_shown event directly via the admin — bypasses the public JS / REST round-trip so we can isolate where breakages happen', 'bspe-connect' ); ?>">
					<?php esc_html_e( 'Insert test event', 'bspe-connect' ); ?>
				</button>
			</form>
			<nav class="bspe-window-picker" aria-label="<?php esc_attr_e( 'Time window', 'bspe-connect' ); ?>">
				<?php foreach ( Analytics_Controller::ALLOWED_WINDOWS as $w ) :
					$is_active = $w === $window;
					$href      = add_query_arg(
						[ 'page' => Admin::PAGE_SLUG, 'tab' => 'analytics', 'window' => $w ],
						admin_url( 'admin.php' )
					);
					?>
					<a class="bspe-window-picker__btn<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $href ); ?>" aria-current="<?php echo $is_active ? 'true' : 'false'; ?>">
						<?php
						/* translators: %d: window length in days */
						printf( esc_html__( '%dd', 'bspe-connect' ), (int) $w );
						?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
	</header>
</section>

<section class="bspe-card bspe-card--funnel">
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Conversion funnel', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php esc_html_e( 'Distinct visitors who reached each stage. Drop-off shown between stages.', 'bspe-connect' ); ?>
			</p>
		</div>
	</header>

	<?php if ( 0 === $max_funnel_count ) : ?>
		<div class="bspe-empty">
			<svg viewBox="0 0 48 48" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M6 8h36L30 26v12l-12 4V26z"/>
			</svg>
			<p class="bspe-empty__title"><?php esc_html_e( 'No events in this window yet.', 'bspe-connect' ); ?></p>
			<p class="bspe-empty__hint"><?php esc_html_e( 'Once visitors interact with the bar, the funnel fills in.', 'bspe-connect' ); ?></p>
		</div>
	<?php else : ?>
		<ol class="bspe-funnel">
			<?php foreach ( $funnel as $i => $stage ) :
				$count_pct = $max_funnel_count > 0 ? ( $stage['count'] / $max_funnel_count ) * 100 : 0;
				$drop_pct  = null !== $stage['drop'] ? round( $stage['drop'] * 100 ) : null;
				?>
				<li class="bspe-funnel__stage" data-stage="<?php echo esc_attr( $stage['key'] ); ?>">
					<?php if ( $i > 0 ) : ?>
						<span class="bspe-funnel__connector" aria-hidden="true">
							<svg viewBox="0 0 16 32" width="16" height="32" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4l8 12-8 12"/></svg>
							<?php if ( null !== $drop_pct ) : ?>
								<span class="bspe-funnel__drop">
									<?php
									/* translators: %d: percentage of previous stage */
									printf( esc_html__( '%d%%', 'bspe-connect' ), (int) $drop_pct );
									?>
								</span>
							<?php endif; ?>
						</span>
					<?php endif; ?>
					<div class="bspe-funnel__card">
						<span class="bspe-funnel__label"><?php echo esc_html( $stage['label'] ); ?></span>
						<span class="bspe-funnel__count"><?php echo esc_html( number_format_i18n( $stage['count'] ) ); ?></span>
						<span class="bspe-funnel__bar" aria-hidden="true">
							<span class="bspe-funnel__bar-fill" style="width: <?php echo esc_attr( max( 4, (string) $count_pct ) ); ?>%;"></span>
						</span>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</section>

<section class="bspe-card bspe-card--bento">
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Per-event counts', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php esc_html_e( 'Total occurrences in the selected window, grouped by area of the bar.', 'bspe-connect' ); ?>
			</p>
		</div>
	</header>

	<div class="bspe-bento">
		<?php foreach ( $event_groups as $group_label => $event_keys ) : ?>
			<div class="bspe-bento__group">
				<h3 class="bspe-bento__title"><?php echo esc_html( $group_label ); ?></h3>
				<div class="bspe-bento__grid">
					<?php foreach ( $event_keys as $type ) :
						$count = (int) ( $counts[ $type ] ?? 0 );
						$label = $event_labels[ $type ] ?? $type;
						?>
						<div class="bspe-tile" data-event="<?php echo esc_attr( $type ); ?>">
							<span class="bspe-tile__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<span class="bspe-tile__label"><?php echo esc_html( $label ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<div class="bspe-pages-grid">
	<section class="bspe-card bspe-card--pages">
		<header class="bspe-card__head">
			<div class="bspe-card__head-text">
				<h2><?php esc_html_e( 'Top pages by submissions', 'bspe-connect' ); ?></h2>
				<p class="bspe-card__lead">
					<?php esc_html_e( 'Where successful form submissions came from.', 'bspe-connect' ); ?>
				</p>
			</div>
		</header>

		<?php if ( empty( $top_submission_pages ) ) : ?>
			<p class="bspe-pages-empty"><?php esc_html_e( 'No submissions in this window yet.', 'bspe-connect' ); ?></p>
		<?php else : ?>
			<ol class="bspe-pages">
				<?php foreach ( $top_submission_pages as $i => $row ) :
					$path = Analytics_Controller::format_path( (string) $row['page_url'] );
					?>
					<li class="bspe-pages__item">
						<span class="bspe-pages__rank"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
						<a class="bspe-pages__link" href="<?php echo esc_url( (string) $row['page_url'] ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $path ); ?>
						</a>
						<span class="bspe-pages__count"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>

	<section class="bspe-card bspe-card--pages">
		<header class="bspe-card__head">
			<div class="bspe-card__head-text">
				<h2><?php esc_html_e( 'Top pages by bar impressions', 'bspe-connect' ); ?></h2>
				<p class="bspe-card__lead">
					<?php esc_html_e( 'Where the bar slid into view most often.', 'bspe-connect' ); ?>
				</p>
			</div>
		</header>

		<?php if ( empty( $top_bar_pages ) ) : ?>
			<p class="bspe-pages-empty"><?php esc_html_e( 'No impressions yet.', 'bspe-connect' ); ?></p>
		<?php else : ?>
			<ol class="bspe-pages">
				<?php foreach ( $top_bar_pages as $i => $row ) :
					$path = Analytics_Controller::format_path( (string) $row['page_url'] );
					?>
					<li class="bspe-pages__item">
						<span class="bspe-pages__rank"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
						<a class="bspe-pages__link" href="<?php echo esc_url( (string) $row['page_url'] ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $path ); ?>
						</a>
						<span class="bspe-pages__count"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
</div>
