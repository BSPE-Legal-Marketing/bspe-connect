<?php
/**
 * Admin page shell: branded header, left sidebar nav, content area.
 *
 * @package BSPE\Connect\Admin
 *
 * @var string                                                                         $active Active tab slug.
 * @var array<string, array{label:string, view:string, phase:int, icon:string, hint:string}> $tabs   Registered tabs.
 */

defined( 'ABSPATH' ) || exit;

$active_tab = $tabs[ $active ] ?? null;
$svg_kses   = \BSPE\Connect\Admin\Admin::svg_kses();
?>
<div class="bspe-shell" data-active-tab="<?php echo esc_attr( $active ); ?>">

	<header class="bspe-header">
		<div class="bspe-header__inner">
			<div class="bspe-brand">
				<span class="bspe-brand__mark" aria-hidden="true">
					<svg viewBox="0 0 32 32" width="28" height="28" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="2" y="6" width="28" height="20" rx="6" stroke="#FAF7F2" stroke-width="1.6"/>
						<path d="M9 14h14M9 18h10" stroke="#3AAFB9" stroke-width="2" stroke-linecap="round"/>
					</svg>
				</span>
				<div class="bspe-brand__text">
					<h1><?php esc_html_e( 'BSPE Connect', 'bspe-connect' ); ?></h1>
					<p class="bspe-brand__tagline">
						<?php
						echo esc_html(
							$active_tab['hint'] ?? __( 'Mobile contact bar for law firm sites', 'bspe-connect' )
						);
						?>
					</p>
				</div>
			</div>
			<div class="bspe-header__meta">
				<span class="bspe-version" aria-label="<?php esc_attr_e( 'Plugin version', 'bspe-connect' ); ?>">
					v<?php echo esc_html( BSPE_CONNECT_VERSION ); ?>
				</span>
			</div>
		</div>
	</header>

	<aside class="bspe-sidebar" role="navigation" aria-label="<?php esc_attr_e( 'BSPE Connect sections', 'bspe-connect' ); ?>">
		<nav class="bspe-nav" role="tablist">
			<?php foreach ( $tabs as $slug => $tab ) : ?>
				<a
					class="bspe-nav__item<?php echo $slug === $active ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( \BSPE\Connect\Admin\Admin::tab_url( $slug ) ); ?>"
					role="tab"
					aria-selected="<?php echo $slug === $active ? 'true' : 'false'; ?>"
					aria-current="<?php echo $slug === $active ? 'page' : 'false'; ?>"
					tabindex="<?php echo $slug === $active ? '0' : '-1'; ?>"
				>
					<span class="bspe-nav__icon" aria-hidden="true">
						<?php echo wp_kses( \BSPE\Connect\Admin\Admin::icon( $tab['icon'] ), $svg_kses ); ?>
					</span>
					<span class="bspe-nav__label"><?php echo esc_html( $tab['label'] ); ?></span>
					<?php if ( $slug === $active ) : ?>
						<span class="bspe-nav__indicator" aria-hidden="true"></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<footer class="bspe-sidebar__footer">
			<div class="bspe-sidebar__build">
				<span class="bspe-sidebar__build-label"><?php esc_html_e( 'Build', 'bspe-connect' ); ?></span>
				<span class="bspe-sidebar__build-value">v<?php echo esc_html( BSPE_CONNECT_VERSION ); ?></span>
			</div>
			<a
				class="bspe-sidebar__doc"
				href="https://github.com/BSPE-Legal-Marketing/bspe-connect"
				target="_blank"
				rel="noopener"
			>
				<svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">
					<path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
				</svg>
				<?php esc_html_e( 'Repository', 'bspe-connect' ); ?>
			</a>
		</footer>
	</aside>

	<main class="bspe-content" role="tabpanel" aria-labelledby="<?php echo esc_attr( 'bspe-tab-' . $active ); ?>">
		<?php
		$saved_notice = \BSPE\Connect\Admin\Settings_Saver::consume_notice();
		if ( 'saved' === $saved_notice ) :
			?>
			<div class="bspe-notice" role="status">
				<span class="bspe-notice__icon" aria-hidden="true">
					<svg viewBox="0 0 14 14" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 7.5l3 3 6-7"/></svg>
				</span>
				<?php esc_html_e( 'Settings saved.', 'bspe-connect' ); ?>
			</div>
		<?php endif; ?>

		<?php
		if ( $active_tab && file_exists( BSPE_CONNECT_DIR . 'admin/views/' . $active_tab['view'] ) ) {
			$current_phase = (int) $active_tab['phase'];
			require BSPE_CONNECT_DIR . 'admin/views/' . $active_tab['view'];
		} else {
			echo '<div class="bspe-card"><p>' . esc_html__( 'Tab not available.', 'bspe-connect' ) . '</p></div>';
		}
		?>
	</main>
</div>
