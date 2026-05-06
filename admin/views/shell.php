<?php
/**
 * Admin page shell: header band, tab nav, content area.
 *
 * @package BSPE\Connect\Admin
 *
 * @var string                                                    $active Active tab slug.
 * @var array<string, array{label:string, view:string, phase:int}> $tabs   Registered tabs.
 */

defined( 'ABSPATH' ) || exit;

$active_tab = $tabs[ $active ] ?? null;
?>
<div class="bspe-shell">
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
					<p class="bspe-brand__tagline"><?php esc_html_e( 'Mobile contact bar for law firm sites', 'bspe-connect' ); ?></p>
				</div>
			</div>
			<div class="bspe-header__meta">
				<span class="bspe-version" aria-label="<?php esc_attr_e( 'Plugin version', 'bspe-connect' ); ?>">
					v<?php echo esc_html( BSPE_CONNECT_VERSION ); ?>
				</span>
			</div>
		</div>
	</header>

	<nav class="bspe-tabs" role="tablist" aria-label="<?php esc_attr_e( 'BSPE Connect sections', 'bspe-connect' ); ?>">
		<?php foreach ( $tabs as $slug => $tab ) : ?>
			<a
				class="bspe-tab<?php echo $slug === $active ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( \BSPE\Connect\Admin\Admin::tab_url( $slug ) ); ?>"
				role="tab"
				aria-selected="<?php echo $slug === $active ? 'true' : 'false'; ?>"
				tabindex="<?php echo $slug === $active ? '0' : '-1'; ?>"
			>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<main class="bspe-content" role="tabpanel">
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
