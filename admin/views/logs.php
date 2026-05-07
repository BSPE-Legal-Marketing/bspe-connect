<?php
/**
 * Logs tab — diagnostics toggle + recent log entries.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Logger;
use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;
use BSPE\Connect\Admin\Settings_Saver;

$logging_enabled = (bool) Settings::get( 'diagnostics.logging_enabled', false );
$entries         = Logger::all();
$entries         = array_reverse( $entries ); // newest first
$cleared         = isset( $_GET['cleared'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['cleared'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( $cleared ) :
	?>
	<div class="bspe-notice" role="status">
		<span class="bspe-notice__icon" aria-hidden="true">
			<svg viewBox="0 0 14 14" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 7.5l3 3 6-7"/></svg>
		</span>
		<?php esc_html_e( 'Logs cleared.', 'bspe-connect' ); ?>
	</div>
	<?php
endif;

// Toggle form (saves to diagnostics.logging_enabled via the standard
// settings-saver under tab=diagnostics — handled by the existing
// Settings_Saver dispatcher below).
$action_url = admin_url( 'admin-post.php' );
?>

<section class="bspe-card bspe-card--settings">
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Diagnostics logging', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php esc_html_e( 'Captures every form submission attempt, mail dispatch result, anti-spam event, and validation failure into a 200-entry ring buffer in wp_options. Toggle off to stop new writes — existing entries stay until you clear them.', 'bspe-connect' ); ?>
			</p>
		</div>
		<span class="bspe-pill bspe-pill--phase">
			<?php echo $logging_enabled ? esc_html__( 'Recording', 'bspe-connect' ) : esc_html__( 'Off', 'bspe-connect' ); ?>
		</span>
	</header>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="bspe-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Saver::ACTION ); ?>" />
		<input type="hidden" name="_tab" value="logs" />
		<?php wp_nonce_field( Settings_Saver::NONCE_ACTION ); ?>

		<?php
		Components::row(
			__( 'Enable logging', 'bspe-connect' ),
			static function () use ( $logging_enabled ): void {
				Components::toggle( 'bspe[diagnostics][logging_enabled]', $logging_enabled, [
					'label' => __( 'Record events to the buffer below', 'bspe-connect' ),
				] );
			},
			[ 'description' => __( 'Off by default — turn on when you need to debug, then turn off so the option table doesn\'t bloat over time.', 'bspe-connect' ) ]
		);
		?>

		<div class="bspe-form__actions">
			<button type="submit" class="bspe-button bspe-button--primary" data-bspe-save-button>
				<?php esc_html_e( 'Save changes', 'bspe-connect' ); ?>
			</button>
		</div>
	</form>
</section>

<section class="bspe-card bspe-card--logs">
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Recent events', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php
				/* translators: %d: number of stored log entries */
				printf(
					esc_html( _n( '%d entry stored. Newest first.', '%d entries stored. Newest first.', count( $entries ), 'bspe-connect' ) ),
					(int) count( $entries )
				);
				?>
			</p>
		</div>
		<?php if ( ! empty( $entries ) ) : ?>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin: 0;">
				<input type="hidden" name="action" value="<?php echo esc_attr( Logger::CLEAR_ACTION ); ?>" />
				<?php wp_nonce_field( Logger::CLEAR_NONCE ); ?>
				<button type="submit" class="bspe-button bspe-button--ghost" onclick="return confirm('<?php echo esc_js( __( 'Clear all log entries? This cannot be undone.', 'bspe-connect' ) ); ?>');">
					<?php esc_html_e( 'Clear logs', 'bspe-connect' ); ?>
				</button>
			</form>
		<?php endif; ?>
	</header>

	<?php if ( empty( $entries ) ) : ?>
		<div class="bspe-empty">
			<svg viewBox="0 0 48 48" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M14 6h14l8 8v26a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/>
				<path d="M28 6v8h8"/>
				<path d="M18 24h12M18 30h10M18 36h6"/>
			</svg>
			<p class="bspe-empty__title">
				<?php
				echo $logging_enabled
					? esc_html__( 'Logging is on, but nothing has been recorded yet.', 'bspe-connect' )
					: esc_html__( 'No log entries.', 'bspe-connect' );
				?>
			</p>
			<p class="bspe-empty__hint">
				<?php
				echo $logging_enabled
					? esc_html__( 'Submit a test form on mobile to see the pipeline play out below.', 'bspe-connect' )
					: esc_html__( 'Toggle "Enable logging" on, save, then submit a test form to capture what\'s happening.', 'bspe-connect' );
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="bspe-table-wrap">
			<table class="bspe-table">
				<thead>
					<tr>
						<th class="bspe-table__col-toggle" aria-label="<?php esc_attr_e( 'Expand entry', 'bspe-connect' ); ?>"></th>
						<th class="bspe-table__col-date"><?php esc_html_e( 'When', 'bspe-connect' ); ?></th>
						<th class="bspe-table__col-source"><?php esc_html_e( 'Level', 'bspe-connect' ); ?></th>
						<th><?php esc_html_e( 'Message', 'bspe-connect' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) :
						$time    = (string) ( $entry['time']    ?? '' );
						$level   = (string) ( $entry['level']   ?? 'info' );
						$message = (string) ( $entry['message'] ?? '' );
						$context = is_array( $entry['context'] ?? null ) ? $entry['context'] : [];
						?>
						<tr class="bspe-table__row">
							<td class="bspe-table__col-toggle">
								<?php if ( ! empty( $context ) ) : ?>
									<button type="button" class="bspe-table__expand" data-bspe-expand aria-expanded="false" aria-label="<?php esc_attr_e( 'Show context', 'bspe-connect' ); ?>">
										<svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4.5l3 3 3-3"/></svg>
									</button>
								<?php endif; ?>
							</td>
							<td class="bspe-table__col-date">
								<time datetime="<?php echo esc_attr( '' !== $time ? gmdate( 'c', strtotime( $time ) ) : '' ); ?>">
									<?php echo esc_html( '' !== $time ? mysql2date( 'M j, Y g:i:s a', $time, true ) : '—' ); ?>
								</time>
							</td>
							<td class="bspe-table__col-source">
								<span class="bspe-tag bspe-tag--log-<?php echo esc_attr( $level ); ?>">
									<?php echo esc_html( ucfirst( $level ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $message ); ?></td>
						</tr>
						<?php if ( ! empty( $context ) ) : ?>
							<tr class="bspe-table__detail" hidden>
								<td colspan="4">
									<div class="bspe-detail">
										<div class="bspe-detail__group">
											<span class="bspe-detail__label"><?php esc_html_e( 'Context', 'bspe-connect' ); ?></span>
											<pre class="bspe-detail__value bspe-detail__value--mono" style="white-space: pre-wrap; max-width: 100%; overflow-x: auto;"><?php echo esc_html( wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
										</div>
									</div>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
