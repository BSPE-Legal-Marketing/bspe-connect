<?php
/**
 * Detect a website's color palette from Elementor or block-theme sources.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only adapter that surfaces every color the host site already has
 * defined, so the Design tab's "Use Default Website Colors" preset can
 * let an admin map their existing palette onto the BSPE Connect color
 * slots.
 *
 * Detection priority:
 *   1. Elementor — if active, read system_colors + custom_colors from the
 *      active kit. Includes user-defined custom colors with their typed
 *      labels.
 *   2. Block theme — wp_get_global_settings()['color']['palette']. Handles
 *      both flat and theme/default/custom-grouped shapes returned by
 *      different WP versions / theme.json layouts.
 *   3. None — admin sees the preset button as disabled with an explanation.
 *
 * No PHP dependency on Elementor's classes — reads its WP options + post
 * meta directly, so the plugin still works on sites without Elementor.
 */
final class Theme_Palette {

	/**
	 * @return array{
	 *   source: 'elementor'|'theme'|'none',
	 *   label:  string,
	 *   colors: array<int, array{label:string, value:string}>,
	 * }
	 */
	public static function detect(): array {
		$elementor = self::from_elementor();
		if ( ! empty( $elementor ) ) {
			return [
				'source' => 'elementor',
				'label'  => __( 'Elementor', 'bspe-connect' ),
				'colors' => $elementor,
			];
		}

		$theme = self::from_theme_json();
		if ( ! empty( $theme ) ) {
			return [
				'source' => 'theme',
				'label'  => __( 'Block theme', 'bspe-connect' ),
				'colors' => $theme,
			];
		}

		return [
			'source' => 'none',
			'label'  => '',
			'colors' => [],
		];
	}

	/**
	 * Read system_colors + custom_colors out of the active Elementor kit.
	 * Returns [] when Elementor isn't installed, no kit is active, or the
	 * meta value is in an unexpected shape (defensive against Elementor
	 * version drift).
	 *
	 * @return array<int, array{label:string, value:string}>
	 */
	private static function from_elementor(): array {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( $kit_id <= 0 ) {
			return [];
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$out = [];
		foreach ( [ 'system_colors', 'custom_colors' ] as $bucket ) {
			$entries = $settings[ $bucket ] ?? [];
			if ( ! is_array( $entries ) ) {
				continue;
			}
			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$value = trim( (string) ( $entry['color'] ?? '' ) );
				$label = trim( (string) ( $entry['title'] ?? '' ) );

				if ( '' === $value || ! self::is_valid_hex( $value ) ) {
					continue;
				}
				if ( '' !== $value && '#' !== $value[0] ) {
					$value = '#' . $value;
				}
				if ( '' === $label ) {
					$label = $value;
				}
				$out[] = [
					'label' => $label,
					'value' => strtolower( $value ),
				];
			}
		}
		return $out;
	}

	/**
	 * Pull settings.color.palette out of the active block theme via the WP
	 * global settings API. Returns [] for classic themes that don't ship a
	 * theme.json. wp_get_global_settings was added in WP 5.9; the plugin
	 * requires 6.0 so it's always available, but we guard anyway.
	 *
	 * @return array<int, array{label:string, value:string}>
	 */
	private static function from_theme_json(): array {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return [];
		}

		$palette = wp_get_global_settings( [ 'color', 'palette' ] );
		if ( ! is_array( $palette ) ) {
			return [];
		}

		// Depending on the theme + WP version, the resolved palette can
		// either be a flat list of { name, slug, color } or a grouped map
		// { theme: [...], default: [...], custom: [...] }. Flatten both.
		$candidates = ( isset( $palette['theme'] ) || isset( $palette['default'] ) || isset( $palette['custom'] ) )
			? array_merge(
				(array) ( $palette['theme']   ?? [] ),
				(array) ( $palette['custom']  ?? [] ),
				(array) ( $palette['default'] ?? [] )
			)
			: $palette;

		$out  = [];
		$seen = [];
		foreach ( $candidates as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$value = trim( (string) ( $entry['color'] ?? '' ) );
			$label = trim( (string) ( $entry['name']  ?? '' ) );

			if ( '' === $value || ! self::is_valid_hex( $value ) ) {
				continue;
			}
			if ( '' !== $value && '#' !== $value[0] ) {
				$value = '#' . $value;
			}
			$value = strtolower( $value );

			// theme.json can list the same color in both 'theme' and 'default'
			// (e.g. when the theme inherits WP's default palette without
			// disabling defaults). Dedupe so the dropdown stays tidy.
			$dedupe_key = $value . '|' . $label;
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			if ( '' === $label ) {
				$label = $value;
			}
			$out[] = [ 'label' => $label, 'value' => $value ];
		}
		return $out;
	}

	/**
	 * Accept 3- or 6-digit hex, with or without a leading '#'. Used by both
	 * adapters before pushing a value into the output so we never expose a
	 * malformed string to the admin UI.
	 */
	private static function is_valid_hex( string $value ): bool {
		$value = trim( $value );
		return (bool) preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $value );
	}
}
