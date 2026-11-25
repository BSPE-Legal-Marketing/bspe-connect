<?php
/**
 * Management REST API: lead form + settings, for wp-admin and BSPE Fleet Control.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * One set of handlers, mounted twice:
 *
 *   /wp-json/bspe-connect/v1/manage/…   permission: manage_options
 *       (a logged-in admin with the wp_rest nonce — the admin builder uses
 *       this — or an Application Password).
 *
 *   /wp-json/bspe-agent/v1/bspe-connect/…   permission: BSPE_Auth::verify_request
 *       Mounted only when the BSPE Connector (Fleet Control) is active, inside
 *       the connector's own namespace (BSPE_REST_NAMESPACE, default
 *       bspe-agent/v1). Its HMAC check is route-generic (timestamp + method +
 *       route + body), so the Fleet controller's
 *       connectorRequest(site, 'GET', '/bspe-connect/form') reaches these
 *       handlers with the site's existing secret and no change to the connector. Errors use `bspe_connect_*` codes with data.version,
 *       which the controller's reconciliation counts as "handler ran".
 *
 * Routes (relative):
 *   GET  /status
 *   GET  /form
 *   POST /form                 { mode?, custom?, copy?, webhook?, mail?, if_revision?, dry_run?, actor?, note? }
 *   GET  /form/elementor
 *   POST /form/import-elementor { post_id, form_id?, apply?, activate?, actor?, note? }
 *   GET  /form/revisions
 *   POST /form/restore         { revision_id, scope?: layout|all, dry_run?, actor? }
 *   POST /form/test-webhook    {}
 *   GET  /settings
 *   POST /settings             { patch: { <section>: {…} }, dry_run?, actor?, note? }
 *
 * Every write that changes the form section first snapshots the previous
 * section into a revision (last 25 kept), so any change can be rolled back
 * with /form/restore.
 */
final class Form_Api {

	public const ADMIN_NAMESPACE = 'bspe-connect/v1';
	public const ADMIN_PREFIX    = '/manage';
	/** Fallback only — the connector's namespace is read from BSPE_REST_NAMESPACE. */
	public const FLEET_NAMESPACE = 'bspe-agent/v1';
	public const FLEET_PREFIX    = '/bspe-connect';

	public const REVISIONS_OPTION = 'bspe_connect_form_revisions';
	public const REVISIONS_KEEP   = 25;

	/** What GET /settings shows in place of a stored secret. */
	public const REDACTED = '__redacted__';

	/** Sections POST /settings may patch, and the copy keys POST /form accepts. */
	private const PATCHABLE_SECTIONS = [ 'enabled', 'welcome_bubble', 'display', 'utilities', 'buttons', 'form', 'design', 'display_rules', 'diagnostics', 'in_post_widget', 'chat', 'privacy' ];

	private const COPY_KEYS = [ 'text_heading', 'email_heading', 'text_subheading', 'email_subheading', 'submit_label', 'success_msg' ];

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		self::mount( self::ADMIN_NAMESPACE, self::ADMIN_PREFIX, [ self::class, 'can_manage' ] );

