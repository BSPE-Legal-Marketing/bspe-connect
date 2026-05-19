<?php
/**
 * In-Post Widget tab — inject a saved shortcode after the Nth
 * paragraph of post / page content.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;

$cfg        = is_array( Settings::get( 'in_post_widget', [] ) ) ? Settings::get( 'in_post_widget', [] ) : [];
$action_url = admin_url( 'admin-post.php' );

$post_types = isset( $cfg['post_types'] ) && is_array( $cfg['post_types'] ) ? $cfg['post_types'] : [ 'post' ];

Components::open_form( 'in_post_widget', $action_url );

/* ----------------- Enable toggle ----------------- */
Components::open_card(
	__( 'In-Post Widget', 'bspe-connect' ),
	__( 'Inject a saved shortcode into post or page content at a chosen paragraph position. Commonly used to drop an Elementor template, opt-in form, or CTA right after the article intro — the spot where reader engagement peaks.', 'bspe-connect' )
);
Components::row(
	__( 'Enable', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::toggle( 'bspe[in_post_widget][enabled]', ! empty( $cfg['enabled'] ), [
			'label' => __( 'Insert the widget into matching posts / pages', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'When off, the plugin doesn\'t touch <code>the_content</code> at all — zero impact on page rendering.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Shortcode ----------------- */
Components::open_card(
	__( 'Shortcode', 'bspe-connect' ),
	__( 'Paste any shortcode supported by this site. Elementor templates use <code>[elementor-template id="123"]</code>; other page builders have their own. The output is rendered through WordPress\'s standard <code>do_shortcode</code> pipeline.', 'bspe-connect' )
);
Components::row(
	__( 'Shortcode to inject', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::textarea( 'bspe[in_post_widget][shortcode]', (string) ( $cfg['shortcode'] ?? '' ), [
			'rows'        => 4,
			'placeholder' => '[elementor-template id="123"]',
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-shortcode',
		'description' => __( 'Test the shortcode on a real post before relying on it. If the shortcode renders nothing, the widget will silently render nothing.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Position ----------------- */
Components::open_card(
	__( 'Placement', 'bspe-connect' ),
	__( 'Where the widget appears within the post body.', 'bspe-connect' )
);
Components::row(
	__( 'After paragraph #', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::number( 'bspe[in_post_widget][after_paragraph]', (int) ( $cfg['after_paragraph'] ?? 1 ), [
			'min'  => 1,
			'max'  => 10,
			'step' => 1,
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-after_paragraph',
		'description' => __( 'Default 1 — right after the article\'s opening paragraph. Counts <code>&lt;/p&gt;</code> tags in the rendered content. If the article has fewer paragraphs than this number, the widget appends at the end instead.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'Apply to', 'bspe-connect' ),
	static function () use ( $post_types ): void {
		?>
		<label class="bspe-check">
			<input type="checkbox" name="bspe[in_post_widget][post_types][]" value="post" <?php checked( in_array( 'post', $post_types, true ) ); ?> />
			<span><?php esc_html_e( 'Blog posts', 'bspe-connect' ); ?></span>
		</label>
		<label class="bspe-check" style="margin-left: 16px;">
			<input type="checkbox" name="bspe[in_post_widget][post_types][]" value="page" <?php checked( in_array( 'page', $post_types, true ) ); ?> />
			<span><?php esc_html_e( 'Pages', 'bspe-connect' ); ?></span>
		</label>
		<?php
	},
	[
		'description' => __( 'Default: posts only. Enable pages if you also want the widget on static pages.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'Exclude post IDs', 'bspe-connect' ),
	static function () use ( $cfg ): void {
		Components::text( 'bspe[in_post_widget][exclude_ids]', (string) ( $cfg['exclude_ids'] ?? '' ), [
			'placeholder' => '12, 34, 56',
		] );
	},
	[
		'id'          => 'bspe-in_post_widget-exclude_ids',
		'description' => __( 'Comma-separated post IDs that should never receive the widget — useful for articles where the widget would be redundant or off-tone.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();
