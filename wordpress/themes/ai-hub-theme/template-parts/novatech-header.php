<?php
/**
 * Template Part: NovaTech Sticky Header & Accessible Mobile Navigation.
 *
 * Reads all branding, navigation items, and CTA properties from the
 * 'header_settings' ACF group on the current page.
 *
 * @package AI_Hub_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$header_settings   = function_exists( 'get_field' ) ? (array) get_field( 'header_settings' ) : array();
$logo_text         = ! empty( $header_settings['logo_text'] ) ? (string) $header_settings['logo_text'] : '';
$logo_badge        = ! empty( $header_settings['logo_badge'] ) ? (string) $header_settings['logo_badge'] : '';
$announcement_text = ! empty( $header_settings['announcement_text'] ) ? (string) $header_settings['announcement_text'] : '';
$cta_label         = ! empty( $header_settings['cta_label'] ) ? (string) $header_settings['cta_label'] : '';
$cta_url           = ! empty( $header_settings['cta_url'] ) ? (string) $header_settings['cta_url'] : '#contact';
$nav_items         = isset( $header_settings['nav_items'] ) ? nt_normalize_acf_list( $header_settings['nav_items'] ) : array();
?>
<a class="nt-skip-link" href="#main-content">Skip to main content</a>

<?php if ( '' !== $announcement_text ) : ?>
<div class="nt-topbar" role="region" aria-label="Environment status announcement">
	<div class="nt-container nt-topbar__inner">
		<span class="nt-topbar__dot" aria-hidden="true"></span>
		<p class="nt-topbar__text"><?php echo esc_html( $announcement_text ); ?></p>
	</div>
</div>
<?php endif; ?>

<header class="nt-header" id="site-header" data-sticky-header>
	<div class="nt-container nt-header__inner">
		<a class="nt-brand" href="<?php echo esc_url( get_permalink() ); ?>" aria-label="<?php echo esc_attr( trim( $logo_text . ' ' . $logo_badge ) ); ?> home">
			<span class="nt-brand__mark" aria-hidden="true">
				<svg viewBox="0 0 28 28" width="28" height="28" fill="none" focusable="false">
					<rect x="2" y="2" width="24" height="24" rx="6" fill="currentColor" fill-opacity="0.14" stroke="currentColor" stroke-width="1.75"/>
					<path d="M8.5 19V9L14 15.5L19.5 9V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
			<span class="nt-brand__wordmark">
				<span class="nt-brand__name"><?php echo esc_html( $logo_text ); ?></span>
				<?php if ( '' !== $logo_badge ) : ?>
					<span class="nt-brand__badge"><?php echo esc_html( $logo_badge ); ?></span>
				<?php endif; ?>
			</span>
		</a>

		<nav class="nt-nav" aria-label="Primary navigation">
			<ul class="nt-nav__list">
				<?php foreach ( $nav_items as $nav_item ) : ?>
					<?php if ( ! empty( $nav_item['label'] ) ) : ?>
						<li class="nt-nav__item">
							<a class="nt-nav__link" href="<?php echo esc_attr( ! empty( $nav_item['url'] ) ? $nav_item['url'] : '#' ); ?>">
								<?php echo esc_html( $nav_item['label'] ); ?>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="nt-header__actions">
			<?php if ( '' !== $cta_label ) : ?>
				<a class="nt-btn nt-btn--primary nt-btn--sm nt-header__cta" href="<?php echo esc_attr( $cta_url ); ?>">
					<?php echo esc_html( $cta_label ); ?>
				</a>
			<?php endif; ?>

			<button
				type="button"
				class="nt-mobile-toggle"
				id="nt-mobile-menu-toggle"
				aria-expanded="false"
				aria-controls="nt-mobile-menu-drawer"
				aria-label="Open navigation menu"
				data-mobile-toggle
			>
				<span class="nt-mobile-toggle__bar" aria-hidden="true"></span>
				<span class="nt-mobile-toggle__bar" aria-hidden="true"></span>
				<span class="nt-mobile-toggle__bar" aria-hidden="true"></span>
			</button>
		</div>
	</div>

	<div
		class="nt-mobile-drawer"
		id="nt-mobile-menu-drawer"
		aria-label="Mobile navigation"
		hidden
		data-mobile-drawer
	>
		<div class="nt-container nt-mobile-drawer__inner">
			<nav aria-label="Mobile section navigation">
				<ul class="nt-mobile-drawer__list">
					<?php foreach ( $nav_items as $nav_item ) : ?>
						<?php if ( ! empty( $nav_item['label'] ) ) : ?>
							<li class="nt-mobile-drawer__item">
								<a class="nt-mobile-drawer__link" href="<?php echo esc_attr( ! empty( $nav_item['url'] ) ? $nav_item['url'] : '#' ); ?>" data-mobile-link>
									<?php echo esc_html( $nav_item['label'] ); ?>
								</a>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</nav>
			<?php if ( '' !== $cta_label ) : ?>
				<div class="nt-mobile-drawer__footer">
					<a class="nt-btn nt-btn--primary nt-btn--full" href="<?php echo esc_attr( $cta_url ); ?>" data-mobile-link>
						<?php echo esc_html( $cta_label ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</header>
