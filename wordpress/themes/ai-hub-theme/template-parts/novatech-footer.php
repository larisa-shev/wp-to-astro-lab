<?php
/**
 * Template Part: NovaTech Corporate Footer.
 *
 * Reads brand, navigation, contact details, and disclaimers from
 * 'header_settings' and 'contact_footer_section' ACF groups.
 *
 * @package AI_Hub_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$header_settings = function_exists( 'get_field' ) ? (array) get_field( 'header_settings' ) : array();
$contact_footer  = function_exists( 'get_field' ) ? (array) get_field( 'contact_footer_section' ) : array();

$logo_text    = ! empty( $header_settings['logo_text'] ) ? (string) $header_settings['logo_text'] : '';
$logo_badge   = ! empty( $header_settings['logo_badge'] ) ? (string) $header_settings['logo_badge'] : '';
$nav_items    = isset( $header_settings['nav_items'] ) ? nt_normalize_acf_list( $header_settings['nav_items'] ) : array();

$footer_desc  = ! empty( $contact_footer['footer_description'] ) ? (string) $contact_footer['footer_description'] : '';
$email        = ! empty( $contact_footer['company_email'] ) ? (string) $contact_footer['company_email'] : '';
$phone        = ! empty( $contact_footer['company_phone'] ) ? (string) $contact_footer['company_phone'] : '';
$address      = ! empty( $contact_footer['company_address'] ) ? (string) $contact_footer['company_address'] : '';
$office_hours = ! empty( $contact_footer['office_hours'] ) ? (string) $contact_footer['office_hours'] : '';
$copyright    = ! empty( $contact_footer['copyright_text'] ) ? (string) $contact_footer['copyright_text'] : '';
$demo_notice  = ! empty( $contact_footer['demo_environment_notice'] ) ? (string) $contact_footer['demo_environment_notice'] : '';
?>
<footer class="nt-footer" id="site-footer">
	<div class="nt-container">
		<div class="nt-footer__grid">
			<div class="nt-footer__brand-col">
				<a class="nt-brand nt-brand--footer" href="<?php echo esc_url( get_permalink() ); ?>">
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
				<?php if ( '' !== $footer_desc ) : ?>
					<p class="nt-footer__desc"><?php echo esc_html( $footer_desc ); ?></p>
				<?php endif; ?>
			</div>

			<div class="nt-footer__nav-col">
				<h2 class="nt-footer__heading">Platform Navigation</h2>
				<ul class="nt-footer__list">
					<?php foreach ( $nav_items as $nav_item ) : ?>
						<?php if ( ! empty( $nav_item['label'] ) ) : ?>
							<li>
								<a class="nt-footer__link" href="<?php echo esc_attr( ! empty( $nav_item['url'] ) ? $nav_item['url'] : '#' ); ?>">
									<?php echo esc_html( $nav_item['label'] ); ?>
								</a>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
					<li><a class="nt-footer__link" href="#contact">Architecture Inquiry</a></li>
				</ul>
			</div>

			<div class="nt-footer__contact-col">
				<h2 class="nt-footer__heading">Engineering Desk (Demo)</h2>
				<dl class="nt-footer__contact-list">
					<?php if ( '' !== $email ) : ?>
						<div class="nt-footer__contact-item">
							<dt class="nt-sr-only">Email</dt>
							<dd><a class="nt-footer__link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></dd>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $phone ) : ?>
						<div class="nt-footer__contact-item">
							<dt class="nt-sr-only">Phone</dt>
							<dd><?php echo esc_html( $phone ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $address ) : ?>
						<div class="nt-footer__contact-item">
							<dt class="nt-sr-only">Address</dt>
							<dd><?php echo esc_html( $address ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $office_hours ) : ?>
						<div class="nt-footer__contact-item">
							<dt class="nt-sr-only">Operating Hours</dt>
							<dd class="nt-footer__hours"><?php echo esc_html( $office_hours ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>
			</div>
		</div>

		<div class="nt-footer__bottom">
			<?php if ( '' !== $copyright ) : ?>
				<p class="nt-footer__copyright"><?php echo esc_html( $copyright ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $demo_notice ) : ?>
				<p class="nt-footer__disclaimer"><?php echo esc_html( $demo_notice ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</footer>
