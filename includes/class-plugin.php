<?php
/**
 * Plugin bootstrap.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton. Boots admin and updater. Phases 2-5 will register
 * additional services (Frontend, FormHandler, Analytics, Rest) here.
 */
final class Plugin {

	public const CRON_PRUNE_EVENTS      = 'bspe_connect_prune_events';
	public const CRON_PRUNE_SUBMISSIONS = 'bspe_connect_prune_submissions';

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'bspe-connect', false, dirname( BSPE_CONNECT_BASENAME ) . '/languages' );

		// Run schema migrations whenever the stored DB version doesn't match
		// the constant. Catches PUC-driven updates where the activation hook
		// never fires — without this, new tables / columns added in later
		// versions silently never get created on the live site, and INSERTs
		// fail with "Table 'wp_bspe_connect_events' doesn't exist".
		$this->maybe_migrate();

		// License gate. Must register BEFORE the runtime services so its
		// cron + admin-post hooks attach, but the actual is_functional()
		// check happens at each service's init() call below — admins can
		// still reach the wp-admin UI when unactivated to enter a key.
		Licensing::init();

		$licensed = Licensing::is_functional();

		// Updater gates on license — no updates flow to unlicensed
		// installs (a revoked client doesn't get newer versions).
		if ( $licensed ) {
			Updater::init();
		}

		// Site utilities run independently of the license gate. The
		// QR indexer + external-link rewriter + REST users hide are
		// general site enhancements that BSPE wants live on every
		// install whether or not the contact bar is licensed.
		Hide_Users_Rest::init();
		External_Links::init();
		QR_Indexer::init();

		// Daily prune of analytics events older than retention window.
		// We register the cron on activation (Activator::activate) but
		// also self-schedule here in case the activation hook never ran
		// for an install that came up on an earlier version.
		add_action( self::CRON_PRUNE_EVENTS, [ self::class, 'cron_prune_events' ] );
		if ( ! wp_next_scheduled( self::CRON_PRUNE_EVENTS ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PRUNE_EVENTS );
		}

		// Daily prune of form submissions older than form.retention_days.
		// The callback is a no-op when retention_days is 0 (default), so
		// existing installs that never touch the setting see no behavior
		// change.
		add_action( self::CRON_PRUNE_SUBMISSIONS, [ self::class, 'cron_prune_submissions' ] );
		if ( ! wp_next_scheduled( self::CRON_PRUNE_SUBMISSIONS ) ) {
			// Stagger from the events cron by ~30 min so they don't both
			// fire in the same minute on a busy site.
			wp_schedule_event( time() + HOUR_IN_SECONDS + ( 30 * MINUTE_IN_SECONDS ), 'daily', self::CRON_PRUNE_SUBMISSIONS );
		}

		// Diagnostics logger — needs the admin-post hook for "Clear logs"
		// even when logging is disabled (the button stays available so old
		// entries can be wiped).
		Logger::init();

		// Form submission handler — gated on license. Without a valid
		// license the form modal can't post to the server, so even if
		// someone tampers with the JS to render the bar manually, no
		// submissions accumulate.
		if ( $licensed ) {
			Form_Handler::init();
		}

		// REST API for analytics events — same gating logic.
		if ( $licensed ) {
			Rest::init();
		}

		if ( is_admin() ) {
			Admin\Admin::init();
		} elseif ( $licensed ) {
			Frontend::init();
		}
	}

	/**
	 * Compare stored DB version with the current constant and run dbDelta
	 * if they differ. Logs the run so the Logs tab makes the upgrade visible
	 * (helpful when diagnosing INSERT-returning-0 issues post-upgrade).
	 */
	private function maybe_migrate(): void {
		$stored = (string) get_option( Settings::DB_VERSION_KEY, '' );
		if ( $stored === Settings::DB_VERSION ) {
			return;
		}

		Activator::create_tables();
		update_option( Settings::DB_VERSION_KEY, Settings::DB_VERSION, false );

		Logger::log( 'info', 'Schema migration ran (dbDelta)', [
			'from' => '' === $stored ? '(unset)' : $stored,
			'to'   => Settings::DB_VERSION,
		] );
	}

	/**
	 * Daily cron callback — prunes old analytics events. Logs only when
	 * something was actually deleted to keep the Logs tab tidy.
	 */
	public static function cron_prune_events(): void {
		$deleted = Events::prune_old();
		if ( $deleted > 0 ) {
			Logger::log( 'info', 'Pruned old analytics events', [
				'deleted'        => $deleted,
				'retention_days' => Events::RETENTION_DAYS,
			] );
		}
	}

	/**
	 * Daily cron callback — prunes old form submissions when the admin has
	 * set a positive retention window. Logs only on actual deletions so the
	 * Logs tab doesn't fill with daily no-op entries on default installs.
	 */
	public static function cron_prune_submissions(): void {
		$days = (int) Settings::get( 'form.retention_days', 0 );
		if ( $days <= 0 ) {
			return;
		}
		$deleted = Submissions::prune_old( $days );
		if ( $deleted > 0 ) {
			Logger::log( 'info', 'Pruned old form submissions', [
				'deleted'        => $deleted,
				'retention_days' => $days,
			] );
		}
	}
}
