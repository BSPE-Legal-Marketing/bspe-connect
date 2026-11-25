<?php
/**
 * Custom (multi-step) lead form: schema, validation and submission parsing.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * The "custom form" exception to the standard five-field lead form.
 *
 * A site whose own website form asks for more (city, employer, salary…) can
 * switch `form.mode` to `custom` and describe its form as steps of fields:
 *
 *   form.custom = {
 *     steps: [
 *       { title, subtitle, fields: [ { key, type, label, placeholder, required,
 *                                      width, role, options, … } ] },
 *     ],
 *     next_label, back_label, submit_label, show_progress, progress_format,
 *     source: { type: 'elementor', post_id, form_id }   // provenance only
 *   }
 *
 * ONE NORMALIZER FOR EVERY WRITER. The admin builder, the REST API (Fleet),
 * the Elementor importer and a revision restore all hand their input to
 * normalize(), and only its output is ever stored. A field that reaches the
 * frontend template therefore always has every key the template reads.
 *
 * ROLES tie custom fields back to the plugin's core columns (name, phone,
 * email, message, contact_pref), so the Submissions list, the email subject
 * placeholders and the webhook's established keys keep working whatever the
 * form looks like. A field with no explicit role is matched by type/key at
 * submission time (first tel → phone, first email → email, fname → first
 * name …), see effective_roles().
 */
final class Custom_Form {

