<?php
/**
 * Elementor Pro form → BSPE Connect custom form converter.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Finds Elementor Pro Form widgets on the site and converts one into the
 * Custom_Form schema, so the bar's form can mirror the firm's website form
 * (same fields, same keys, same steps) without retyping it.
 *
 * Reads `_elementor_data` straight from post meta — no Elementor classes are
 * needed, so it works on a site where Elementor is temporarily inactive.
 *
 * Field mapping:
 *   step                      → starts a new step (its label becomes the title)
 *   text/email/tel/number/
 *   textarea/date/hidden      → same type
 *   url/time                  → text
 *   select (multiple)         → checkboxes, else select
 *   radio                     → radio
 *   checkbox                  → checkboxes (one option = single checkbox)
 *   acceptance                → checkbox (label = acceptance text)
 *   honeypot, recaptcha*,
 *   html, upload, password    → skipped (BSPE Connect has its own anti-spam)
 */
final class Elementor_Import {

	private const SKIP_TYPES = [ 'honeypot', 'recaptcha', 'recaptcha_v3', 'html', 'upload', 'password', 'step_divider' ];

	/**
	 * Every Elementor form widget in published/private/draft content and in
	 * Elementor templates (popups, sections).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_forms( int $limit = 50 ): array {
		global $wpdb;
		$limit = max( 1, min( 200, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, p.post_title, p.post_type, p.post_status
				   FROM {$wpdb->postmeta} pm
				   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				  WHERE pm.meta_key = '_elementor_data'
				    AND pm.meta_value LIKE %s
				    AND p.post_type NOT IN ('revision','nav_menu_item')
				    AND p.post_status IN ('publish','private','draft','future')
				  ORDER BY p.post_type = 'elementor_library' DESC, p.ID ASC
				  LIMIT %d",
				'%' . $wpdb->esc_like( '"widgetType":"form"' ) . '%',
				$limit
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['post_id'];
			foreach ( self::forms_in_post( $post_id ) as $form ) {
				$settings  = (array) ( $form['settings'] ?? [] );
				$fields    = (array) ( $settings['form_fields'] ?? [] );
				$steps     = 0;
				$inputs    = 0;
				$keys      = [];
				foreach ( $fields as $f ) {
					$type = (string) ( $f['field_type'] ?? 'text' );
					if ( 'step' === $type ) {
						++$steps;
					} elseif ( ! in_array( $type, self::SKIP_TYPES, true ) ) {
						++$inputs;
						$keys[] = (string) ( $f['custom_id'] ?? '' );
					}
				}
				$out[] = [
					'post_id'     => $post_id,
					'post_title'  => (string) $row['post_title'],
					'post_type'   => (string) $row['post_type'],
					'post_status' => (string) $row['post_status'],
					'form_id'     => (string) ( $form['id'] ?? '' ),
					'form_name'   => (string) ( $settings['form_name'] ?? '' ),
					'steps'       => max( 1, $steps ),
					'fields'      => $inputs,
					'keys'        => $keys,
				];
			}
		}
		return $out;
	}

	/**
	 * Convert one Elementor form into a (not yet normalized) custom config.
	 *
	 * @param int    $post_id Document that holds the widget (the form's hidden post_id input).
	 * @param string $form_id Widget element id (the form's hidden form_id input). '' = first form in the post.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function convert( int $post_id, string $form_id = '' ) {
		$forms = self::forms_in_post( $post_id );
		if ( empty( $forms ) ) {
			return new \WP_Error( 'bspe_connect_no_elementor_form', sprintf( 'Post %d has no Elementor form widget.', $post_id ), [ 'status' => 404 ] );
		}

		$form = null;
		if ( '' === $form_id ) {
			$form = $forms[0];
		} else {
			foreach ( $forms as $candidate ) {
				if ( (string) ( $candidate['id'] ?? '' ) === $form_id ) {
					$form = $candidate;
					break;
				}
			}
		}
		if ( null === $form ) {
			$ids = array_map( static function ( $f ) { return (string) ( $f['id'] ?? '' ); }, $forms );
			return new \WP_Error( 'bspe_connect_no_elementor_form', sprintf( 'Post %d has no form with id "%s". Forms in that post: %s.', $post_id, $form_id, implode( ', ', $ids ) ), [ 'status' => 404 ] );
		}

		$settings   = (array) ( $form['settings'] ?? [] );
		$steps      = [];
		$current    = null;
		$skipped    = [];
		$back_label = '';
		$next_label = '';
		// With step_type "none" Elementor shows no step names, and its default
		// step label is the literal word "Step" — neither is a real title.
		$show_titles = ! in_array( (string) ( $settings['step_type'] ?? '' ), [ 'none', '' ], true );

		foreach ( (array) ( $settings['form_fields'] ?? [] ) as $ef ) {
			$ef   = (array) $ef;
			$type = (string) ( $ef['field_type'] ?? 'text' );

			if ( 'step' === $type ) {
				if ( null !== $current && ! empty( $current['fields'] ) ) {
					$steps[] = $current;
				}
				$step_label = trim( (string) ( $ef['field_label'] ?? '' ) );
				$current    = [
					'title'    => ( $show_titles && 'step' !== strtolower( $step_label ) ) ? $step_label : '',
					'subtitle' => '',
					'fields'   => [],
				];
				if ( '' === $back_label && '' !== trim( (string) ( $ef['previous_button'] ?? '' ) ) ) {
					$back_label = trim( (string) $ef['previous_button'] );
				}
				if ( '' === $next_label && '' !== trim( (string) ( $ef['next_button'] ?? '' ) ) ) {
					$next_label = trim( (string) $ef['next_button'] );
				}
				continue;
			}

			if ( in_array( $type, self::SKIP_TYPES, true ) ) {
				$skipped[] = (string) ( $ef['custom_id'] ?? $type ) . ' (' . $type . ')';
				continue;
			}

			if ( null === $current ) {
				$current = [ 'title' => '', 'subtitle' => '', 'fields' => [] ];
			}
			$current['fields'][] = self::map_field( $ef );
		}
		if ( null !== $current && ! empty( $current['fields'] ) ) {
			$steps[] = $current;
		}

		$submit = trim( wp_strip_all_tags( (string) ( $settings['button_text'] ?? '' ) ) );

		return [
			'steps'         => $steps,
			'next_label'    => self::title_case_if_shouting( $next_label ?: ( (string) ( $settings['step_next_label'] ?? '' ) ?: 'Next' ) ),
			'back_label'    => self::title_case_if_shouting( $back_label ?: ( (string) ( $settings['step_previous_label'] ?? '' ) ?: 'Back' ) ),
			'submit_label'  => '' !== $submit ? self::title_case_if_shouting( $submit ) : 'Submit',
			'show_progress' => count( $steps ) > 1,
			'source'        => array_filter( [
				'type'        => 'elementor',
				'post_id'     => $post_id,
				'form_id'     => (string) ( $form['id'] ?? '' ),
				'form_name'   => (string) ( $settings['form_name'] ?? '' ),
				'template_id' => (int) ( $form['_global_template'] ?? 0 ),
				'imported_at' => gmdate( 'c' ),
			] ),
			'_skipped'      => $skipped,
		];
	}

	/**
	 * @param array<string,mixed> $ef Elementor repeater item.
	 * @return array<string,mixed>
	 */
	private static function map_field( array $ef ): array {
		$etype       = (string) ( $ef['field_type'] ?? 'text' );
		$label       = trim( (string) ( $ef['field_label'] ?? '' ) );
		$placeholder = trim( (string) ( $ef['placeholder'] ?? '' ) );
		$options_raw = (string) ( $ef['field_options'] ?? '' );
		$options     = Custom_Form::normalize_options( $options_raw );

		// Elementor never shows `placeholder` on a select (the editor keeps
		// whatever was typed before the type changed — "Anual Salary" on a
		// Yes/No dropdown). Its real placeholder is a first option with an
		// empty value ("Your location - State|"), else the label.
		if ( 'select' === $etype ) {
			$placeholder = '';
			foreach ( preg_split( '/\r\n|\r|\n/', $options_raw ) ?: [] as $line ) {
				if ( false !== strpos( $line, '|' ) ) {
					[ $l, $v ] = array_map( 'trim', explode( '|', $line, 2 ) );
					if ( '' === $v && '' !== $l ) {
						$placeholder = $l;
					}
				}
				break;
			}
			if ( '' === $placeholder ) {
				$placeholder = $label;
			}
		}

		switch ( $etype ) {
			case 'select':
				$type = ! empty( $ef['allow_multiple'] ) ? 'checkboxes' : 'select';
				break;
			case 'checkbox':
				$type = count( $options ) <= 1 ? 'checkbox' : 'checkboxes';
				if ( 'checkbox' === $type && ! empty( $options ) ) {
					$label = $options[0]['label'];
				}
				break;
			case 'acceptance':
				$type  = 'checkbox';
				$label = trim( (string) ( $ef['acceptance_text'] ?? '' ) ) ?: $label;
				break;
			case 'url':
			case 'time':
				$type = 'text';
				break;
			default:
				$type = in_array( $etype, Custom_Form::TYPES, true ) ? $etype : 'text';
		}

		$key = strtolower( (string) ( $ef['custom_id'] ?? '' ) );
		if ( '' === $key || ! preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $key ) ) {
			$key = Custom_Form::key_from_label( '' !== $label ? $label : $placeholder );
		}

