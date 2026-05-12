<?php
/**
 * License tab — activate / status / deactivate.
 *
 * @package BSPE\Connect\Admin
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Licensing;
use BSPE\Connect\Admin\License_Controller;

$state       = Licensing::state();
$action_url  = admin_url( 'admin-post.php' );
$flash       = License_Controller::consume_flash();

$status        = (string) $state['status'];
$key           = (string) $state['key'];
$domain        = (string) $state['domain'];
$last_check    = (int) $state['last_check_at'];
$last_success  = (int) $state['last_success_at'];
$last_error    = (string) $state['last_error'];
$in_grace      = Licensing::in_grace_period();
$is_functional = Licensing::is_functional();

$fmt_time = static function ( int $ts ): string {
	if ( $ts <= 0 ) {
		return __( 'Never', 'bspe-connect' );
	}
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
};

$mask_key = static function ( string $k ): string {
	// Show prefix + suffix, mask the middle. Helps visual ID without
	// fully exposing the key in screenshots.
	if ( strlen( $k ) < 12 ) {
		return $k;
	}
	return substr( $k, 0, 9 ) . '••••••••••' . substr( $k, -4 );
};
?>

<?php if ( null !== $flash ) :
	$flash_class = 'success' === $flash['kind']
		? 'bspe-notice--success'
		: ( 'warn' === $flash['kind'] ? 'bspe-notice--warn' : 'bspe-notice--error' );
	?>
	<div class="bspe-notice <?php echo esc_attr( $flash_class ); ?>" role="status">
		<?php echo esc_html( $flash['text'] ); ?>
	</div>
<?php endif; ?>

<?php /* ---------- STATE: UNACTIVATED ---------- */ ?>
<?php if ( 'unactivated' === $status || '' === $key ) : ?>
	<section class="bspe-card">
		<header class="bspe-card__head">
			<div class="bspe-card__head-text">
				<h2><?php esc_html_e( 'Activate BSPE Connect', 'bspe-connect' ); ?></h2>
				<p class="bspe-card__lead">
					<?php esc_html_e( 'BSPE Connect is dormant until a valid license key is activated. Enter the key sent by BSPE Legal Marketing and click Activate.', 'bspe-connect' ); ?>
				</p>
			</div>
		</header>

		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<?php wp_nonce_field( License_Controller::ACTIVATE_NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( License_Controller::ACTIVATE_ACTION ); ?>" />

			<div class="bspe-row" style="flex-direction: column; align-items: stretch; gap: 8px;">
				<label for="bspe-license-key" style="font-weight: 500;">
					<?php esc_html_e( 'License key', 'bspe-connect' ); ?>
				</label>
				<input type="text"
					id="bspe-license-key"
					name="license_key"
					class="bspe-input"
					autocomplete="off"
					spellcheck="false"
					placeholder="bspe-XXXX-XXXX-XXXX-XXXX"
					required
					style="font-family: 'SFMono-Regular', Menlo, Consolas, monospace; max-width: 360px;"
				/>
				<p class="bspe-row__description" style="margin-top: 0;">
					<?php esc_html_e( 'Once activated, this key is bound to', 'bspe-connect' ); ?>
					<code><?php echo esc_html( Licensing::current_domain() ); ?></code>.
					<?php esc_html_e( 'Subdomains (staging, www, etc.) of the same site are covered by the same key.', 'bspe-connect' ); ?>
				</p>
			</div>

			<div class="bspe-row" style="margin-top: 16px;">
				<button type="submit" class="bspe-button bspe-button--primary">
					<?php esc_html_e( 'Activate license', 'bspe-connect' ); ?>
				</button>
			</div>
		</form>
	</section>

<?php /* ---------- STATE: REVOKED ---------- */ ?>
<?php elseif ( 'revoked' === $status ) : ?>
	<section class="bspe-card bspe-card--danger">
		<header class="bspe-card__head">
			<div class="bspe-card__head-text">
				<h2><?php esc_html_e( 'License revoked', 'bspe-connect' ); ?></h2>
				<p class="bspe-card__lead">
					<?php esc_html_e( 'This license has been deactivated by BSPE Legal Marketing. BSPE Connect is disabled. Please contact BSPE for a new key or to discuss reactivation.', 'bspe-connect' ); ?>
				</p>
			</div>
		</header>

		<div style="padding: 0 24px 22px;">
			<dl class="bspe-kv">
				<dt><?php esc_html_e( 'Key', 'bspe-connect' ); ?></dt>
				<dd class="bspe-mono"><?php echo esc_html( $mask_key( $key ) ); ?></dd>
				<dt><?php esc_html_e( 'Domain', 'bspe-connect' ); ?></dt>
				<dd class="bspe-mono"><?php echo esc_html( $domain ); ?></dd>
				<dt><?php esc_html_e( 'Revoked on', 'bspe-connect' ); ?></dt>
				<dd><?php echo esc_html( $fmt_time( $last_check ) ); ?></dd>
			</dl>

			<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-top: 18px;">
				<?php wp_nonce_field( License_Controller::DEACTIVATE_NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( License_Controller::DEACTIVATE_ACTION ); ?>" />
				<button type="submit" class="bspe-button">
					<?php esc_html_e( 'Clear key and enter a new one', 'bspe-connect' ); ?>
				</button>
			</form>
		</div>
	</section>

<?php /* ---------- STATE: DOMAIN MISMATCH ---------- */ ?>
<?php elseif ( 'domain_mismatch' === $status ) : ?>
	<section class="bspe-card bspe-card--danger">
		<header class="bspe-card__head">
			<div class="bspe-card__head-text">
				<h2><?php esc_html_e( 'Domain mismatch', 'bspe-connect' ); ?></h2>
				<p class="bspe-card__lead">
					<?php esc_html_e( 'This license key is registered to a different domain. BSPE Connect is disabled. If the site was migrated, contact BSPE Legal Marketing to transfer the key to', 'bspe-connect' ); ?>
					<code><?php echo esc_html( Licensing::current_domain() ); ?></code>.
				</p>
			</div>
		</header>

		<div style="padding: 0 24px 22px;">
			<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-top: 8px;">
				<?php wp_nonce_field( License_Controller::DEACTIVATE_NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( License_Controller::DEACTIVATE_ACTION ); ?>" />
				<button type="submit" class="bspe-button">
					<?php esc_html_e( 'Clear key and enter a new one', 'bspe-connect' ); ?>
				</button>
			</form>
		</div>
	</section>

<?php /* ---------- STATE: ACTIVE (functional OR grace-expired) ---------- */ ?>
<?php else : ?>

	<?php if ( ! $is_functional ) : /* grace expired */ ?>
		<div class="bspe-notice bspe-notice--error" role="alert">
			<strong><?php esc_html_e( 'License check failed for too long.', 'bspe-connect' ); ?></strong>
			<?php esc_html_e( 'BSPE Connect has been disabled until the license server can be reached again. Click "Check now" to retry, or contact BSPE Legal Marketing.', 'bspe-connect' ); ?>
		</div>
	<?php elseif ( $in_grace ) : ?>
		<div class="bspe-notice bspe-notice--warn" role="status">
			<strong><?php esc_html_e( 'Could not reach the license server.', 'bspe-connect' ); ?></strong>
			<?php
			printf(
				esc_html__( 'The last successful check was %s. The plugin keeps working for up to 7 days on cached state — try "Check now" to retry.', 'bspe-connect' ),
				esc_html( $fmt_time( $last_success ) )
			);
			?>
		</div>
	<?php endif; ?>

	<section class="bspe-card">
		<header class="bspe-card__head">
			<div class="bspe-card__head-text">
				<h2>
					<?php esc_html_e( 'License', 'bspe-connect' ); ?>
					<?php if ( $is_functional ) : ?>
						<span class="bspe-tag bspe-tag--status-sent" style="margin-left: 10px;"><?php esc_html_e( 'Active', 'bspe-connect' ); ?></span>
					<?php else : ?>
						<span class="bspe-tag bspe-tag--status-failed" style="margin-left: 10px;"><?php esc_html_e( 'Inactive', 'bspe-connect' ); ?></span>
					<?php endif; ?>
				</h2>
				<p class="bspe-card__lead">
					<?php esc_html_e( 'Status of this install\'s license with BSPE Legal Marketing.', 'bspe-connect' ); ?>
				</p>
			</div>
		</header>

		<div style="padding: 0 24px 22px;">
			<dl class="bspe-kv">
				<dt><?php esc_html_e( 'Key', 'bspe-connect' ); ?></dt>
				<dd class="bspe-mono"><?php echo esc_html( $mask_key( $key ) ); ?></dd>

				<dt><?php esc_html_e( 'Bound to domain', 'bspe-connect' ); ?></dt>
				<dd class="bspe-mono"><?php echo esc_html( $domain ); ?></dd>

				<dt><?php esc_html_e( 'Last successful check', 'bspe-connect' ); ?></dt>
				<dd><?php echo esc_html( $fmt_time( $last_success ) ); ?></dd>

				<dt><?php esc_html_e( 'Last check attempt', 'bspe-connect' ); ?></dt>
				<dd><?php echo esc_html( $fmt_time( $last_check ) ); ?></dd>

				<?php if ( '' !== $last_error ) : ?>
					<dt><?php esc_html_e( 'Last error', 'bspe-connect' ); ?></dt>
					<dd class="bspe-mono"><?php echo esc_html( $last_error ); ?></dd>
				<?php endif; ?>
			</dl>

			<div class="bspe-row" style="margin-top: 18px; gap: 10px;">
				<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin: 0;">
					<?php wp_nonce_field( License_Controller::CHECK_NONCE ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( License_Controller::CHECK_ACTION ); ?>" />
					<button type="submit" class="bspe-button">
						<?php esc_html_e( 'Check now', 'bspe-connect' ); ?>
					</button>
				</form>
			</div>
		</div>

		<div class="bspe-danger-zone" style="margin: 0 24px 22px;">
			<h3 style="margin: 0 0 8px;"><?php esc_html_e( 'Deactivate this install', 'bspe-connect' ); ?></h3>
			<p class="bspe-row__description" style="margin: 0 0 12px;">
				<?php esc_html_e( 'Releases the domain binding so the same key can be used on a different install. Use this only when migrating to a new domain or stopping use of the plugin on this site.', 'bspe-connect' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Deactivate this license? BSPE Connect will stop working until a license is re-entered.', 'bspe-connect' ) ); ?>');">
				<?php wp_nonce_field( License_Controller::DEACTIVATE_NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( License_Controller::DEACTIVATE_ACTION ); ?>" />
				<button type="submit" class="bspe-button bspe-button--danger">
					<?php esc_html_e( 'Deactivate license', 'bspe-connect' ); ?>
				</button>
			</form>
		</div>
	</section>

<?php endif; ?>