	public const TYPES = [ 'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'checkboxes', 'date', 'hidden' ];

	public const ROLES = [ 'first_name', 'last_name', 'full_name', 'phone', 'email', 'message', 'contact_pref' ];

	/** Field types each role may sit on. */
	private const ROLE_TYPES = [
		'first_name'   => [ 'text' ],
		'last_name'    => [ 'text' ],
		'full_name'    => [ 'text' ],
		'phone'        => [ 'tel' ],
		'email'        => [ 'email' ],
		'message'      => [ 'textarea', 'text' ],
		'contact_pref' => [ 'select', 'radio' ],
	];

	public const MAX_STEPS   = 10;
	public const MAX_FIELDS  = 60;
	public const MAX_OPTIONS = 250;

	/** POST array the frontend inputs live under: bspe_f[<key>]. */
	public const POST_KEY = 'bspe_f';

	private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

	/**
	 * Defaults for form.custom. Steps empty = nothing to render, so a site
	 * flipped to custom mode with no steps keeps showing the standard form.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'steps'           => [],
			'next_label'      => 'Next',
			'back_label'      => 'Back',
			'submit_label'    => 'Submit',
			'show_progress'   => true,
			'progress_format' => 'Step {current} of {total}',
			'source'          => [],
		];
	}

	/**
	 * Stored custom form merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function config(): array {
		$stored = Settings::get( 'form.custom', [] );
		$stored = is_array( $stored ) ? $stored : [];
		$out    = array_merge( self::defaults(), $stored );
		$out['steps'] = is_array( $out['steps'] ) ? array_values( $out['steps'] ) : [];
		return $out;
	}

	public static function mode(): string {
		$mode = (string) Settings::get( 'form.mode', 'standard' );
		return 'custom' === $mode ? 'custom' : 'standard';
	}

	/**
	 * Custom mode is ON and the schema has at least one visible field. A
	 * custom form with nothing to fill in falls back to the standard form
	 * rather than rendering an empty sheet.
	 */
	public static function is_active(): bool {
		return 'custom' === self::mode() && self::count_inputs( self::config() ) > 0;
	}

	/**
	 * @param array<string,mixed> $config
	 */
	public static function count_inputs( array $config ): int {
		$n = 0;
		foreach ( self::all_fields( $config ) as $field ) {
			if ( 'hidden' !== ( $field['type'] ?? '' ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Every field of every step, in display order, each with a `_step` index.
	 *
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	public static function all_fields( array $config ): array {
		$out = [];
		foreach ( (array) ( $config['steps'] ?? [] ) as $i => $step ) {
			foreach ( (array) ( $step['fields'] ?? [] ) as $field ) {
				if ( is_array( $field ) ) {
					$field['_step'] = (int) $i;
					$out[]          = $field;
				}
			}
		}
		return $out;
	}

	/**
	 * Fingerprint of what the SERVER will parse: mode plus each field's key,
	 * type, required flag and allowed values. Printed into the form as a
	 * hidden input; a submission carrying another signature came from a
	 * page cached (NitroPack, LiteSpeed…) before the form changed, and is
	 * answered with "please refresh" instead of being parsed against fields
	 * it never showed. Labels and copy are left out on purpose: rewording a
	 * label must not invalidate every cached page.
	 */
	public static function signature(): string {
		if ( ! self::is_active() ) {
			return 's';
		}
		$shape = [];
		foreach ( self::all_fields( self::config() ) as $f ) {
			$shape[] = [
				$f['key'],
				$f['type'],
				! empty( $f['required'] ),
				array_column( (array) ( $f['options'] ?? [] ), 'value' ),
				$f['phone_format'] ?? '',
				$f['min'] ?? null,
				$f['max'] ?? null,
			];
		}
		return 'c' . substr( md5( (string) wp_json_encode( $shape ) ), 0, 10 );
	}

	/**
	 * How many visible fields the visitor actually answered.
	 *
	 * @param array<int,array{key:string,label:string,value:string,type:string}> $rows
	 */
	public static function answered_count( array $rows ): int {
		$n = 0;
		foreach ( $rows as $row ) {
			if ( 'hidden' !== $row['type'] && '' !== trim( (string) $row['value'] ) ) {
				++$n;
			}
		}
		return $n;
	}

	/* -----------------------------------------------------------------
	 * Normalization (the only path into storage)
	 * ----------------------------------------------------------------- */

	/**
	 * Validate + normalize an untrusted custom-form definition.
	 *
	 * Hard problems (unknown type, bad or duplicate key, a role on the wrong
	 * type, too many fields) are ERRORS and the caller must refuse the write.
	 * Soft problems (a key derived from the label, no phone or email field)
	 * are WARNINGS that travel back to the caller with the saved result.
	 *
	 * @param mixed $input Decoded JSON / array.
	 * @return array{config:array<string,mixed>, errors:string[], warnings:string[]}
	 */
	public static function normalize( $input ): array {
		$errors   = [];
		$warnings = [];
		$input    = is_array( $input ) ? $input : [];

		$config = self::defaults();
		foreach ( [ 'next_label', 'back_label', 'submit_label' ] as $label_key ) {
			if ( isset( $input[ $label_key ] ) ) {
				$value = self::clean_text( (string) $input[ $label_key ], 60 );
				if ( '' !== $value ) {
					$config[ $label_key ] = $value;
				}
			}
		}
		if ( array_key_exists( 'show_progress', $input ) ) {
			$config['show_progress'] = self::to_bool( $input['show_progress'] );
		}
		if ( isset( $input['progress_format'] ) ) {
			$fmt = self::clean_text( (string) $input['progress_format'], 80 );
			$config['progress_format'] = '' !== $fmt ? $fmt : $config['progress_format'];
		}
		if ( isset( $input['source'] ) && is_array( $input['source'] ) ) {
			$config['source'] = self::clean_source( $input['source'] );
		}

		$steps_in = $input['steps'] ?? [];
		if ( ! is_array( $steps_in ) ) {
			$errors[] = 'steps must be a list.';
			$steps_in = [];
		}
		$steps_in = array_values( $steps_in );
		if ( count( $steps_in ) > self::MAX_STEPS ) {
			$errors[] = sprintf( 'A form can have at most %d steps (got %d).', self::MAX_STEPS, count( $steps_in ) );
			$steps_in = array_slice( $steps_in, 0, self::MAX_STEPS );
		}

		$seen_keys   = [];
		$seen_roles  = [];
		$field_total = 0;
		$steps       = [];

		foreach ( $steps_in as $si => $step_in ) {
			$path    = 'steps[' . $si . ']';
			$step_in = is_array( $step_in ) ? $step_in : [];
			$step    = [
				'title'    => self::clean_text( (string) ( $step_in['title'] ?? '' ), 120 ),
				'subtitle' => self::clean_text( (string) ( $step_in['subtitle'] ?? '' ), 240 ),
				'fields'   => [],
			];

			$fields_in = $step_in['fields'] ?? [];
			if ( ! is_array( $fields_in ) ) {
				$errors[]  = $path . '.fields must be a list.';
				$fields_in = [];
			}

			foreach ( array_values( $fields_in ) as $fi => $field_in ) {
				$fpath = $path . '.fields[' . $fi . ']';
				++$field_total;
				if ( $field_total > self::MAX_FIELDS ) {
					$errors[] = sprintf( 'A form can have at most %d fields.', self::MAX_FIELDS );
					break 2;
				}
				$field = self::normalize_field( is_array( $field_in ) ? $field_in : [], $fpath, $errors, $warnings );
				if ( null === $field ) {
					continue;
				}
				if ( isset( $seen_keys[ $field['key'] ] ) ) {
					$errors[] = sprintf( '%s.key "%s" is used twice (also at %s). Keys must be unique.', $fpath, $field['key'], $seen_keys[ $field['key'] ] );
					continue;
				}
				$seen_keys[ $field['key'] ] = $fpath;

				if ( '' !== $field['role'] ) {
					if ( isset( $seen_roles[ $field['role'] ] ) ) {
						$errors[] = sprintf( '%s.role "%s" is already on field "%s". Each role may be used once.', $fpath, $field['role'], $seen_roles[ $field['role'] ] );
						$field['role'] = '';
					} else {
						$seen_roles[ $field['role'] ] = $field['key'];
					}
				}
				$step['fields'][] = $field;
			}

			if ( empty( $step['fields'] ) ) {
				$warnings[] = $path . ' has no fields and was dropped.';
				continue;
			}
			$steps[] = $step;
		}

		$config['steps'] = $steps;

		if ( ! empty( $steps ) ) {
			$roles = self::effective_roles( $config );
			if ( ! isset( $roles['phone'] ) && ! isset( $roles['email'] ) ) {
				$warnings[] = 'The form has no phone or email field, so the firm has no way to reply.';
			}
			if ( ! isset( $roles['first_name'] ) && ! isset( $roles['full_name'] ) ) {
				$warnings[] = 'No name field was found (role first_name or full_name). The Submissions list and the email will show the lead without a name.';
			}
		}

		return [
			'config'   => $config,
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * @param array<string,mixed> $in
	 * @param string[]            $errors
	 * @param string[]            $warnings
	 * @return array<string,mixed>|null Null = field dropped (error recorded).
	 */
	private static function normalize_field( array $in, string $path, array &$errors, array &$warnings ): ?array {
		$type = strtolower( trim( (string) ( $in['type'] ?? 'text' ) ) );
		$aliases = [
			'phone'    => 'tel',
			'url'      => 'text',
			'time'     => 'text',
			'multi'    => 'checkboxes',
			'consent'  => 'checkbox',
			'acceptance' => 'checkbox',
		];
		$type = $aliases[ $type ] ?? $type;
		if ( ! in_array( $type, self::TYPES, true ) ) {
			$errors[] = sprintf( '%s.type "%s" is not supported. Use one of: %s.', $path, $type, implode( ', ', self::TYPES ) );
			return null;
		}

		$label = 'checkbox' === $type
			? self::clean_inline_html( (string) ( $in['label'] ?? '' ), 500 )
			: self::clean_text( (string) ( $in['label'] ?? '' ), 200 );

		$key = strtolower( trim( (string) ( $in['key'] ?? '' ) ) );
		if ( '' === $key ) {
			$key = self::key_from_label( wp_strip_all_tags( $label ) );
			if ( '' === $key ) {
				$errors[] = $path . ' needs a key (or a label to derive one from).';
				return null;
			}
			$warnings[] = sprintf( '%s had no key; "%s" was derived from its label.', $path, $key );
		}
		if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
			$errors[] = sprintf( '%s.key "%s" must start with a letter and use only a-z, 0-9 and _ (max 40 characters).', $path, $key );
			return null;
		}
		if ( '' === $label && 'hidden' !== $type ) {
			$label = ucfirst( str_replace( '_', ' ', $key ) );
			$warnings[] = sprintf( '%s had no label; "%s" is used.', $path, $label );
		}

		$role = strtolower( trim( (string) ( $in['role'] ?? '' ) ) );
		if ( '' !== $role ) {
			if ( ! in_array( $role, self::ROLES, true ) ) {
				$errors[] = sprintf( '%s.role "%s" is unknown. Use one of: %s (or leave empty).', $path, $role, implode( ', ', self::ROLES ) );
				$role = '';
			} elseif ( ! in_array( $type, self::ROLE_TYPES[ $role ], true ) ) {
				$errors[] = sprintf( '%s.role "%s" needs a field of type %s, not %s.', $path, $role, implode( ' or ', self::ROLE_TYPES[ $role ] ), $type );
				$role = '';
			}
		}

		$width = strtolower( (string) ( $in['width'] ?? 'full' ) );
		$width = in_array( $width, [ 'half', '50', '50%' ], true ) ? 'half' : 'full';

		$field = [
			'key'         => $key,
			'type'        => $type,
			'label'       => $label,
			'placeholder' => self::clean_text( (string) ( $in['placeholder'] ?? '' ), 200 ),
			'required'    => 'hidden' === $type ? false : self::to_bool( $in['required'] ?? false ),
			'width'       => 'hidden' === $type ? 'full' : $width,
			'role'        => $role,
			'help'        => self::clean_text( (string) ( $in['help'] ?? '' ), 300 ),
			'show_label'  => self::to_bool( $in['show_label'] ?? false ),
			'default'     => self::clean_text( (string) ( $in['default'] ?? '' ), 500 ),
		];

		switch ( $type ) {
			case 'select':
			case 'radio':
			case 'checkboxes':
				$options = self::normalize_options( $in['options'] ?? [] );
				if ( count( $options ) > self::MAX_OPTIONS ) {
					$errors[] = sprintf( '%s has %d options; the limit is %d.', $path, count( $options ), self::MAX_OPTIONS );
					$options  = array_slice( $options, 0, self::MAX_OPTIONS );
				}
				if ( empty( $options ) ) {
					$errors[] = sprintf( '%s (%s) needs at least one option.', $path, $type );
					return null;
				}
				$field['options'] = $options;
				if ( '' !== $field['default'] && ! in_array( $field['default'], array_column( $options, 'value' ), true ) ) {
					$warnings[] = sprintf( '%s.default "%s" is not one of its options and was cleared.', $path, $field['default'] );
					$field['default'] = '';
				}
				break;
			case 'number':
				$field['min']  = self::to_number_or_null( $in['min'] ?? null );
				$field['max']  = self::to_number_or_null( $in['max'] ?? null );
				$field['step'] = self::to_number_or_null( $in['step'] ?? null );
				if ( null !== $field['min'] && null !== $field['max'] && $field['min'] > $field['max'] ) {
					$errors[] = $path . '.min is larger than max.';
				}
				break;
			case 'text':
				$field['maxlength'] = max( 1, min( 500, (int) ( $in['maxlength'] ?? 255 ) ) );
				break;
			case 'textarea':
				$field['maxlength'] = max( 1, min( 10000, (int) ( $in['maxlength'] ?? 5000 ) ) );
				$field['rows']      = max( 2, min( 12, (int) ( $in['rows'] ?? 4 ) ) );
				break;
			case 'tel':
				$fmt = strtolower( (string) ( $in['phone_format'] ?? 'us' ) );
				$field['phone_format'] = 'intl' === $fmt ? 'intl' : 'us';
				break;
			case 'hidden':
				if ( '' === $field['default'] ) {
					$warnings[] = sprintf( '%s is a hidden field with no default value; it will always be empty.', $path );
				}
				break;
		}

		return $field;
	}

	/**
	 * Options arrive as a list of strings, a list of {value,label}, or one
	 * newline-separated string of "Label" / "Label|value" lines (Elementor's
	 * own format). An option with an empty value is a placeholder line and is
	 * dropped — the field's placeholder carries that text instead.
	 *
	 * @param mixed $raw
	 * @return array<int,array{value:string,label:string}>
	 */
	public static function normalize_options( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\r\n|\r|\n/', $raw ) ?: [];
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out  = [];
		$seen = [];
		foreach ( $raw as $item ) {
			if ( is_array( $item ) ) {
				$label = self::clean_text( (string) ( $item['label'] ?? ( $item['value'] ?? '' ) ), 200 );
				$value = array_key_exists( 'value', $item ) ? self::clean_text( (string) $item['value'], 200 ) : $label;
			} else {
				$line = (string) $item;
				if ( false !== strpos( $line, '|' ) ) {
					[ $label, $value ] = array_map( 'trim', explode( '|', $line, 2 ) );
				} else {
					$label = trim( $line );
					$value = $label;
				}
				$label = self::clean_text( $label, 200 );
				$value = self::clean_text( $value, 200 );
			}
			if ( '' === $value ) {
				continue;
			}
			if ( '' === $label ) {
				$label = $value;
			}
			if ( isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$out[]          = [ 'value' => $value, 'label' => $label ];
		}
		return $out;
	}

	/**
	 * Explicit roles first; core roles nobody claimed are inferred from the
	 * field type / key so a hand-written or imported schema still feeds the
	 * Submissions columns.
	 *
	 * @param array<string,mixed> $config
	 * @return array<string,string> role => field key
	 */
	public static function effective_roles( array $config ): array {
		$fields = self::all_fields( $config );
		$roles  = [];
		$taken  = [];
		foreach ( $fields as $f ) {
			if ( '' !== ( $f['role'] ?? '' ) ) {
				$roles[ $f['role'] ] = $f['key'];
				$taken[ $f['key'] ]  = true;
			}
		}

		$key_hints = [
			'first_name' => [ 'fname', 'first_name', 'firstname', 'first' ],
			'last_name'  => [ 'lname', 'last_name', 'lastname', 'last', 'surname' ],
			'full_name'  => [ 'name', 'full_name', 'fullname', 'your_name' ],
			'message'    => [ 'message', 'situation', 'description', 'details', 'comments', 'comment', 'case_details', 'how_can_we_help' ],
		];
		foreach ( $key_hints as $role => $keys ) {
			if ( isset( $roles[ $role ] ) ) {
				continue;
			}
			foreach ( $fields as $f ) {
				if ( isset( $taken[ $f['key'] ] ) || ! in_array( $f['type'], self::ROLE_TYPES[ $role ], true ) ) {
					continue;
				}
				if ( in_array( $f['key'], $keys, true ) ) {
					$roles[ $role ]     = $f['key'];
					$taken[ $f['key'] ] = true;
					break;
				}
			}
		}

		$type_hints = [ 'phone' => 'tel', 'email' => 'email', 'message' => 'textarea' ];
		foreach ( $type_hints as $role => $type ) {
			if ( isset( $roles[ $role ] ) ) {
				continue;
			}
			foreach ( $fields as $f ) {
				if ( ! isset( $taken[ $f['key'] ] ) && $type === $f['type'] ) {
					$roles[ $role ]     = $f['key'];
					$taken[ $f['key'] ] = true;
					break;
				}
			}
		}

		return $roles;
	}

	/* -----------------------------------------------------------------
	 * Submission parsing
	 * ----------------------------------------------------------------- */

	/**
	 * Parse + validate a submitted custom form against the stored schema.
	 *
	 * Only keys in the schema are read; hidden fields always take their
	 * configured value (never the browser's), so a tampered POST cannot
	 * rewrite a lead-source tag.
	 *
	 * @param mixed               $raw    $_POST[POST_KEY], already unslashed.
	 * @param array<string,mixed> $config Normalized custom config.
	 * @return array{values:array<string,string>, errors:array<string,string>, rows:array<int,array{key:string,label:string,value:string,type:string}>, core:array<string,string>}
	 */
	public static function collect( $raw, array $config ): array {
		$raw    = is_array( $raw ) ? $raw : [];
		$values = [];
		$errors = [];
		$rows   = [];

		foreach ( self::all_fields( $config ) as $field ) {
			$key   = (string) $field['key'];
			$type  = (string) $field['type'];
			$input = $raw[ $key ] ?? null;
			$value = '';
			$error = '';

			switch ( $type ) {
				case 'hidden':
					$value = (string) ( $field['default'] ?? '' );
					break;

				case 'textarea':
					$value = is_scalar( $input ) ? self::plain_text( (string) $input, true ) : '';
					$value = mb_substr( $value, 0, (int) ( $field['maxlength'] ?? 5000 ) );
					break;

				case 'email':
					$value = is_scalar( $input ) ? sanitize_email( trim( (string) $input ) ) : '';
					$value = substr( $value, 0, 255 );
					if ( '' === $value && is_scalar( $input ) && '' !== trim( (string) $input ) ) {
						$error = __( 'Please enter a valid email address.', 'bspe-connect' );
					} elseif ( '' !== $value && ! is_email( $value ) ) {
						$error = __( 'Please enter a valid email address.', 'bspe-connect' );
					}
					break;

				case 'tel':
					$digits = is_scalar( $input ) ? ( preg_replace( '/\D/', '', (string) $input ) ?? '' ) : '';
					if ( 'intl' === ( $field['phone_format'] ?? 'us' ) ) {
						if ( '' !== $digits && ( strlen( $digits ) < 7 || strlen( $digits ) > 15 ) ) {
							$error = __( 'Please enter a valid phone number.', 'bspe-connect' );
						}
					} else {
						if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
							$digits = substr( $digits, 1 );
						}
						if ( '' !== $digits && 10 !== strlen( $digits ) ) {
							$error = __( 'Please enter a 10-digit US phone number.', 'bspe-connect' );
						}
					}
					$value = substr( $digits, 0, 20 );
					break;

				case 'number':
					$clean = is_scalar( $input ) ? ( preg_replace( '/[^0-9.\-]/', '', (string) $input ) ?? '' ) : '';
					if ( '' !== $clean ) {
						if ( ! is_numeric( $clean ) ) {
							$error = __( 'Please enter a number.', 'bspe-connect' );
						} else {
							$num = (float) $clean;
							$min = $field['min'] ?? null;
							$max = $field['max'] ?? null;
							if ( ( null !== $min && $num < $min ) || ( null !== $max && $num > $max ) ) {
								$error = self::range_message( $min, $max );
							}
						}
					}
					$value = substr( $clean, 0, 40 );
					break;

				case 'select':
				case 'radio':
					$candidate = is_scalar( $input ) ? self::plain_text( (string) $input ) : '';
					$allowed   = array_column( (array) ( $field['options'] ?? [] ), 'value' );
					if ( '' !== $candidate && ! in_array( $candidate, $allowed, true ) ) {
						$error     = __( 'Please choose one of the options.', 'bspe-connect' );
						$candidate = '';
					}
					$value = $candidate;
					break;

				case 'checkboxes':
					// Scalars only: a crafted bspe_f[key][0][]=x must not raise
					// "Array to string conversion" into the AJAX response.
					$picked  = is_array( $input ) ? array_map( 'strval', array_filter( $input, 'is_scalar' ) ) : ( is_scalar( $input ) && '' !== (string) $input ? [ (string) $input ] : [] );
					$allowed = array_column( (array) ( $field['options'] ?? [] ), 'value' );
					$picked  = array_values( array_intersect( $allowed, array_map( [ self::class, 'plain_text' ], $picked ) ) );
					$value   = implode( ', ', $picked );
					break;

				case 'checkbox':
					$value = ( is_scalar( $input ) && '' !== (string) $input && '0' !== (string) $input ) ? __( 'Yes', 'bspe-connect' ) : '';
					break;

				case 'date':
					$candidate = is_scalar( $input ) ? trim( (string) $input ) : '';
					if ( '' !== $candidate ) {
						if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $candidate, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
							$value = $candidate;
						} else {
							$error = __( 'Please enter a valid date.', 'bspe-connect' );
						}
					}
					break;

				case 'text':
				default:
					$value = is_scalar( $input ) ? self::plain_text( (string) $input ) : '';
					$value = mb_substr( $value, 0, (int) ( $field['maxlength'] ?? 255 ) );
					break;
			}

			if ( '' === $error && ! empty( $field['required'] ) && '' === trim( $value ) ) {
				$error = self::required_message( $type );
			}

			if ( '' !== $error ) {
				$errors[ $key ] = $error;
			}

			$values[ $key ] = $value;
			$rows[]         = [
				'key'   => $key,
				'label' => self::plain_label( $field ),
				'value' => $value,
				'type'  => $type,
			];
		}

		return [
			'values' => $values,
			'errors' => $errors,
			'rows'   => $rows,
			'core'   => self::core_values( $values, $config ),
		];
	}

	/**
	 * Map role-tagged values onto the plugin's core lead fields.
	 *
	 * @param array<string,string> $values
	 * @param array<string,mixed>  $config
	 * @return array<string,string>
	 */
	public static function core_values( array $values, array $config ): array {
		$roles = self::effective_roles( $config );
		$get   = static function ( string $role ) use ( $roles, $values ): string {
			return isset( $roles[ $role ] ) ? (string) ( $values[ $roles[ $role ] ] ?? '' ) : '';
		};

		$first = $get( 'first_name' );
		$last  = $get( 'last_name' );
		$full  = $get( 'full_name' );
		if ( '' === $full ) {
			$full = trim( $first . ' ' . $last );
		}
		if ( '' === $first && '' === $last && '' !== $full ) {
			[ $first, $last ] = self::split_name( $full );
		}

		$pref = strtolower( $get( 'contact_pref' ) );

		return [
			'name'         => mb_substr( $full, 0, 255 ),
			'first_name'   => $first,
			'last_name'    => $last,
			'phone'        => $get( 'phone' ),
			'email'        => $get( 'email' ),
			'message'      => $get( 'message' ),
			'contact_pref' => mb_substr( $pref, 0, 20 ),
		];
	}

	/**
	 * "Label: value" lines for every answered field — handy as a single CRM
	 * note and used verbatim by the webhook's `summary` key.
	 *
	 * @param array<int,array{key:string,label:string,value:string,type:string}> $rows
	 */
	public static function summary( array $rows ): string {
		$lines = [];
		foreach ( $rows as $row ) {
			if ( '' === trim( (string) $row['value'] ) ) {
				continue;
			}
			$value = 'tel' === $row['type'] ? self::format_phone( (string) $row['value'] ) : (string) $row['value'];
			$lines[] = $row['label'] . ': ' . $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Example values for a test webhook / preview, in schema order.
	 *
	 * @param array<string,mixed> $config
	 * @return array<string,string>
	 */
	public static function sample_values( array $config ): array {
		$out = [];
		foreach ( self::all_fields( $config ) as $f ) {
			$key = (string) $f['key'];
			switch ( $f['type'] ) {
				case 'email':
					$out[ $key ] = 'test.lead@example.com';
					break;
				case 'tel':
					$out[ $key ] = '5555550123';
					break;
				case 'number':
					$out[ $key ] = '50000';
					break;
				case 'date':
					$out[ $key ] = gmdate( 'Y-m-d' );
					break;
				case 'checkbox':
					$out[ $key ] = 'Yes';
					break;
				case 'select':
				case 'radio':
				case 'checkboxes':
					$out[ $key ] = (string) ( $f['options'][0]['value'] ?? '' );
					break;
				case 'hidden':
					$out[ $key ] = (string) ( $f['default'] ?? '' );
					break;
				case 'textarea':
					$out[ $key ] = 'This is a test submission sent from BSPE Connect to check the webhook mapping.';
					break;
				default:
					$hints = [ 'fname' => 'Test', 'first_name' => 'Test', 'lname' => 'Lead', 'last_name' => 'Lead', 'city' => 'Los Angeles' ];
					$out[ $key ] = $hints[ $key ] ?? 'Test ' . str_replace( '_', ' ', $key );
			}
		}
		return $out;
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	public static function format_phone( string $digits ): string {
		$d = preg_replace( '/\D/', '', $digits ) ?? '';
		if ( 10 === strlen( $d ) ) {
			return sprintf( '(%s) %s-%s', substr( $d, 0, 3 ), substr( $d, 3, 3 ), substr( $d, 6, 4 ) );
		}
		return $d;
	}

	/**
	 * +1XXXXXXXXXX for a 10-digit US number; '' otherwise. CRMs (Lawmatics)
	 * that guess the country of a bare 10-digit string get it right from
	 * this one.
	 */
	public static function phone_e164( string $digits ): string {
		$d = preg_replace( '/\D/', '', $digits ) ?? '';
		if ( 11 === strlen( $d ) && '1' === $d[0] ) {
			$d = substr( $d, 1 );
		}
		return 10 === strlen( $d ) ? '+1' . $d : '';
	}

	/**
	 * @return array{0:string,1:string}
	 */
	public static function split_name( string $full ): array {
		$full = trim( preg_replace( '/\s+/', ' ', $full ) ?? '' );
		if ( '' === $full ) {
			return [ '', '' ];
		}
		$parts = explode( ' ', $full, 2 );
		return [ $parts[0], $parts[1] ?? '' ];
	}

	/**
	 * @param array<string,mixed> $field
	 */
	public static function plain_label( array $field ): string {
		$label = trim( wp_strip_all_tags( (string) ( $field['label'] ?? '' ) ) );
		if ( '' === $label ) {
			$label = ucfirst( str_replace( '_', ' ', (string) ( $field['key'] ?? '' ) ) );
		}
		return $label;
	}

	public static function key_from_label( string $label ): string {
		$key = strtolower( remove_accents( $label ) );
		$key = preg_replace( '/[^a-z0-9]+/', '_', $key ) ?? '';
		$key = trim( $key, '_' );
		if ( '' !== $key && ! preg_match( '/^[a-z]/', $key ) ) {
			$key = 'f_' . $key;
		}
		return substr( $key, 0, 40 );
	}

	private static function required_message( string $type ): string {
		switch ( $type ) {
			case 'select':
			case 'radio':
			case 'checkboxes':
				return __( 'Please make a selection.', 'bspe-connect' );
			case 'checkbox':
				return __( 'Please check this box to continue.', 'bspe-connect' );
			default:
				return __( 'This field is required.', 'bspe-connect' );
		}
	}

	/**
	 * @param float|null $min
	 * @param float|null $max
	 */
	private static function range_message( $min, $max ): string {
		if ( null !== $min && null !== $max ) {
			/* translators: 1: minimum, 2: maximum */
			return sprintf( __( 'Please enter a number between %1$s and %2$s.', 'bspe-connect' ), self::num( $min ), self::num( $max ) );
		}
		if ( null !== $min ) {
			/* translators: %s: minimum */
			return sprintf( __( 'Please enter a number of at least %s.', 'bspe-connect' ), self::num( $min ) );
		}
		/* translators: %s: maximum */
		return sprintf( __( 'Please enter a number no greater than %s.', 'bspe-connect' ), self::num( (float) $max ) );
	}

	private static function num( float $n ): string {
		return ( floor( $n ) === $n ) ? number_format( $n, 0, '.', ',' ) : (string) $n;
	}

	private static function clean_text( string $raw, int $max ): string {
		return mb_substr( self::plain_text( $raw ), 0, $max );
	}

	/**
	 * Tag-free plain text. Unlike sanitize_text_field() it does NOT turn a
	 * lone "<" into "&lt;" — a "< $50,000" salary option or a visitor's
	 * "salary < 50k" must reach the email and the CRM as typed. Every
	 * output path escapes for its own context (esc_html, JSON, prepared SQL).
	 */
	public static function plain_text( string $raw, bool $multiline = false ): string {
		$raw = wp_check_invalid_utf8( $raw );
		$raw = wp_strip_all_tags( $raw, false );
		$raw = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw ) ?? '';
		if ( $multiline ) {
			$raw = str_replace( [ "\r\n", "\r" ], "\n", $raw );
			$raw = preg_replace( '/[ \t]+/', ' ', $raw ) ?? '';
			$raw = preg_replace( '/\n{3,}/', "\n\n", $raw ) ?? '';
			return trim( $raw );
		}
		$raw = preg_replace( '/\s+/u', ' ', $raw ) ?? '';
		return trim( $raw );
	}

	/**
	 * Checkbox labels may carry a link to the privacy policy / terms.
	 */
	private static function clean_inline_html( string $raw, int $max ): string {
		$allowed = [
			'a'      => [ 'href' => [], 'target' => [], 'rel' => [] ],
			'strong' => [],
			'b'      => [],
			'em'     => [],
			'i'      => [],
		];
		$clean = trim( wp_kses( $raw, $allowed ) );
		$clean = preg_replace( '/\s+/', ' ', $clean ) ?? '';
		return mb_substr( $clean, 0, $max );
	}

	/**
	 * @param mixed $value
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}
		return in_array( strtolower( trim( (string) $value ) ), [ 'true', 'yes', 'on', '1', 'y' ], true );
	}

	/**
	 * @param mixed $value
	 */
	private static function to_number_or_null( $value ): ?float {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}
		return (float) $value;
	}

	/**
	 * @param array<string,mixed> $source
	 * @return array<string,mixed>
	 */
	private static function clean_source( array $source ): array {
		$out = [];
		if ( isset( $source['type'] ) ) {
			$out['type'] = sanitize_key( (string) $source['type'] );
		}
		if ( isset( $source['post_id'] ) ) {
			$out['post_id'] = absint( $source['post_id'] );
		}
		if ( isset( $source['form_id'] ) ) {
			$out['form_id'] = sanitize_key( (string) $source['form_id'] );
		}
		if ( isset( $source['template_id'] ) ) {
			$out['template_id'] = absint( $source['template_id'] );
		}
		if ( isset( $source['form_name'] ) ) {
			$out['form_name'] = self::clean_text( (string) $source['form_name'], 120 );
		}
		if ( isset( $source['imported_at'] ) ) {
			$out['imported_at'] = self::clean_text( (string) $source['imported_at'], 40 );
		}
		return $out;
	}
}
