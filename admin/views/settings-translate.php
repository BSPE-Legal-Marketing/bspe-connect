<?php
/**
 * Translate tab — Claude-powered page translation for WPML sites.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Claude_Client;
use BSPE\Connect\Admin\Components;
use BSPE\Connect\Admin\Translate_Controller;

$translate  = is_array( Settings::get( 'translate', [] ) ) ? Settings::get( 'translate', [] ) : [];
$action_url = admin_url( 'admin-post.php' );
$key        = (string) ( $translate['api_key'] ?? '' );
$key_hint   = '' === $key ? '' : str_repeat( '•', 12 ) . substr( $key, -4 );
$available  = Translate_Controller::available();
$blockers   = Translate_Controller::blockers();
$default_tg = (string) ( $translate['default_target'] ?? 'es' );

/* ----------------- Workflow ----------------- */
Components::open_card(
	__( 'Translate a page', 'bspe-connect' ),
	__( 'Paste the URL of a page in the site\'s default language. BSPE Connect reads everything WPML would translate (title, URL slug, excerpt, content, Elementor widget text, SEOPress title and description, translatable custom fields), sends the text to Claude in chunks, and creates the WPML-linked translation with the layout intact.', 'bspe-connect' )
);
if ( ! $available ) : ?>
	<div class="bspe-row">
		<div class="bspe-row__label-col"><span class="bspe-row__label"><?php esc_html_e( 'Not available yet', 'bspe-connect' ); ?></span></div>
		<div class="bspe-row__control-col">
			<ul class="bspe-translate__blockers">
				<?php foreach ( $blockers as $b ) : ?>
					<li><?php echo esc_html( $b ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
<?php else : ?>
	<div class="bspe-translate" data-bspe-translate data-default-target="<?php echo esc_attr( $default_tg ); ?>">
		<div class="bspe-row">
			<div class="bspe-row__label-col">
				<label for="bspe-translate-url" class="bspe-row__label"><?php esc_html_e( 'Page URL', 'bspe-connect' ); ?></label>
				<p class="bspe-row__description"><?php esc_html_e( 'Any post, page or public custom post type on this site. The page is looked up but nothing is changed yet.', 'bspe-connect' ); ?></p>
			</div>
			<div class="bspe-row__control-col">
				<div class="bspe-translate__urlrow">
					<input type="url" id="bspe-translate-url" class="bspe-input" placeholder="<?php echo esc_attr( home_url( '/personal-injury-lawyer/' ) ); ?>" data-bspe-translate-url />
					<button type="button" class="bspe-button bspe-button--secondary" data-bspe-translate-lookup><?php esc_html_e( 'Add', 'bspe-connect' ); ?></button>
				</div>
				<p class="bspe-translate__error" role="alert" hidden data-bspe-translate-error></p>
			</div>
		</div>

		<div class="bspe-row" hidden data-bspe-translate-panel>
			<div class="bspe-row__label-col">
				<span class="bspe-row__label"><?php esc_html_e( 'Page', 'bspe-connect' ); ?></span>
			</div>
			<div class="bspe-row__control-col">
				<div class="bspe-translate__page">
					<strong data-bspe-translate-title></strong>
					<span class="bspe-translate__meta" data-bspe-translate-meta></span>
					<a href="#" target="_blank" rel="noopener" data-bspe-translate-edit><?php esc_html_e( 'Edit original', 'bspe-connect' ); ?></a>
				</div>
				<p class="bspe-translate__warn" hidden data-bspe-translate-existing></p>
				<p class="bspe-translate__warn" hidden data-bspe-translate-langwarn></p>
			</div>
		</div>

		<div class="bspe-row" hidden data-bspe-translate-panel>
			<div class="bspe-row__label-col">
				<label for="bspe-translate-target" class="bspe-row__label"><?php esc_html_e( 'Translate into', 'bspe-connect' ); ?></label>
			</div>
			<div class="bspe-row__control-col">
				<div class="bspe-translate__actions">
					<div class="bspe-select-wrap">
						<select id="bspe-translate-target" class="bspe-input bspe-select" data-bspe-translate-target>
							<?php foreach ( Claude_Client::LANGUAGES as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $default_tg ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<label class="bspe-translate__overwrite" hidden data-bspe-translate-overwrite-wrap>
						<input type="checkbox" data-bspe-translate-overwrite />
						<?php esc_html_e( 'Overwrite the existing translation', 'bspe-connect' ); ?>
					</label>
					<button type="button" class="bspe-button bspe-button--primary" data-bspe-translate-start><?php esc_html_e( 'Translate', 'bspe-connect' ); ?></button>
					<button type="button" class="bspe-button bspe-button--ghost" hidden data-bspe-translate-cancel><?php esc_html_e( 'Cancel', 'bspe-connect' ); ?></button>
				</div>

				<div class="bspe-translate__progress" hidden data-bspe-translate-progress>
					<div class="bspe-translate__bar"><span data-bspe-translate-bar></span></div>
					<p class="bspe-translate__status" data-bspe-translate-status></p>
				</div>

				<div class="bspe-translate__result" hidden data-bspe-translate-result>
					<p>
						<strong data-bspe-translate-result-text></strong>
						<a href="#" target="_blank" rel="noopener" data-bspe-translate-result-view><?php esc_html_e( 'View', 'bspe-connect' ); ?></a>
						<a href="#" target="_blank" rel="noopener" data-bspe-translate-result-edit><?php esc_html_e( 'Edit', 'bspe-connect' ); ?></a>
					</p>
					<p class="bspe-translate__meta" data-bspe-translate-result-usage></p>
				</div>
			</div>
		</div>
	</div>
<?php endif;
Components::close_card();

/* ----------------- Settings ----------------- */
Components::open_form( 'translate', $action_url );
Components::open_card(
	__( 'Claude API', 'bspe-connect' ),
	__( 'The translation runs on Anthropic\'s Claude API using this site\'s own key. Usage is billed to the account that owns the key, not per word through WPML.', 'bspe-connect' )
);
Components::row(
	__( 'API key', 'bspe-connect' ),
	static function () use ( $key_hint ): void {
		Components::text( 'bspe[translate][api_key]', '', [
			'type'         => 'password',
			'placeholder'  => '' === $key_hint ? 'sk-ant-…' : $key_hint,
			'autocomplete' => 'off',
		] );
	},
	[
		'id'          => 'bspe-translate-api_key',
		'description' => '' === $key_hint
			? __( 'Create a key in the Anthropic Console (console.anthropic.com, API Keys). Stored in this site\'s database and never displayed again in full.', 'bspe-connect' )
			: __( 'A key is saved. Leave blank to keep it, paste a new key to replace it, or type <code>CLEAR</code> to remove it.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Model', 'bspe-connect' ),
	static function () use ( $translate ): void {
		Components::select( 'bspe[translate][model]', (string) ( $translate['model'] ?? 'claude-opus-5' ), Claude_Client::MODELS );
	},
	[ 'description' => __( 'Opus gives the most natural legal Spanish. A typical page costs a few cents; a very long Elementor page well under a dollar.', 'bspe-connect' ) ]
);
Components::row(
	__( 'Default language', 'bspe-connect' ),
	static function () use ( $default_tg ): void {
		Components::select( 'bspe[translate][default_target]', $default_tg, Claude_Client::LANGUAGES );
	},
	[ 'description' => __( 'Pre-selected in the translate form. More languages can be added in a later version.', 'bspe-connect' ) ]
);
Components::row(
	__( 'Firm name', 'bspe-connect' ),
	static function () use ( $translate ): void {
		Components::text( 'bspe[translate][firm_name_hint]', (string) ( $translate['firm_name_hint'] ?? '' ), [
			'placeholder' => (string) Settings::get( 'design.firm_name', '' ),
		] );
	},
	[
		'id'          => 'bspe-translate-firm_name_hint',
		'description' => __( 'Told to the translator as a name that must never be translated. Leave blank to use the firm name from the Design tab.', 'bspe-connect' ),
	]
);
Components::close_card();
Components::close_form();

/* ----------------- Notes ----------------- */
Components::open_card(
	__( 'How it works', 'bspe-connect' ),
	''
);
?>
<div class="bspe-row">
	<div class="bspe-row__label-col"><span class="bspe-row__label"><?php esc_html_e( 'Good to know', 'bspe-connect' ); ?></span></div>
	<div class="bspe-row__control-col">
		<ul class="bspe-translate__notes">
			<li><?php esc_html_e( 'The translation takes the same status as the original: a published page gives a published translation, so it appears in the language switcher right away. Review it afterwards like any WPML translation.', 'bspe-connect' ); ?></li>
			<li><?php esc_html_e( 'Long pages are sent in several chunks so nothing times out; the original is never modified, and nothing is written until every chunk came back.', 'bspe-connect' ); ?></li>
			<li><?php esc_html_e( 'Elementor layout, links, images, colors and settings are copied as they are; only visitor-facing text is translated. Elementor\'s CSS for the new page is regenerated on first view.', 'bspe-connect' ); ?></li>
			<li><?php esc_html_e( 'Categories and tags are mapped to their WPML translations when those exist. Menus, theme strings and reusable Elementor templates are not part of a page and are translated in WPML as usual.', 'bspe-connect' ); ?></li>
		</ul>
	</div>
</div>
<?php
Components::close_card();