		if ( class_exists( 'BSPE_Auth' ) && method_exists( 'BSPE_Auth', 'verify_request' ) ) {
			self::mount( self::fleet_namespace(), self::FLEET_PREFIX, [ self::class, 'fleet_permission' ] );
		}
	}

	/**
	 * The BSPE Connector's REST namespace — the one the Fleet controller
	 * signs and calls (connector.ts NAMESPACE).
	 */
	public static function fleet_namespace(): string {
		$ns = ( defined( 'BSPE_REST_NAMESPACE' ) && is_string( BSPE_REST_NAMESPACE ) && '' !== BSPE_REST_NAMESPACE ) ? BSPE_REST_NAMESPACE : self::FLEET_NAMESPACE;
		return trim( $ns, '/' );
	}

	/**
	 * @param callable $permission
	 */
	private static function mount( string $namespace, string $prefix, $permission ): void {
		$routes = [
			'/status'                => [ 'GET', 'route_status' ],
			'/form'                  => [ [ 'GET', 'route_get_form' ], [ 'POST', 'route_update_form' ] ],
			'/form/elementor'        => [ 'GET', 'route_list_elementor' ],
			'/form/import-elementor' => [ 'POST', 'route_import_elementor' ],
			'/form/revisions'        => [ 'GET', 'route_list_revisions' ],
			'/form/restore'          => [ 'POST', 'route_restore' ],
			'/form/test-webhook'     => [ 'POST', 'route_test_webhook' ],
			'/settings'              => [ [ 'GET', 'route_get_settings' ], [ 'POST', 'route_patch_settings' ] ],
		];

		foreach ( $routes as $path => $spec ) {
			$endpoints = is_array( $spec[0] ) ? $spec : [ $spec ];
			$args      = [];
			foreach ( $endpoints as [ $method, $callback ] ) {
				$args[] = [
					'methods'             => $method,
					'callback'            => [ self::class, $callback ],
					'permission_callback' => $permission,
				];
			}
			register_rest_route( $namespace, $prefix . $path, $args );
		}
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return true|\WP_Error
	 */
	public static function fleet_permission( $request ) {
		return \BSPE_Auth::verify_request( $request );
	}

	/* -----------------------------------------------------------------
	 * Routes
	 * ----------------------------------------------------------------- */

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_status( $request ) {
		unset( $request );
		$form   = self::form_section();
		$custom = Custom_Form::config();
		$hook   = is_array( $form['webhook'] ?? null ) ? $form['webhook'] : [];

		return self::ok( [
			'plugin_version'  => BSPE_CONNECT_VERSION,
			'licensed'        => Licensing::is_functional(),
			'bar_enabled'     => (bool) Settings::get( 'enabled', false ),
			'form_mode'       => Custom_Form::mode(),
			'custom_active'   => Custom_Form::is_active(),
			'custom_steps'    => count( $custom['steps'] ),
			'custom_fields'   => count( Custom_Form::all_fields( $custom ) ),
			'webhook_enabled' => ! empty( $hook['enabled'] ),
			'webhook_host'    => (string) wp_parse_url( (string) ( $hook['url'] ?? '' ), PHP_URL_HOST ),
			'mail_to'         => (string) ( $form['mail_to'] ?? '' ),
			'revision'        => self::revision_hash( $form ),
			'fleet_routes'    => class_exists( 'BSPE_Auth' ),
		] );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_get_form( $request ) {
		unset( $request );
		return self::ok( self::form_view() );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_update_form( $request ) {
		$body     = self::body( $request );
		$dry_run  = self::flag( $body['dry_run'] ?? false );
		$current  = self::form_section();

		$conflict = self::check_revision( $body, $current );
		if ( $conflict ) {
			return $conflict;
		}

		$next     = $current;
		$warnings = [];

		if ( array_key_exists( 'custom', $body ) ) {
			$result = Custom_Form::normalize( $body['custom'] );
			if ( ! empty( $result['errors'] ) ) {
				return self::error( 'bspe_connect_invalid_form', 'The custom form was refused. Nothing was saved.', 400, [ 'errors' => $result['errors'], 'warnings' => $result['warnings'] ] );
			}
			$next['custom'] = $result['config'];
			$warnings       = $result['warnings'];
		}

		if ( array_key_exists( 'mode', $body ) ) {
			$mode = (string) $body['mode'];
			if ( ! in_array( $mode, [ 'standard', 'custom' ], true ) ) {
				return self::error( 'bspe_connect_invalid_mode', 'mode must be "standard" or "custom".', 400 );
			}
			$next['mode'] = $mode;
		}

		if ( isset( $body['copy'] ) && is_array( $body['copy'] ) ) {
			foreach ( self::COPY_KEYS as $key ) {
				if ( array_key_exists( $key, $body['copy'] ) ) {
					$next[ $key ] = mb_substr( sanitize_text_field( (string) $body['copy'][ $key ] ), 0, 240 );
				}
			}
		}

		if ( isset( $body['webhook'] ) && is_array( $body['webhook'] ) ) {
			$hook = is_array( $next['webhook'] ?? null ) ? $next['webhook'] : [ 'enabled' => false, 'url' => '' ];
			if ( array_key_exists( 'url', $body['webhook'] ) ) {
				$url = trim( (string) $body['webhook']['url'] );
				$clean = esc_url_raw( $url, [ 'https', 'http' ] );
				if ( '' !== $url && ( '' === $clean || ! wp_http_validate_url( $clean ) ) ) {
					return self::error( 'bspe_connect_invalid_webhook', 'webhook.url is not a valid public http(s) URL.', 400 );
				}
				$hook['url'] = $clean;
			}
			if ( array_key_exists( 'enabled', $body['webhook'] ) ) {
				$hook['enabled'] = self::flag( $body['webhook']['enabled'] );
			}
			$next['webhook'] = $hook;
		}

		if ( isset( $body['mail'] ) && is_array( $body['mail'] ) ) {
			$mail = $body['mail'];
			if ( array_key_exists( 'to', $mail ) ) {
				$list = [];
				foreach ( preg_split( '/[,\s;]+/', (string) $mail['to'] ) ?: [] as $candidate ) {
					if ( '' !== $candidate && is_email( $candidate ) ) {
						$list[] = sanitize_email( $candidate );
					}
				}
				$next['mail_to'] = implode( ', ', $list );
			}
			if ( array_key_exists( 'subject', $mail ) ) {
				$next['mail_subject'] = sanitize_text_field( (string) $mail['subject'] );
			}
			if ( array_key_exists( 'from', $mail ) ) {
				$next['mail_from'] = sanitize_email( (string) $mail['from'] );
			}
			if ( array_key_exists( 'from_name', $mail ) ) {
				$next['mail_from_name'] = sanitize_text_field( (string) $mail['from_name'] );
			}
		}

		if ( 'custom' === ( $next['mode'] ?? 'standard' ) && Custom_Form::count_inputs( (array) ( $next['custom'] ?? [] ) ) < 1 ) {
			$warnings[] = 'mode is "custom" but the custom form has no visible fields, so visitors still see the standard form.';
		}

		return self::commit_form( $current, $next, $body, $dry_run, $warnings, 'api update' );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_list_elementor( $request ) {
		$limit = (int) ( $request->get_param( 'limit' ) ?? 50 );
		return self::ok( [ 'forms' => Elementor_Import::list_forms( $limit > 0 ? $limit : 50 ) ] );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_import_elementor( $request ) {
		$body    = self::body( $request );
		$post_id = absint( $body['post_id'] ?? 0 );
		$form_id = sanitize_key( (string) ( $body['form_id'] ?? '' ) );
		$apply   = self::flag( $body['apply'] ?? false );

		if ( $post_id <= 0 ) {
			return self::error( 'bspe_connect_missing_post', 'post_id is required (the Elementor document that holds the form — GET form/elementor lists them).', 400 );
		}

		$converted = Elementor_Import::convert( $post_id, $form_id );
		if ( is_wp_error( $converted ) ) {
			$data = (array) $converted->get_error_data();
			return self::error( $converted->get_error_code(), $converted->get_error_message(), (int) ( $data['status'] ?? 400 ) );
		}

		$skipped = (array) ( $converted['_skipped'] ?? [] );
		unset( $converted['_skipped'] );

		$result = Custom_Form::normalize( $converted );
		$warnings = $result['warnings'];
		foreach ( $skipped as $s ) {
			$warnings[] = 'Skipped Elementor field ' . $s . ' — BSPE Connect has its own spam protection / does not support it.';
		}
		if ( ! empty( $result['errors'] ) ) {
			return self::error( 'bspe_connect_invalid_form', 'The Elementor form could not be converted cleanly. Nothing was saved.', 400, [ 'errors' => $result['errors'], 'warnings' => $warnings, 'custom' => $result['config'] ] );
		}

		$current        = self::form_section();
		$next           = $current;
		$next['custom'] = $result['config'];
		if ( self::flag( $body['activate'] ?? true ) ) {
			$next['mode'] = 'custom';
		}

		$body['note'] = (string) ( $body['note'] ?? '' ) ?: sprintf( 'Imported Elementor form %s from post %d', $form_id ?: '(first)', $post_id );
		return self::commit_form( $current, $next, $body, ! $apply, $warnings, 'elementor import' );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_list_revisions( $request ) {
		unset( $request );
		$out = [];
		foreach ( self::revisions() as $rev ) {
			$form   = (array) ( $rev['form'] ?? [] );
			$custom = (array) ( $form['custom'] ?? [] );
			$out[]  = [
				'id'     => (string) $rev['id'],
				'at'     => (string) $rev['at'],
				'actor'  => (array) ( $rev['actor'] ?? [] ),
				'note'   => (string) ( $rev['note'] ?? '' ),
				'mode'   => (string) ( $form['mode'] ?? 'standard' ),
				'steps'  => count( (array) ( $custom['steps'] ?? [] ) ),
				'fields' => count( Custom_Form::all_fields( $custom ) ),
			];
		}
		return self::ok( [
			'current_revision' => self::revision_hash( self::form_section() ),
			'revisions'        => $out,
			'note'             => 'Each revision is the form section as it was BEFORE the change made at that time.',
		] );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_restore( $request ) {
		$body  = self::body( $request );
		$id    = (string) ( $body['revision_id'] ?? '' );
		$scope = 'all' === (string) ( $body['scope'] ?? '' ) ? 'all' : 'layout';
		foreach ( self::revisions() as $rev ) {
			if ( (string) $rev['id'] === $id ) {
				$current = self::form_section();
				$old     = (array) $rev['form'];
				// Default scope is the LAYOUT only (mode + custom fields). Mail
				// recipients, the webhook URL, anti-spam keys and copy may have
				// been changed on purpose since — rotating a Zapier hook or a
				// Turnstile secret must not be undone by "restore the fields".
				if ( 'all' === $scope ) {
					$next = array_merge( $current, $old );
				} else {
					$next           = $current;
					$next['mode']   = 'custom' === ( $old['mode'] ?? 'standard' ) ? 'custom' : 'standard';
					$next['custom'] = $old['custom'] ?? Custom_Form::defaults();
				}
				if ( isset( $next['custom'] ) ) {
					$next['custom'] = Custom_Form::normalize( $next['custom'] )['config'];
				}
				$body['note'] = 'Restored revision ' . $id . ( 'all' === $scope ? ' (whole form section)' : ' (layout)' );
				return self::commit_form( $current, $next, $body, self::flag( $body['dry_run'] ?? false ), [], 'restore' );
			}
		}
		return self::error( 'bspe_connect_no_revision', 'No revision with that id. GET form/revisions lists them.', 404 );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_test_webhook( $request ) {
		unset( $request );
		$url = trim( (string) Settings::get( 'form.webhook.url', '' ) );
		if ( '' === $url ) {
			return self::error( 'bspe_connect_no_webhook', 'No webhook URL is configured (form.webhook.url).', 400 );
		}

		$payload = Webhook::sample_payload();
		$result  = Webhook::send_to( $url, $payload, 'form_submission_test' );

		return self::ok( [
			'delivered' => $result['ok'],
			'http_code' => $result['code'],
			'error'     => $result['error'],
			'host'      => (string) wp_parse_url( $url, PHP_URL_HOST ),
			'payload'   => $payload,
			'note'      => 'Sent whether or not the webhook is enabled, so a Zap can be built before going live. The payload carries "test": true.',
		] );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_get_settings( $request ) {
		unset( $request );
		$all = Settings::all();
		if ( '' !== (string) ( $all['form']['antispam']['turnstile_secret_key'] ?? '' ) ) {
			$all['form']['antispam']['turnstile_secret_key'] = self::REDACTED;
		}
		return self::ok( [
			'settings'           => $all,
			'patchable_sections' => self::PATCHABLE_SECTIONS,
			'revision'           => self::revision_hash( $all['form'] ?? [] ),
		] );
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	public static function route_patch_settings( $request ) {
		$body  = self::body( $request );
		$patch = $body['patch'] ?? null;
		if ( ! is_array( $patch ) || empty( $patch ) ) {
			return self::error( 'bspe_connect_empty_patch', 'Send { "patch": { "<section>": { … } } }. Sections: ' . implode( ', ', self::PATCHABLE_SECTIONS ) . '.', 400 );
		}

		$unknown = array_diff( array_keys( $patch ), self::PATCHABLE_SECTIONS );
		if ( ! empty( $unknown ) ) {
			return self::error( 'bspe_connect_unknown_section', 'Unknown section(s): ' . implode( ', ', $unknown ) . '.', 400 );
		}
		if ( isset( $patch['form'] ) && is_array( $patch['form'] ) && ( array_key_exists( 'custom', $patch['form'] ) || array_key_exists( 'mode', $patch['form'] ) ) ) {
			return self::error( 'bspe_connect_use_form_route', 'form.mode and form.custom are changed through POST form (it validates the schema and keeps revisions).', 400 );
		}

		$existing = Settings::all();
		$next     = $existing;
		foreach ( $patch as $section => $values ) {
			$next = Admin\Settings_Saver::apply_patch( (string) $section, $values, $next );
		}

		$changed = self::diff_paths( $existing, $next );
		$dry_run = self::flag( $body['dry_run'] ?? false );

		if ( ! $dry_run && ! empty( $changed ) ) {
			if ( isset( $patch['form'] ) ) {
				self::record_revision( (array) ( $existing['form'] ?? [] ), $body, 'settings patch' );
			}
			Settings::save( $next );
			Logger::log( 'info', 'Settings patched over the management API', [
				'sections' => array_keys( $patch ),
				'changed'  => array_slice( $changed, 0, 40 ),
				'actor'    => self::actor( $body )['name'],
			] );
		}

		// The bar (and its form) is printed into every cached page.
		$purged = ( ! $dry_run && ! empty( $changed ) ) ? Cache_Purge::all( 'settings patch' ) : [];

		return self::ok( [
			'dry_run'      => $dry_run,
			'applied'      => ! $dry_run && ! empty( $changed ),
			'changed'      => $changed,
			'cache_purged' => $purged,
		] );
	}

	/* -----------------------------------------------------------------
	 * Form commit + revisions
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $next
	 * @param array<string,mixed> $body
	 * @param string[]            $warnings
	 */
	private static function commit_form( array $current, array $next, array $body, bool $dry_run, array $warnings, string $kind ) {
		$changes = self::describe_changes( $current, $next );

		$purged = [];
		if ( ! $dry_run && ! empty( $changes ) ) {
			self::record_revision( $current, $body, $kind );
			$all         = Settings::all();
			$all['form'] = $next;
			Settings::save( $all );
			Logger::log( 'info', 'Lead form changed over the management API', [
				'kind'    => $kind,
				'changes' => array_slice( $changes, 0, 40 ),
				'actor'   => self::actor( $body )['name'],
			] );
			$purged = Cache_Purge::all( $kind );
		}

		$view = self::form_view( $next );

		return self::ok( [
			'dry_run'      => $dry_run,
			'applied'      => ! $dry_run && ! empty( $changes ),
			'changes'      => $changes,
			'warnings'     => array_values( array_unique( $warnings ) ),
			'cache_purged' => $purged,
			'form'         => $view,
		] );
	}

	/**
	 * Snapshot the form section as it is BEFORE a change.
	 *
	 * @param array<string,mixed> $form_before
	 * @param array<string,mixed> $body
	 */
	public static function record_revision( array $form_before, array $body = [], string $kind = '' ): void {
		$revs   = self::revisions();
		$actor  = self::actor( $body );
		$note   = mb_substr( sanitize_text_field( (string) ( $body['note'] ?? '' ) ), 0, 200 );
		array_unshift( $revs, [
			'id'    => gmdate( 'YmdHis' ) . '-' . substr( md5( wp_json_encode( $form_before ) . microtime() ), 0, 6 ),
			'at'    => gmdate( 'c' ),
			'actor' => $actor,
			'note'  => '' !== $note ? $note : $kind,
			'form'  => $form_before,
		] );
		update_option( self::REVISIONS_OPTION, array_slice( $revs, 0, self::REVISIONS_KEEP ), false );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function revisions(): array {
		$revs = get_option( self::REVISIONS_OPTION, [] );
		return is_array( $revs ) ? array_values( array_filter( $revs, 'is_array' ) ) : [];
	}

	/**
	 * Human-readable change list, field by field.
	 *
	 * @param array<string,mixed> $old
	 * @param array<string,mixed> $new
	 * @return string[]
	 */
	public static function describe_changes( array $old, array $new ): array {
		$changes = [];
		if ( ( $old['mode'] ?? 'standard' ) !== ( $new['mode'] ?? 'standard' ) ) {
			$changes[] = sprintf( 'mode: %s → %s', $old['mode'] ?? 'standard', $new['mode'] ?? 'standard' );
		}

		$old_c = (array) ( $old['custom'] ?? [] );
		$new_c = (array) ( $new['custom'] ?? [] );
		$old_f = [];
		foreach ( Custom_Form::all_fields( $old_c ) as $f ) {
			$old_f[ $f['key'] ] = $f;
		}
		$new_f = [];
		foreach ( Custom_Form::all_fields( $new_c ) as $f ) {
			$new_f[ $f['key'] ] = $f;
		}
		if ( count( (array) ( $old_c['steps'] ?? [] ) ) !== count( (array) ( $new_c['steps'] ?? [] ) ) ) {
			$changes[] = sprintf( 'custom steps: %d → %d', count( (array) ( $old_c['steps'] ?? [] ) ), count( (array) ( $new_c['steps'] ?? [] ) ) );
		}
		foreach ( $new_f as $key => $f ) {
			if ( ! isset( $old_f[ $key ] ) ) {
				$changes[] = sprintf( 'field added: %s (%s, step %d)', $key, $f['type'], $f['_step'] + 1 );
			} elseif ( wp_json_encode( $old_f[ $key ] ) !== wp_json_encode( $f ) ) {
				$diff = [];
				foreach ( array_unique( array_merge( array_keys( $old_f[ $key ] ), array_keys( $f ) ) ) as $prop ) {
					if ( wp_json_encode( $old_f[ $key ][ $prop ] ?? null ) !== wp_json_encode( $f[ $prop ] ?? null ) ) {
						$diff[] = '_step' === $prop ? 'step' : $prop;
					}
				}
				$changes[] = sprintf( 'field changed: %s (%s)', $key, implode( ', ', $diff ) );
			}
		}
		foreach ( $old_f as $key => $f ) {
			if ( ! isset( $new_f[ $key ] ) ) {
				$changes[] = sprintf( 'field removed: %s', $key );
			}
		}
		$order_old = array_keys( array_intersect_key( $old_f, $new_f ) );
		$order_new = array_keys( array_intersect_key( $new_f, $old_f ) );
		if ( $order_old !== $order_new ) {
			$changes[] = 'field order changed';
		}
		foreach ( [ 'next_label', 'back_label', 'submit_label', 'show_progress', 'progress_format' ] as $k ) {
			if ( wp_json_encode( $old_c[ $k ] ?? null ) !== wp_json_encode( $new_c[ $k ] ?? null ) && ( isset( $old_c[ $k ] ) || isset( $new_c[ $k ] ) ) ) {
				$changes[] = 'custom.' . $k . ' changed';
			}
		}
		foreach ( (array) ( $new_c['steps'] ?? [] ) as $i => $step ) {
			$before = (array) ( $old_c['steps'][ $i ] ?? [] );
			if ( ( $before['title'] ?? '' ) !== ( $step['title'] ?? '' ) || ( $before['subtitle'] ?? '' ) !== ( $step['subtitle'] ?? '' ) ) {
				$changes[] = sprintf( 'step %d title/subtitle changed', $i + 1 );
			}
		}

		$rest_old = $old;
		$rest_new = $new;
		unset( $rest_old['custom'], $rest_new['custom'], $rest_old['mode'], $rest_new['mode'] );
		foreach ( self::diff_paths( $rest_old, $rest_new ) as $path ) {
			$changes[] = $path . ' changed';
		}
		return $changes;
	}

	/* -----------------------------------------------------------------
	 * Views + helpers
	 * ----------------------------------------------------------------- */

	/**
	 * @param array<string,mixed>|null $form
	 * @return array<string,mixed>
	 */
	public static function form_view( ?array $form = null ): array {
		$form   = $form ?? self::form_section();
		$custom = array_merge( Custom_Form::defaults(), (array) ( $form['custom'] ?? [] ) );
		$copy   = [];
		foreach ( self::COPY_KEYS as $k ) {
			$copy[ $k ] = (string) ( $form[ $k ] ?? '' );
		}
		$hook = is_array( $form['webhook'] ?? null ) ? $form['webhook'] : [];

		return [
			'revision'        => self::revision_hash( $form ),
			'mode'            => 'custom' === ( $form['mode'] ?? 'standard' ) ? 'custom' : 'standard',
			'custom_active'   => 'custom' === ( $form['mode'] ?? 'standard' ) && Custom_Form::count_inputs( $custom ) > 0,
			'custom'          => $custom,
			'effective_roles' => Custom_Form::effective_roles( $custom ),
			'standard_fields' => (array) ( $form['fields'] ?? [] ),
			'copy'            => $copy,
			'mail'            => [
				'to'        => (string) ( $form['mail_to'] ?? '' ),
				'subject'   => (string) ( $form['mail_subject'] ?? '' ),
				'from'      => (string) ( $form['mail_from'] ?? '' ),
				'from_name' => (string) ( $form['mail_from_name'] ?? '' ),
			],
			'webhook'         => [
				'enabled' => ! empty( $hook['enabled'] ),
				'url'     => (string) ( $hook['url'] ?? '' ),
			],
			'webhook_keys'    => Webhook::payload_keys( $custom ),
			'catalog'         => [
				'field_types' => Custom_Form::TYPES,
				'roles'       => Custom_Form::ROLES,
				'limits'      => [ 'steps' => Custom_Form::MAX_STEPS, 'fields' => Custom_Form::MAX_FIELDS, 'options' => Custom_Form::MAX_OPTIONS ],
				'field_shape' => [
					'key'          => 'a-z, 0-9, _ ; starts with a letter; unique; becomes the webhook key',
					'type'         => implode( '|', Custom_Form::TYPES ),
					'label'        => 'text (checkbox labels may contain <a>, <strong>, <em>)',
					'placeholder'  => 'text; shown inside the input (defaults to the label)',
					'required'     => 'bool',
					'width'        => 'full|half',
					'role'         => '(optional) ' . implode( '|', Custom_Form::ROLES ) . ' — maps the field onto the core lead columns; inferred when empty',
					'options'      => 'select/radio/checkboxes: ["Yes","No"] or [{"value":"CA","label":"California"}] or "Label|value" lines',
					'default'      => 'preselected value; for hidden fields the value always sent',
					'help'         => 'small text under the field',
					'show_label'   => 'bool; show the label above the input instead of only as placeholder',
					'min/max/step' => 'number only',
					'maxlength'    => 'text (≤500) / textarea (≤10000)',
					'rows'         => 'textarea only, 2-12',
					'phone_format' => 'tel only: us (10 digits, default) | intl (7-15 digits)',
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function form_section(): array {
		$form = Settings::get( 'form', [] );
		return is_array( $form ) ? $form : [];
	}

	/**
	 * @param array<string,mixed> $form
	 */
	public static function revision_hash( array $form ): string {
		return substr( md5( (string) wp_json_encode( $form ) ), 0, 12 );
	}

	/**
	 * Optimistic lock: a caller that read revision X and sends
	 * if_revision: X is refused if somebody changed the form since.
	 *
	 * @param array<string,mixed> $body
	 * @param array<string,mixed> $current
	 */
	private static function check_revision( array $body, array $current ) {
		$expected = (string) ( $body['if_revision'] ?? '' );
		if ( '' !== $expected && $expected !== self::revision_hash( $current ) ) {
			return self::error( 'bspe_connect_revision_conflict', 'The form changed since you read it. GET form again and re-apply your change.', 409, [ 'current_revision' => self::revision_hash( $current ) ] );
		}
		return null;
	}

	/**
	 * Dot paths whose value differs between two settings trees.
	 *
	 * @param array<string,mixed> $a
	 * @param array<string,mixed> $b
	 * @return string[]
	 */
	private static function diff_paths( array $a, array $b, string $prefix = '' ): array {
		$out = [];
		foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $k ) {
			$path = '' === $prefix ? (string) $k : $prefix . '.' . $k;
			$va   = $a[ $k ] ?? null;
			$vb   = $b[ $k ] ?? null;
			if ( is_array( $va ) && is_array( $vb ) && ! self::is_list( $va ) && ! self::is_list( $vb ) ) {
				$out = array_merge( $out, self::diff_paths( $va, $vb, $path ) );
			} elseif ( wp_json_encode( $va ) !== wp_json_encode( $vb ) ) {
				$out[] = $path;
			}
		}
		return $out;
	}

	/**
	 * array_is_list() for PHP 8.0.
	 *
	 * @param array<int|string,mixed> $arr
	 */
	private static function is_list( array $arr ): bool {
		return [] === $arr || array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return array<string,mixed>
	 */
	private static function body( $request ): array {
		$json = $request->get_json_params();
		if ( is_array( $json ) ) {
			return $json;
		}
		$params = $request->get_body_params();
		return is_array( $params ) ? $params : [];
	}

	/**
	 * Who made the change: the Fleet sends { actor: {app, name, email} };
	 * a wp-admin caller is the logged-in user.
	 *
	 * @param array<string,mixed> $body
	 * @return array{app:string,name:string,email:string}
	 */
	private static function actor( array $body ): array {
		$in = is_array( $body['actor'] ?? null ) ? $body['actor'] : [];
		$actor = [
			'app'   => mb_substr( sanitize_text_field( (string) ( $in['app'] ?? '' ) ), 0, 60 ),
			'name'  => mb_substr( sanitize_text_field( (string) ( $in['name'] ?? '' ) ), 0, 120 ),
			'email' => sanitize_email( (string) ( $in['email'] ?? '' ) ),
		];
		if ( '' === $actor['name'] ) {
			$user = wp_get_current_user();
			if ( $user && $user->exists() ) {
				$actor['name']  = (string) $user->display_name;
				$actor['email'] = '' !== $actor['email'] ? $actor['email'] : (string) $user->user_email;
				$actor['app']   = '' !== $actor['app'] ? $actor['app'] : 'wp-admin';
			} else {
				$actor['name'] = 'unknown';
				$actor['app']  = '' !== $actor['app'] ? $actor['app'] : 'fleet';
			}
		}
		return $actor;
	}

	/**
	 * @param mixed $v
	 */
	private static function flag( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return in_array( strtolower( trim( (string) $v ) ), [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function ok( array $data ): \WP_REST_Response {
		$response = new \WP_REST_Response( array_merge( [ 'ok' => true, 'version' => BSPE_CONNECT_VERSION ], $data ), 200 );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * @param array<string,mixed> $extra
	 */
	private static function error( string $code, string $message, int $status, array $extra = [] ): \WP_Error {
		if ( 0 !== strpos( $code, 'bspe_' ) ) {
			$code = 'bspe_connect_' . $code;
		}
		return new \WP_Error( $code, $message, array_merge( [ 'status' => $status, 'version' => BSPE_CONNECT_VERSION ], $extra ) );
	}
}
