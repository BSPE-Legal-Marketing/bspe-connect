<?php
/**
 * Design settings tab — firm name, color pickers, font selection.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Theme_Palette;
use BSPE\Connect\Admin\Components;

$design     = is_array( Settings::get( 'design', [] ) ) ? Settings::get( 'design', [] ) : [];
$colors     = is_array( $design['colors'] ?? null ) ? $design['colors'] : [];
$action_url = admin_url( 'admin-post.php' );

$google_fonts = [
	'DM Sans'           => 'DM Sans',
	'Inter'             => 'Inter',
	'Lato'              => 'Lato',
	'Roboto'            => 'Roboto',
	'Open Sans'         => 'Open Sans',
	'Source Sans 3'     => 'Source Sans 3',
	'Poppins'           => 'Poppins',
	'Manrope'           => 'Manrope',
	'Nunito'            => 'Nunito',
	'Work Sans'         => 'Work Sans',
	'Plus Jakarta Sans' => 'Plus Jakarta Sans',
	'IBM Plex Sans'     => 'IBM Plex Sans',
	'Figtree'           => 'Figtree',
	'Montserrat'        => 'Montserrat',
	'Public Sans'       => 'Public Sans',
];

Components::open_form( 'design', $action_url );

/* ----------------- Firm name ----------------- */
Components::open_card(
	__( 'Firm', 'bspe-connect' ),
	__( 'Used in template variables like {firm_name}.', 'bspe-connect' )
);
Components::row(
	__( 'Firm name', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::text( 'bspe[design][firm_name]', (string) ( $design['firm_name'] ?? '' ), [
			'placeholder' => __( 'Yourfirm Legal', 'bspe-connect' ),
			'maxlength'   => 120,
		] );
	},
	[
		'id'          => 'bspe-design-firm_name',
		'description' => __( 'If empty, falls back to the WordPress site title.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Colors ----------------- */
Components::open_card(
	__( 'Colors', 'bspe-connect' ),
	__( 'Per-surface colors used by the bar, buttons, welcome bubble, and form modal. Hex values; 3- and 6-digit accepted.', 'bspe-connect' )
);

$color_rows = [
	[ 'bar_bg',    __( 'Bar background', 'bspe-connect' ),     '#351E28' ],
	[ 'button_bg', __( 'Button background', 'bspe-connect' ),  '#351E28' ],
	[ 'button_fg', __( 'Button text & icon', 'bspe-connect' ), '#FAF7F2' ],
	[ 'bubble_bg', __( 'Bubble background', 'bspe-connect' ),  '#FAF7F2' ],
	[ 'bubble_fg', __( 'Bubble text', 'bspe-connect' ),        '#351E28' ],
	[ 'accent',    __( 'Accent (focus, CTA)', 'bspe-connect' ),'#3AAFB9' ],
];
foreach ( $color_rows as $row ) {
	[ $key, $label, $default ] = $row;
	Components::row(
		$label,
		static function () use ( $key, $colors, $default ): void {
			Components::color( 'bspe[design][colors][' . $key . ']', (string) ( $colors[ $key ] ?? $default ) );
		},
		[ 'id' => 'bspe-design-colors-' . $key ]
	);
}

/* -- Quick presets: plugin defaults / map from site palette ------------- */
$plugin_defaults_json = wp_json_encode(
	array_combine(
		array_map( static fn( $row ) => $row[0], $color_rows ),
		array_map( static fn( $row ) => $row[2], $color_rows )
	)
);
$palette          = Theme_Palette::detect();
$palette_disabled = 'none' === $palette['source'] || empty( $palette['colors'] );

?>
<div class="bspe-palette-presets">
	<button type="button"
		class="bspe-btn bspe-btn--ghost"
		data-bspe-preset-defaults
		data-bspe-defaults="<?php echo esc_attr( (string) $plugin_defaults_json ); ?>"
	>
		<?php esc_html_e( 'Use Plugin Default Colors', 'bspe-connect' ); ?>
	</button>

	<button type="button"
		class="bspe-btn bspe-btn--ghost<?php echo $palette_disabled ? ' is-disabled' : ''; ?>"
		data-bspe-preset-theme
		<?php echo $palette_disabled ? 'disabled aria-disabled="true"' : ''; ?>
		<?php if ( $palette_disabled ) : ?>
			title="<?php esc_attr_e( 'No palette detected. Install Elementor or use a block theme to enable this.', 'bspe-connect' ); ?>"
		<?php else : ?>
			aria-controls="bspe-palette-panel"
			aria-expanded="false"
		<?php endif; ?>
	>
		<?php esc_html_e( 'Use Default Website Colors', 'bspe-connect' ); ?>
		<?php if ( ! $palette_disabled ) : ?>
			<span class="bspe-palette-presets__source">
				—
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: source name (Elementor / Block theme), 2: color count */
						_n( '%1$s, %2$d color', '%1$s, %2$d colors', count( $palette['colors'] ), 'bspe-connect' ),
						$palette['label'],
						count( $palette['colors'] )
					)
				);
				?>
			</span>
		<?php endif; ?>
	</button>
</div>

<?php if ( ! $palette_disabled ) : ?>
	<div class="bspe-palette-panel" id="bspe-palette-panel" data-bspe-palette-panel hidden>
		<div class="bspe-palette-panel__header">
			<h3 class="bspe-palette-panel__title">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: source name */
						__( 'Map from your %s palette', 'bspe-connect' ),
						$palette['label']
					)
				);
				?>
			</h3>
			<p class="bspe-palette-panel__hint">
				<?php esc_html_e( 'Pick which of your palette colors should fill each BSPE Connect color. Nothing is saved until you click Apply and then Save changes.', 'bspe-connect' ); ?>
			</p>
		</div>

		<div class="bspe-palette-panel__grid">
			<?php foreach ( $color_rows as $row ) :
				[ $key, $label, $default ] = $row;
				?>
				<div class="bspe-palette-row" data-bspe-palette-row="<?php echo esc_attr( $key ); ?>">
					<div class="bspe-palette-row__head">
						<span class="bspe-palette-row__label"><?php echo esc_html( $label ); ?></span>
						<span class="bspe-palette-row__caption" data-bspe-palette-caption="<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( '— Pick a color —', 'bspe-connect' ); ?>
						</span>
					</div>
					<input type="hidden"
						class="bspe-palette-row__value"
						data-bspe-palette-select="<?php echo esc_attr( $key ); ?>"
						value=""
					/>
					<div class="bspe-palette-row__chips" role="radiogroup" aria-label="<?php echo esc_attr( $label ); ?>">
						<?php foreach ( $palette['colors'] as $color ) :
							$value = (string) $color['value'];
							$name  = (string) $color['label'];
							$title = sprintf( '%s — %s', $name, strtoupper( $value ) );
							?>
							<button type="button"
								class="bspe-palette-chip"
								role="radio"
								aria-checked="false"
								aria-label="<?php echo esc_attr( $title ); ?>"
								title="<?php echo esc_attr( $title ); ?>"
								data-bspe-palette-chip="<?php echo esc_attr( $key ); ?>"
								data-value="<?php echo esc_attr( $value ); ?>"
								data-label="<?php echo esc_attr( $name ); ?>"
							>
								<span class="bspe-palette-chip__swatch" style="background:<?php echo esc_attr( $value ); ?>;"></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="bspe-palette-panel__actions">
			<button type="button" class="bspe-btn bspe-btn--ghost" data-bspe-palette-cancel>
				<?php esc_html_e( 'Cancel', 'bspe-connect' ); ?>
			</button>
			<button type="button" class="bspe-btn bspe-btn--primary" data-bspe-palette-apply>
				<?php esc_html_e( 'Apply to color pickers', 'bspe-connect' ); ?>
			</button>
		</div>
	</div>