		// The bar's form is a phone bottom sheet, so Elementor's MOBILE width
		// wins when the designer set one.
		$width_raw = (string) ( $ef['width_mobile'] ?? '' );
		if ( '' === $width_raw ) {
			$width_raw = (string) ( $ef['width'] ?? '100' );
		}

		$field = [
			'key'         => $key,
			'type'        => $type,
			'label'       => '' !== $label ? $label : $placeholder,
			'placeholder' => $placeholder,
			'required'    => self::truthy( $ef['required'] ?? '' ),
			'width'       => in_array( $width_raw, [ '50', '33', '25', '40', '20' ], true ) ? 'half' : 'full',
			'default'     => (string) ( $ef['field_value'] ?? '' ),
		];

		if ( in_array( $type, [ 'select', 'radio', 'checkboxes' ], true ) ) {
			$field['options'] = $options;
		}
		if ( 'number' === $type ) {
			if ( isset( $ef['field_min'] ) && '' !== $ef['field_min'] ) {
				$field['min'] = $ef['field_min'];
			}
			if ( isset( $ef['field_max'] ) && '' !== $ef['field_max'] ) {
				$field['max'] = $ef['field_max'];
			}
		}
		if ( 'textarea' === $type && ! empty( $ef['rows'] ) ) {
			$field['rows'] = (int) $ef['rows'];
		}
		return $field;
	}

	/**
	 * All form widgets in one post's Elementor tree.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function forms_in_post( int $post_id ): array {
		$data = self::tree( $post_id );
		if ( null === $data ) {
			return [];
		}
		$found = [];
		self::walk( $data, $found, 0 );
		return $found;
	}

	/**
	 * @return array<int|string,mixed>|null
	 */
	private static function tree( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $data ) ? $data : null;
	}

	/**
	 * GLOBAL WIDGETS: a page that uses a saved global form holds only a
	 * { widgetType: "global", templateID } stub (plus a stale settings copy);
	 * the live settings are in the template post. The stub's element id is
	 * what the rendered form posts as form_id, so that id is kept while the
	 * settings come from the template.
	 *
	 * @param array<int|string,mixed>        $nodes
	 * @param array<int,array<string,mixed>> $found
	 */
	private static function walk( array $nodes, array &$found, int $depth ): void {
		if ( $depth > 30 ) {
			return;
		}
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( 'widget' === ( $node['elType'] ?? '' ) && 'form' === ( $node['widgetType'] ?? '' ) ) {
				$found[] = $node;
			} elseif ( 'widget' === ( $node['elType'] ?? '' ) && 'global' === ( $node['widgetType'] ?? '' ) && ! empty( $node['templateID'] ) && $depth < 10 ) {
				$template = self::tree( (int) $node['templateID'] );
				$inner    = [];
				if ( null !== $template ) {
					self::walk( $template, $inner, $depth + 10 );
				}
				if ( ! empty( $inner ) ) {
					$resolved                     = $inner[0];
					$resolved['id']               = (string) ( $node['id'] ?? $resolved['id'] ?? '' );
					$resolved['_global_template'] = (int) $node['templateID'];
					$found[]                      = $resolved;
				} elseif ( ! empty( $node['settings']['form_fields'] ) ) {
					// Template missing: the stub's own copy is better than nothing.
					$node['_global_template'] = (int) $node['templateID'];
					$found[]                  = $node;
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk( $node['elements'], $found, $depth + 1 );
			}
		}
	}

	/**
	 * @param mixed $v
	 */
	private static function truthy( $v ): bool {
		return in_array( strtolower( (string) $v ), [ 'true', 'yes', '1', 'on' ], true ) || true === $v;
	}

	/** "SUBMIT" (a CSS text-transform victim) → "Submit". */
	private static function title_case_if_shouting( string $s ): string {
		return ( strtoupper( $s ) === $s && strlen( $s ) > 1 ) ? ucfirst( strtolower( $s ) ) : $s;
	}
}
