<?php
/**
 * Settings store.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes plugin settings. Owns the canonical defaults so the
 * Activator and admin UI never disagree on shape.
 */
final class Settings {

	public const OPTION_KEY = 'bspe_connect_settings';

	public const DB_VERSION_KEY = 'bspe_connect_db_version';

	public const DB_VERSION = '1.1.0';

	/**
	 * Returns the canonical default settings array (spec §9).
	 *
	 * Defined as a method (not a const) because PHP class constants do not
	 * support trailing-comma array literals on PHP 8.0 in all engines and
	 * because we want to apply a filter so site owners can tweak defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		$defaults = [
			'enabled'        => false,
			'welcome_bubble' => [
				'enabled'     => true,
				'heading'     => 'Welcome to {firm_name}',
				'message'     => "I'm here if you have any questions or need help!",
				'show_avatar' => false,
				'avatar_id'   => 0,
				'trigger'     => 'auto',
				'delay'       => 3,
				'repeat'      => 'session',
			],
			'display'        => [
				'show_delay'        => 3,
				'scroll_threshold'  => 0,
				'hide_on_scroll_up' => false,
				'mobile_breakpoint' => 768,
			],
			'buttons'        => [
				'connect' => [
					'enabled'         => true,
					'label'           => 'Connect',
					'icon_library'    => 'none',
					'icon'            => '',
					'label_weight'    => '',
					// Connect specifically forces UPPERCASE by default — the
					// other buttons inherit (which now defaults to off).
					'label_uppercase' => 'yes',
				],
				'call'    => [
					'enabled'         => true,
					'phone'           => '',
					'label'           => 'Call',
					'icon_library'    => 'fa-solid',
					'icon'            => 'phone',
					'label_weight'    => '',
					'label_uppercase' => '',
				],
				'text'    => [
					'enabled'         => true,
					'mode'            => 'sms',
					'phone'           => '',
					'label'           => 'Text',
					'icon_library'    => 'fa-solid',
					'icon'            => 'comment-dots',
					'label_weight'    => '',
					'label_uppercase' => '',
				],
				'email'   => [
					'enabled'         => true,
					'label'           => 'Email',
					'icon_library'    => 'fa-solid',
					'icon'            => 'envelope',
					'label_weight'    => '',
					'label_uppercase' => '',
				],
			],
			'form'           => [
				'fields'         => [
					'name'         => [
						'visible'  => true,
						'required' => true,
					],
					'phone'        => [
						'visible'  => true,
						'required' => true,
					],
					'email'        => [
						'visible'  => true,
						'required' => true,
					],
					'contact_pref' => [
						'visible'  => false,
						'required' => false,
					],
					'message'      => [
						'visible'  => true,
						'required' => true,
					],
				],
				'text_heading'    => 'Send us a text',
				'email_heading'   => 'Send us an email',
				'text_subheading'  => 'Please enter your name and contact info.',
				'email_subheading' => 'Please enter your name and contact info.',
				'submit_label'   => 'Send',
				'success_msg'    => "Thanks. We'll be in touch shortly.",
				'mail_to'        => '',
				'mail_subject'   => 'New lead from {site_name}: {source}',
				'mail_from'      => '',
				'mail_from_name' => '{site_name}',
				'antispam'       => [
					'honeypot'             => true,
					'min_seconds'          => 2,
					'rate_limit'           => 5,
					'turnstile_enabled'    => false,
					'turnstile_site_key'   => '',
					'turnstile_secret_key' => '',
				],
				// Days to keep saved submissions. 0 = keep forever (default,
				// backward-compatible). The daily prune cron checks this
				// setting and deletes rows from wp_bspe_connect_submissions
				// whose submitted_at is older than retention_days. Sent
				// emails are untouched.
				'retention_days' => 0,
			],
			'design'         => [
				'firm_name'   => '',
				'colors'      => [
					'bar_bg'    => '#351E28',
					'button_bg' => '#351E28',
					'button_fg' => '#FAF7F2',
					'bubble_bg' => '#FAF7F2',
					'bubble_fg' => '#351E28',
					'accent'    => '#3AAFB9',
				],
				'icon_size'             => 16,
				'label_size'            => 12,
				'label_weight'          => 500,
				'label_uppercase'       => false,
				'button_padding_top'    => 6,
				'button_padding_right'  => 4,
				'button_padding_bottom' => 6,
				'button_padding_left'   => 4,
				'icon_label_gap'        => 2,
				'font_mode'             => 'inherit',
				'google_font'           => 'DM Sans',
			],
			'display_rules'  => [
				'mode'  => 'sitewide',
				'slugs' => '',
			],
			'diagnostics'    => [
				'logging_enabled' => false,
			],
		];

		/**
		 * Filter the default settings before they're persisted on activation
		 * or merged on read.
		 *
		 * @param array $defaults Default settings array.
		 */
		return apply_filters( 'bspe_connect_default_settings', $defaults );
	}

	/**
	 * Full settings array, with stored values merged on top of defaults.
	 * Recursive merge so partially-saved options never break shape.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return self::deep_merge( self::defaults(), $stored );
	}

	/**
	 * Look up a single setting by dot-path.
	 *
	 * @param string $path Dot-separated key path. Example: design.colors.accent
	 * @param mixed  $default Returned when the path is absent.
	 *
	 * @return mixed
	 */
	public static function get( string $path, $default = null ) {
		$node = self::all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return $default;
			}
			$node = $node[ $segment ];
		}
		return $node;
	}

	/**
	 * Persist a full settings array. Phase 4 will call this from sanitized
	 * admin form handlers; provided here so other classes can read a stable
	 * write contract.
	 *
	 * @param array<string,mixed> $new New settings array.
	 */
	public static function save( array $new ): bool {
		return (bool) update_option( self::OPTION_KEY, $new );
	}

	/**
	 * Recursive associative merge. Numeric arrays from $override replace
	 * those from $base wholesale, so we don't double up on lists.
	 *
	 * @param array<string,mixed> $base Base array.
	 * @param array<string,mixed> $override Override array.
	 *
	 * @return array<string,mixed>
	 */
	private static function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if (
				isset( $base[ $key ] ) &&
				is_array( $base[ $key ] ) &&
				is_array( $value ) &&
				self::is_assoc( $base[ $key ] )
			) {
				$base[ $key ] = self::deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	private static function is_assoc( array $arr ): bool {
		if ( [] === $arr ) {
			return true;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