<?php endif;

Components::close_card();

/* ----------------- Sizing & layout ----------------- */
Components::open_card(
	__( 'Sizing & layout', 'bspe-connect' ),
	__( 'Fine-tune the bar button icon size, label size, padding, and the gap between icon and label.', 'bspe-connect' )
);
Components::row(
	__( 'Icon size', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][icon_size]', (int) ( $design['icon_size'] ?? 16 ), [
			'min'    => 12,
			'max'    => 48,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-icon_size',
		'description' => __( 'Default 16 px. Applies to every bar button icon.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Label size', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][label_size]', (int) ( $design['label_size'] ?? 12 ), [
			'min'    => 8,
			'max'    => 20,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-label_size',
		'description' => __( 'Default 12 px. Adjusts the text under each icon.', 'bspe-connect' ),
	]
);
// Per-side button padding. Rendered as a single row with four mini inputs
// labelled T / R / B / L so the four controls stay together at a glance
// instead of taking four full settings rows. Legacy button_padding_y
// (pre-v2.2.0) populates top + bottom on first read so upgrading installs
// don't snap back to defaults.
$legacy_pad_y = (int) ( $design['button_padding_y'] ?? -1 );
$pad_defaults = [
	'top'    => $legacy_pad_y >= 0 ? $legacy_pad_y : 6,
	'right'  => 4,
	'bottom' => $legacy_pad_y >= 0 ? $legacy_pad_y : 6,
	'left'   => 4,
];
$pad_sides = [
	'top'    => [ 'label' => __( 'Top', 'bspe-connect' ),    'short' => __( 'T', 'bspe-connect' ) ],
	'right'  => [ 'label' => __( 'Right', 'bspe-connect' ),  'short' => __( 'R', 'bspe-connect' ) ],
	'bottom' => [ 'label' => __( 'Bottom', 'bspe-connect' ), 'short' => __( 'B', 'bspe-connect' ) ],
	'left'   => [ 'label' => __( 'Left', 'bspe-connect' ),   'short' => __( 'L', 'bspe-connect' ) ],
];
Components::row(
	__( 'Button padding', 'bspe-connect' ),
	static function () use ( $design, $pad_defaults, $pad_sides ): void {
		echo '<div class="bspe-pad-grid" role="group" aria-label="' . esc_attr__( 'Button padding (top, right, bottom, left)', 'bspe-connect' ) . '">';
		foreach ( $pad_sides as $side => $meta ) {
			$value = (int) ( $design[ 'button_padding_' . $side ] ?? $pad_defaults[ $side ] );
			$id    = 'bspe-design-button_padding_' . $side;
			echo '<div class="bspe-pad-grid__cell">';
			printf(
				'<label class="bspe-pad-grid__label" for="%1$s" title="%2$s"><span class="bspe-pad-grid__short" aria-hidden="true">%3$s</span><span class="screen-reader-text">%2$s</span></label>',
				esc_attr( $id ),
				esc_attr( $meta['label'] ),
				esc_html( $meta['short'] )
			);
			printf(
				'<input type="number" id="%1$s" class="bspe-input bspe-input--number bspe-pad-grid__input" name="bspe[design][button_padding_%2$s]" value="%3$d" min="0" max="32" step="1" />',
				esc_attr( $id ),
				esc_attr( $side ),
				$value
			);
			echo '<span class="bspe-pad-grid__suffix">' . esc_html__( 'px', 'bspe-connect' ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	},
	[
		'description' => __( 'Top, right, bottom, left padding inside each bar button. Defaults: 6 / 4 / 6 / 4 px.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Icon ↔ label gap', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::number( 'bspe[design][icon_label_gap]', (int) ( $design['icon_label_gap'] ?? 2 ), [
			'min'    => 0,
			'max'    => 16,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-design-icon_label_gap',
		'description' => __( 'Default 2 px. Vertical space between the icon and the label below it.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Label style ----------------- */
Components::open_card(
	__( 'Label style', 'bspe-connect' ),
	__( 'Weight and casing for the label under each bar button.', 'bspe-connect' )
);
Components::row(
	__( 'Label weight', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::select(
			'bspe[design][label_weight]',
			(string) (int) ( $design['label_weight'] ?? 500 ),
			[
				'400' => __( 'Regular (400)',  'bspe-connect' ),
				'500' => __( 'Medium (500) — default', 'bspe-connect' ),
				'600' => __( 'Semibold (600)', 'bspe-connect' ),
				'700' => __( 'Bold (700)',     'bspe-connect' ),
			]
		);
	},
	[
		'id'          => 'bspe-design-label_weight',
		'description' => __( 'Heavier labels feel more like calls-to-action; lighter labels feel quieter.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Uppercase labels', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::toggle( 'bspe[design][label_uppercase]', ! empty( $design['label_uppercase'] ?? true ), [
			'label' => __( 'Render labels in UPPERCASE on the bar', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'On by default for the brand look. Turn off to render labels in their saved case.', 'bspe-connect' ) ]
);
Components::close_card();

/* ----------------- Typography ----------------- */
Components::open_card(
	__( 'Typography', 'bspe-connect' ),
	__( 'Inherit from the active theme, or load a curated Google Font for the bar.', 'bspe-connect' )
);
Components::row(
	__( 'Font source', 'bspe-connect' ),
	static function () use ( $design ): void {
		Components::radio_pills( 'bspe[design][font_mode]', (string) ( $design['font_mode'] ?? 'inherit' ), [
			'inherit' => __( 'Inherit from theme', 'bspe-connect' ),
			'google'  => __( 'Google Font', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Google Font', 'bspe-connect' ),
	static function () use ( $design, $google_fonts ): void {
		Components::select( 'bspe[design][google_font]', (string) ( $design['google_font'] ?? 'DM Sans' ), $google_fonts );
	},
	[
		'id'          => 'bspe-design-google_font',
		'description' => __( 'Loaded from fonts.googleapis.com when Font source is Google Font.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
