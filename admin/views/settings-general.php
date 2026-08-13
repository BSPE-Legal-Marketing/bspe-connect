<?php
/**
 * General settings tab — master enable, welcome bubble, display behavior.
 *
 * @package BSPE\Connect\Admin
 *
 * @var int $current_phase
 */

defined( 'ABSPATH' ) || exit;

use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Components;
use BSPE\Connect\Admin\Settings_Saver;

$enabled    = (bool) Settings::get( 'enabled', false );
$bubble     = is_array( Settings::get( 'welcome_bubble', [] ) ) ? Settings::get( 'welcome_bubble', [] ) : [];
$display    = is_array( Settings::get( 'display', [] ) ) ? Settings::get( 'display', [] ) : [];
$utilities  = is_array( Settings::get( 'utilities', [] ) ) ? Settings::get( 'utilities', [] ) : [];
$action_url = admin_url( 'admin-post.php' );

Components::open_form( 'general', $action_url );

Components::open_card(
	__( 'Master controls', 'bspe-connect' ),
	__( 'Turn the bar on for visitors. Off until you finish configuring buttons and the lead form.', 'bspe-connect' )
);
Components::row(
	__( 'Bar enabled', 'bspe-connect' ),
	static function () use ( $enabled ): void {
		Components::toggle( 'bspe[enabled]', $enabled, [
			'label' => __( 'Show the contact bar to mobile visitors', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Visitors only see the bar when this is on AND a display rule matches AND at least one button is enabled.', 'bspe-connect' ) ]
);
Components::close_card();

Components::open_card(
	__( 'Welcome bubble', 'bspe-connect' ),
	__( 'A small message that floats above the bar to introduce the firm.', 'bspe-connect' )
);
Components::row(
	__( 'Show welcome bubble', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::toggle( 'bspe[welcome_bubble][enabled]', ! empty( $bubble['enabled'] ), [
			'label' => __( 'Display the bubble', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Heading', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::text( 'bspe[welcome_bubble][heading]', (string) ( $bubble['heading'] ?? '' ), [
			'placeholder' => 'Welcome to {firm_name}',
			'maxlength'   => 120,
		] );
	},
	[
		'id'          => 'bspe-welcome_bubble-heading',
		'description' => __( 'Use <code>{firm_name}</code> or <code>{site_name}</code> as placeholders.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Message', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::textarea( 'bspe[welcome_bubble][message]', (string) ( $bubble['message'] ?? '' ), [
			'rows'      => 2,
			'maxlength' => 240,
		] );
	},
	[ 'id' => 'bspe-welcome_bubble-message' ]
);
Components::row(
	__( 'Avatar', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::checkbox( 'bspe[welcome_bubble][show_avatar]', ! empty( $bubble['show_avatar'] ), [
			'label' => __( 'Show an avatar next to the message', 'bspe-connect' ),
		] );
		echo '<div class="bspe-row__sub" data-bspe-show-when="bspe-welcome_bubble-show_avatar">';
		Components::media( 'bspe[welcome_bubble][avatar_id]', (int) ( $bubble['avatar_id'] ?? 0 ), [
			'button_text' => __( 'Choose avatar', 'bspe-connect' ),
			'modal_title' => __( 'Select avatar image', 'bspe-connect' ),
		] );
		echo '</div>';
	}
);
Components::row(
	__( 'When to show', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::radio_pills( 'bspe[welcome_bubble][trigger]', (string) ( $bubble['trigger'] ?? 'auto' ), [
			'auto'  => __( 'After a delay', 'bspe-connect' ),
			'click' => __( 'On Connect click', 'bspe-connect' ),
		] );
	}
);
Components::row(
	__( 'Delay', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::number( 'bspe[welcome_bubble][delay]', (int) ( $bubble['delay'] ?? 3 ), [
			'min'    => 0,
			'max'    => 60,
			'suffix' => __( 'seconds', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-welcome_bubble-delay',
		'description' => __( 'How long to wait after the bar appears before showing the bubble.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Repeat rule', 'bspe-connect' ),
	static function () use ( $bubble ): void {
		Components::radio_pills( 'bspe[welcome_bubble][repeat]', (string) ( $bubble['repeat'] ?? 'session' ), [
			'session' => __( 'Once per session', 'bspe-connect' ),
			'once'    => __( 'Once ever', 'bspe-connect' ),
			'always'  => __( 'Every page', 'bspe-connect' ),
		] );
	},
	[ 'description' => __( 'Once dismissed, when should the bubble reappear?', 'bspe-connect' ) ]
);
Components::close_card();

Components::open_card(
	__( 'Display behavior', 'bspe-connect' ),
	__( 'When the bar appears, and where the mobile breakpoint sits.', 'bspe-connect' )
);
Components::row(
	__( 'Show after delay', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::number( 'bspe[display][show_delay]', (int) ( $display['show_delay'] ?? 3 ), [
			'min'    => 0,
			'max'    => 60,
			'step'   => 1,
			'suffix' => __( 'seconds', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-display-show_delay',
		'description' => __( 'Wait this many seconds after the page loads, then slide the bar in. Set to 0 to show the bar immediately.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Hide at top of page', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::number( 'bspe[display][scroll_threshold]', (int) ( $display['scroll_threshold'] ?? 0 ), [
			'min'    => 0,
			'max'    => 5000,
			'step'   => 10,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-display-scroll_threshold',
		'description' => __( 'Keep the bar hidden until the visitor scrolls down at least this far. Set to 0 to always show the bar (after the delay above).', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Hide on scroll up', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::toggle( 'bspe[display][hide_on_scroll_up]', ! empty( $display['hide_on_scroll_up'] ), [
			'label' => __( 'Slide the bar away when the visitor scrolls back up the page.', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Off by default. Pairs naturally with “Hide at top of page” — the bar reappears when the visitor scrolls down again.', 'bspe-connect' ),
	]
);
Components::row(
	__( 'Mobile breakpoint', 'bspe-connect' ),
	static function () use ( $display ): void {
		Components::number( 'bspe[display][mobile_breakpoint]', (int) ( $display['mobile_breakpoint'] ?? 768 ), [
			'min'    => 320,
			'max'    => 2000,
			'step'   => 1,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-display-mobile_breakpoint',
		'description' => __( 'Screens wider than this never see the bar.', 'bspe-connect' ),
	]
);
Components::close_card();

/* ----------------- Site utilities ----------------- */
Components::open_card(
	__( 'Site utilities', 'bspe-connect' ),
	__( 'Optional extras that improve every BSPE Legal Marketing site. All on by default.', 'bspe-connect' )
);

Components::row(
	__( 'Page QR code', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::toggle( 'bspe[utilities][qr_indexer]', ! empty( $utilities['qr_indexer'] ), [
			'label' => __( 'Append a QR code at the bottom of every post and page', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Generated locally on the visitor\'s browser — no external service. The QR encodes the current page URL so visitors can scan it on a phone.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'QR code size', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::number( 'bspe[utilities][qr_size_px]', (int) ( $utilities['qr_size_px'] ?? 150 ), [
			'min'    => 80,
			'max'    => 400,
			'step'   => 10,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-utilities-qr_size_px',
		'description' => __( 'Default 150 px. Image side length on the page.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'QR container max width', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::number( 'bspe[utilities][qr_max_width_px]', (int) ( $utilities['qr_max_width_px'] ?? 1240 ), [
			'min'    => 320,
			'max'    => 2400,
			'step'   => 10,
			'suffix' => __( 'px', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-utilities-qr_max_width_px',
		'description' => __( 'Default 1240 px. Caps the centered wrapper so the QR doesn\'t span full width on long-form layouts.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'External links in new tab', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::toggle( 'bspe[utilities][external_links_new_tab]', ! empty( $utilities['external_links_new_tab'] ), [
			'label' => __( 'Open links to other domains in a new tab', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'Adds <code>target="_blank" rel="noopener noreferrer"</code> to every external link on page load. Same-domain links (including subdomains like <code>www.</code> and <code>staging.</code>) are left alone.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'Hide users from REST API', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::toggle( 'bspe[utilities][hide_users_rest]', ! empty( $utilities['hide_users_rest'] ), [
			'label' => __( 'Block anonymous access to <code>/wp-json/wp/v2/users</code>', 'bspe-connect' ),
		] );
	},
	[
		'description' => __( 'WordPress publishes the list of authors at <code>/wp-json/wp/v2/users</code> by default — useful for usernames-then-passwords attacks. This toggle closes that endpoint for anonymous requests while leaving it open to logged-in admins.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'WPML language switcher', 'bspe-connect' ),
	static function (): void {
		if ( ! \BSPE\Connect\WPML_Status::wpml_active() ) {
			echo '<p style="margin:0;color:#646970;">' . esc_html__( 'WPML is not active on this site, nothing to check.', 'bspe-connect' ) . '</p>';
			return;
		}

		$skip  = \BSPE\Connect\WPML_Status::skip_enabled();
		$dupes = \BSPE\Connect\WPML_Status::published_duplicate_count();

		if ( true === $skip ) {
			echo '<p style="margin:0 0 6px;"><span style="color:#00a32a;font-weight:600;">&#10003; ' . esc_html__( '"Skip language" is ON', 'bspe-connect' ) . '</span>: ' . esc_html__( 'languages without translation are hidden from the switcher.', 'bspe-connect' ) . '</p>';
		} elseif ( false === $skip ) {
			echo '<p style="margin:0 0 6px;"><span style="color:#d63638;font-weight:600;">&#10007; ' . esc_html__( '"Skip language" is OFF', 'bspe-connect' ) . '</span>: ' . esc_html__( 'the switcher links visitors to the homepage of languages that have no translation. Turn it on under WPML, Languages, Language switcher options ("What to do for languages without translation").', 'bspe-connect' ) . '</p>';
		} else {
			echo '<p style="margin:0 0 6px;color:#646970;">' . esc_html__( 'Could not read the setting from WPML (never saved yet, so WPML\'s default applies). Check WPML, Languages, Language switcher options.', 'bspe-connect' ) . '</p>';
		}

		if ( $dupes > 0 ) {
			echo '<p style="margin:0;color:#996800;">';
			printf(
				/* translators: %d: number of published duplicate posts/pages */
				esc_html__( 'Heads up: %d published pages on this site are WPML duplicates. WPML counts a duplicate as a translation, so those languages keep showing in the switcher even with "Skip language" on. This is the usual reason the option seems not to work. Translate the duplicates (or delete them) to hide those languages.', 'bspe-connect' ),
				(int) $dupes
			);
			echo '</p>';
		}
	},
	[
		'description' => __( 'Read-only check of WPML\'s own behavior for languages without translation. BSPE Connect does not change the switcher; it only reports whether WPML is set to skip untranslated languages, and flags duplicated content that makes the option look broken.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'Strip schema on translated pages (WPML)', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::toggle( 'bspe[utilities][wpml_strip_schema]', ! empty( $utilities['wpml_strip_schema'] ), [
			'label' => __( 'Remove all JSON-LD structured data from pages in a non-default language', 'bspe-connect' ),
		] );
	},
	[
		'description' => \BSPE\Connect\WPML_Status::wpml_active()
			? __( 'SEOPress custom schemas are written once, in the default language, so WPML serves the same English schema on every translated page, which is wrong structured data for those URLs. When on, unmarked <code>application/ld+json</code> blocks (no id/class on the script tag, which is how SEOPress prints Manual schemas, the Custom type included) are stripped from the head of pages in the languages below. Schema tagged by its generator, like the theme\'s <code>website-schema</code>, is kept. Pages on the allow list keep everything.', 'bspe-connect' )
			: __( 'Only applies when WPML is installed. WPML is not active on this site right now, so this toggle has no effect.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'Strip on languages', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::text( 'bspe[utilities][wpml_schema_strip_langs]', (string) ( $utilities['wpml_schema_strip_langs'] ?? 'es' ), [
			'placeholder' => 'es',
		] );
	},
	[
		'id'          => 'bspe-utilities-wpml_schema_strip_langs',
		'description' => __( 'Comma separated WPML language codes the stripper acts on. Default: <code>es</code> (Spanish only). The site\'s default language is never stripped, even if listed here.', 'bspe-connect' ),
	]
);

Components::row(
	__( 'Pages allowed to keep schema', 'bspe-connect' ),
	static function () use ( $utilities ): void {
		Components::text( 'bspe[utilities][wpml_schema_allow_ids]', (string) ( $utilities['wpml_schema_allow_ids'] ?? '' ), [
			'placeholder' => __( 'e.g. 1234, 5678', 'bspe-connect' ),
		] );
	},
	[
		'id'          => 'bspe-utilities-wpml_schema_allow_ids',
		'description' => __( 'Comma separated page/post IDs. A translated page listed here keeps its own schema output even with the stripper on. Use it once a page has its own correct schema in its own language. The page ID is shown in the edit screen URL (post=...) and in the schema warning on translated pages.', 'bspe-connect' ),
	]
);
Components::close_card();

Components::close_form();

/* ----------------- Danger zone: Reset settings ----------------- */
/* Lives OUTSIDE the main settings form so the reset submit can't be
   confused with a normal Save click, and so its own POST stays separate
   from the regular settings-save flow. */
$reset_phrase = \BSPE\Connect\Admin\Settings_Saver::RESET_PHRASE;
?>
<section class="bspe-card bspe-card--danger" data-bspe-reset-card>
	<header class="bspe-card__head">
		<div class="bspe-card__head-text">
			<h2><?php esc_html_e( 'Reset all settings', 'bspe-connect' ); ?></h2>
			<p class="bspe-card__lead">
				<?php esc_html_e( 'Restore every BSPE Connect setting to the same defaults it shipped with. Saved submissions, analytics events, and diagnostic logs are not touched — only the plugin\'s settings are replaced.', 'bspe-connect' ); ?>
			</p>
		</div>
	</header>

	<div class="bspe-reset">
		<button type="button" class="bspe-button bspe-button--danger" data-bspe-reset-trigger>
			<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M3 8a5 5 0 1 0 1.5-3.5M3 3v3h3"/>
			</svg>
			<?php esc_html_e( 'Reset all settings to defaults', 'bspe-connect' ); ?>
		</button>

		<form method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			class="bspe-reset__form"
			data-bspe-reset-form
			data-bspe-reset-phrase="<?php echo esc_attr( $reset_phrase ); ?>"
			hidden
		>
			<?php wp_nonce_field( \BSPE\Connect\Admin\Settings_Saver::RESET_NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( \BSPE\Connect\Admin\Settings_Saver::RESET_ACTION ); ?>" />

			<p class="bspe-reset__warning">
				<strong><?php esc_html_e( 'This cannot be undone.', 'bspe-connect' ); ?></strong>
				<?php
				printf(
					/* translators: %s: the literal phrase the user must type to confirm */
					esc_html__( 'Type %s below to confirm.', 'bspe-connect' ),
					'<code>' . esc_html( $reset_phrase ) . '</code>'
				);
				?>
			</p>

			<div class="bspe-reset__row">
				<label for="bspe-reset-confirm" class="screen-reader-text">
					<?php esc_html_e( 'Type the confirmation phrase', 'bspe-connect' ); ?>
				</label>
				<input type="text"
					id="bspe-reset-confirm"
					name="bspe_reset_confirm"
					class="bspe-input bspe-reset__input"
					autocomplete="off"
					autocapitalize="characters"
					spellcheck="false"
					placeholder="<?php echo esc_attr( $reset_phrase ); ?>"
					data-bspe-reset-input
				/>
				<button type="submit"
					class="bspe-button bspe-button--danger"
					data-bspe-reset-submit
					disabled
				>
					<?php esc_html_e( 'Reset everything', 'bspe-connect' ); ?>
				</button>
				<button type="button" class="bspe-button bspe-button--ghost" data-bspe-reset-cancel>
					<?php esc_html_e( 'Cancel', 'bspe-connect' ); ?>
				</button>
			</div>
		</form>
	</div>
</section>
