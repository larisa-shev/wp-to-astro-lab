<?php
/**
 * Template Name: NovaTech Corporate Landing
 * Template Post Type: page
 *
 * Full-featured B2B technology landing page driven 100% by Advanced Custom Fields (ACF Free).
 * Designed as the reference baseline for the WordPress-to-Astro migration laboratory.
 *
 * @package AI_Hub_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hero_section         = function_exists( 'get_field' ) ? (array) get_field( 'hero_section' ) : array();
$services_section     = function_exists( 'get_field' ) ? (array) get_field( 'services_section' ) : array();
$cases_section        = function_exists( 'get_field' ) ? (array) get_field( 'cases_slider_section' ) : array();
$testimonials_section = function_exists( 'get_field' ) ? (array) get_field( 'testimonials_section' ) : array();
$process_section      = function_exists( 'get_field' ) ? (array) get_field( 'process_section' ) : array();
$faq_section          = function_exists( 'get_field' ) ? (array) get_field( 'faq_section' ) : array();
$contact_section      = function_exists( 'get_field' ) ? (array) get_field( 'contact_footer_section' ) : array();

// Normalize nested ACF Free sub-groups into clean indexed arrays
$hero_stats        = isset( $hero_section['stats'] ) ? nt_normalize_acf_list( $hero_section['stats'], false ) : array();
$hero_image        = isset( $hero_section['hero_image'] ) ? nt_resolve_acf_image( $hero_section['hero_image'], 'NovaTech platform architecture diagram' ) : array();
$service_cards     = isset( $services_section['cards'] ) ? nt_normalize_acf_list( $services_section['cards'] ) : array();
$case_slides       = isset( $cases_section['slides'] ) ? nt_normalize_acf_list( $cases_section['slides'] ) : array();
$testimonial_items = isset( $testimonials_section['slides'] ) ? nt_normalize_acf_list( $testimonials_section['slides'] ) : array();
$process_steps     = isset( $process_section['steps'] ) ? nt_normalize_acf_list( $process_section['steps'] ) : array();
$faq_items         = isset( $faq_section['items'] ) ? nt_normalize_acf_list( $faq_section['items'] ) : array();
$autoplay_delay    = ! empty( $testimonials_section['autoplay_delay'] ) ? (int) $testimonials_section['autoplay_delay'] : 6000;
$allow_multi_proc  = ! isset( $process_section['allow_multiple'] ) || (bool) $process_section['allow_multiple'];
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="nt-html">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'nt-body' ); ?>>
<?php wp_body_open(); ?>

<?php get_template_part( 'template-parts/novatech', 'header' ); ?>

<main id="main-content" class="nt-main">

	<!-- ================================================================== -->
	<!-- 1. HERO SECTION                                                    -->
	<!-- ================================================================== -->
	<section class="nt-hero" id="hero" aria-labelledby="nt-hero-heading">
		<div class="nt-container">
			<div class="nt-hero__grid">
				<div class="nt-hero__content">
					<?php if ( ! empty( $hero_section['eyebrow_badge'] ) ) : ?>
						<div class="nt-eyebrow">
							<span class="nt-eyebrow__pulse" aria-hidden="true"></span>
							<span><?php echo esc_html( $hero_section['eyebrow_badge'] ); ?></span>
						</div>
					<?php endif; ?>

					<h1 id="nt-hero-heading" class="nt-hero__title">
						<?php echo esc_html( ! empty( $hero_section['title'] ) ? $hero_section['title'] : get_the_title() ); ?>
					</h1>

					<?php if ( ! empty( $hero_section['subtitle'] ) ) : ?>
						<p class="nt-hero__subtitle">
							<?php echo esc_html( $hero_section['subtitle'] ); ?>
						</p>
					<?php endif; ?>

					<div class="nt-hero__actions">
						<?php if ( ! empty( $hero_section['primary_cta_text'] ) ) : ?>
							<a class="nt-btn nt-btn--primary" href="<?php echo esc_attr( ! empty( $hero_section['primary_cta_url'] ) ? $hero_section['primary_cta_url'] : '#contact' ); ?>">
								<span><?php echo esc_html( $hero_section['primary_cta_text'] ); ?></span>
								<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
									<path d="M4 10h12m-4-4 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</a>
						<?php endif; ?>

						<?php if ( ! empty( $hero_section['secondary_cta_text'] ) ) : ?>
							<a class="nt-btn nt-btn--secondary" href="<?php echo esc_attr( ! empty( $hero_section['secondary_cta_url'] ) ? $hero_section['secondary_cta_url'] : '#cases' ); ?>">
								<?php echo esc_html( $hero_section['secondary_cta_text'] ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<div class="nt-hero__visual">
					<figure class="nt-hero__figure">
						<?php if ( ! empty( $hero_image['url'] ) ) : ?>
							<img
								class="nt-hero__img"
								src="<?php echo esc_url( $hero_image['url'] ); ?>"
								alt="<?php echo esc_attr( $hero_image['alt'] ); ?>"
								width="<?php echo esc_attr( (string) $hero_image['width'] ); ?>"
								height="<?php echo esc_attr( (string) $hero_image['height'] ); ?>"
								fetchpriority="high"
								decoding="async"
							>
						<?php endif; ?>
						<?php if ( ! empty( $hero_section['architecture_caption'] ) ) : ?>
							<figcaption class="nt-hero__caption">
								<?php echo esc_html( $hero_section['architecture_caption'] ); ?>
							</figcaption>
						<?php endif; ?>
					</figure>
				</div>
			</div>

			<?php if ( ! empty( $hero_stats ) ) : ?>
				<dl class="nt-hero__stats" aria-label="Key operational performance metrics">
					<?php foreach ( $hero_stats as $stat ) : ?>
						<?php if ( ! empty( $stat['value'] ) ) : ?>
							<div class="nt-stat-card">
								<dt class="nt-stat-card__label"><?php echo esc_html( $stat['label'] ); ?></dt>
								<dd class="nt-stat-card__value"><?php echo esc_html( $stat['value'] ); ?></dd>
								<?php if ( ! empty( $stat['detail'] ) ) : ?>
									<dd class="nt-stat-card__detail"><?php echo esc_html( $stat['detail'] ); ?></dd>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</div>
	</section>

	<!-- ================================================================== -->
	<!-- 2. SERVICES GRID (6 CARDS)                                         -->
	<!-- ================================================================== -->
	<section class="nt-section nt-services" id="services" aria-labelledby="nt-services-heading">
		<div class="nt-container">
			<div class="nt-section__header">
				<?php if ( ! empty( $services_section['section_tag'] ) ) : ?>
					<p class="nt-section__tag"><?php echo esc_html( $services_section['section_tag'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $services_section['section_title'] ) ) : ?>
					<h2 id="nt-services-heading" class="nt-section__title"><?php echo esc_html( $services_section['section_title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( ! empty( $services_section['section_subtitle'] ) ) : ?>
					<p class="nt-section__subtitle"><?php echo esc_html( $services_section['section_subtitle'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="nt-services__grid">
				<?php foreach ( $service_cards as $card ) : ?>
					<article class="nt-service-card">
						<div class="nt-service-card__top">
							<span class="nt-service-card__icon">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe static SVG sprite from theme helper
								echo nt_get_service_icon_svg( ! empty( $card['icon_key'] ) ? $card['icon_key'] : 'cloud' );
								?>
							</span>
							<?php if ( ! empty( $card['badge'] ) ) : ?>
								<span class="nt-service-card__badge"><?php echo esc_html( $card['badge'] ); ?></span>
							<?php endif; ?>
						</div>

						<h3 class="nt-service-card__title"><?php echo esc_html( $card['title'] ); ?></h3>

						<?php if ( ! empty( $card['description'] ) ) : ?>
							<p class="nt-service-card__desc"><?php echo esc_html( $card['description'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $card['deliverable_highlight'] ) ) : ?>
							<p class="nt-service-card__deliverable"><?php echo esc_html( $card['deliverable_highlight'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $card['link_text'] ) ) : ?>
							<a class="nt-service-card__link" href="<?php echo esc_attr( ! empty( $card['link_url'] ) ? $card['link_url'] : '#contact' ); ?>">
								<span><?php echo esc_html( $card['link_text'] ); ?></span>
								<svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
									<path d="M3.5 8h9m-3.5-3.5L12.5 8 9 11.5" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ================================================================== -->
	<!-- 3. SWIPER SLIDER #1: CASE STUDIES / PROJECTS                       -->
	<!-- ================================================================== -->
	<section class="nt-section nt-section--alt nt-cases" id="cases" aria-labelledby="nt-cases-heading">
		<div class="nt-container">
			<div class="nt-section__header nt-section__header--split">
				<div>
					<?php if ( ! empty( $cases_section['section_tag'] ) ) : ?>
						<p class="nt-section__tag"><?php echo esc_html( $cases_section['section_tag'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $cases_section['section_title'] ) ) : ?>
						<h2 id="nt-cases-heading" class="nt-section__title"><?php echo esc_html( $cases_section['section_title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( ! empty( $cases_section['section_subtitle'] ) ) : ?>
						<p class="nt-section__subtitle"><?php echo esc_html( $cases_section['section_subtitle'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="nt-slider-controls" aria-label="Case studies carousel controls">
					<button type="button" class="nt-slider-btn" id="nt-cases-prev" aria-label="Previous case study slide">
						<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
							<path d="M12.5 15 7.5 10l5-5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
					<button type="button" class="nt-slider-btn" id="nt-cases-next" aria-label="Next case study slide">
						<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
							<path d="m7.5 15 5-5-5-5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>
			</div>

			<div class="swiper nt-cases-swiper" id="nt-cases-swiper" data-cases-swiper>
				<div class="swiper-wrapper">
					<?php foreach ( $case_slides as $slide ) : ?>
						<?php $slide_img = isset( $slide['image'] ) ? nt_resolve_acf_image( $slide['image'], $slide['title'] ) : array(); ?>
						<article class="swiper-slide nt-case-card">
							<?php if ( ! empty( $slide_img['url'] ) ) : ?>
								<div class="nt-case-card__media">
									<img
										class="nt-case-card__img"
										src="<?php echo esc_url( $slide_img['url'] ); ?>"
										alt="<?php echo esc_attr( $slide_img['alt'] ); ?>"
										width="<?php echo esc_attr( (string) $slide_img['width'] ); ?>"
										height="<?php echo esc_attr( (string) $slide_img['height'] ); ?>"
										loading="lazy"
										decoding="async"
									>
									<?php if ( ! empty( $slide['category'] ) ) : ?>
										<span class="nt-case-card__category"><?php echo esc_html( $slide['category'] ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<div class="nt-case-card__body">
								<?php if ( ! empty( $slide['client_name'] ) ) : ?>
									<p class="nt-case-card__client"><?php echo esc_html( $slide['client_name'] ); ?></p>
								<?php endif; ?>

								<h3 class="nt-case-card__title"><?php echo esc_html( $slide['title'] ); ?></h3>

								<?php if ( ! empty( $slide['summary'] ) ) : ?>
									<p class="nt-case-card__summary"><?php echo esc_html( $slide['summary'] ); ?></p>
								<?php endif; ?>

								<div class="nt-case-card__metrics">
									<?php if ( ! empty( $slide['metric_value'] ) ) : ?>
										<div class="nt-case-card__primary-metric">
											<span class="nt-case-card__metric-val"><?php echo esc_html( $slide['metric_value'] ); ?></span>
											<span class="nt-case-card__metric-lbl"><?php echo esc_html( $slide['metric_label'] ); ?></span>
										</div>
									<?php endif; ?>
									<?php if ( ! empty( $slide['secondary_metric'] ) ) : ?>
										<p class="nt-case-card__secondary-metric"><?php echo esc_html( $slide['secondary_metric'] ); ?></p>
									<?php endif; ?>
								</div>

								<?php if ( ! empty( $slide['tech_stack'] ) ) : ?>
									<p class="nt-case-card__stack"><?php echo esc_html( $slide['tech_stack'] ); ?></p>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<div class="nt-swiper-footer">
					<div class="swiper-pagination nt-cases-pagination" id="nt-cases-pagination"></div>
					<?php if ( ! empty( $cases_section['demo_disclaimer'] ) ) : ?>
						<p class="nt-cases__disclaimer"><?php echo esc_html( $cases_section['demo_disclaimer'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ================================================================== -->
	<!-- 4. SWIPER SLIDER #2: CLIENT TESTIMONIALS                           -->
	<!-- ================================================================== -->
	<section class="nt-section nt-testimonials" id="testimonials" aria-labelledby="nt-testimonials-heading">
		<div class="nt-container">
			<div class="nt-section__header nt-section__header--split">
				<div>
					<?php if ( ! empty( $testimonials_section['section_tag'] ) ) : ?>
						<p class="nt-section__tag"><?php echo esc_html( $testimonials_section['section_tag'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $testimonials_section['section_title'] ) ) : ?>
						<h2 id="nt-testimonials-heading" class="nt-section__title"><?php echo esc_html( $testimonials_section['section_title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( ! empty( $testimonials_section['section_subtitle'] ) ) : ?>
						<p class="nt-section__subtitle"><?php echo esc_html( $testimonials_section['section_subtitle'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="nt-slider-controls" aria-label="Testimonials carousel controls">
					<button
						type="button"
						class="nt-autoplay-toggle"
						id="nt-testimonials-autoplay-toggle"
						aria-pressed="false"
						aria-label="Pause testimonial autoplay"
						data-autoplay-toggle
					>
						<span class="nt-autoplay-toggle__dot" aria-hidden="true"></span>
						<span data-autoplay-label>Pause Autoplay</span>
					</button>
					<button type="button" class="nt-slider-btn" id="nt-testimonials-prev" aria-label="Previous testimonial slide">
						<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
							<path d="M12.5 15 7.5 10l5-5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
					<button type="button" class="nt-slider-btn" id="nt-testimonials-next" aria-label="Next testimonial slide">
						<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
							<path d="m7.5 15 5-5-5-5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>
			</div>

			<div
				class="swiper nt-testimonials-swiper"
				id="nt-testimonials-swiper"
				data-testimonials-swiper
				data-autoplay-delay="<?php echo esc_attr( (string) $autoplay_delay ); ?>"
			>
				<div class="swiper-wrapper">
					<?php foreach ( $testimonial_items as $t_item ) : ?>
						<?php
						$avatar_img = isset( $t_item['avatar'] ) ? nt_resolve_acf_image( $t_item['avatar'], $t_item['author_name'] ) : array();
						$rating     = ! empty( $t_item['rating'] ) ? max( 1, min( 5, (int) $t_item['rating'] ) ) : 5;
						?>
						<article class="swiper-slide nt-testimonial-card">
							<div class="nt-testimonial-card__inner">
								<div class="nt-testimonial-card__meta-top">
									<div class="nt-testimonial-card__stars" aria-label="<?php echo esc_attr( $rating . ' out of 5 stars' ); ?>">
										<?php for ( $s = 0; $s < $rating; $s++ ) : ?>
											<svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">
												<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/>
											</svg>
										<?php endfor; ?>
									</div>
									<?php if ( ! empty( $t_item['engagement_scope'] ) ) : ?>
										<span class="nt-testimonial-card__scope"><?php echo esc_html( $t_item['engagement_scope'] ); ?></span>
									<?php endif; ?>
								</div>

								<blockquote class="nt-testimonial-card__quote">
									<p>&ldquo;<?php echo esc_html( $t_item['quote'] ); ?>&rdquo;</p>
								</blockquote>

								<div class="nt-testimonial-card__author">
									<?php if ( ! empty( $avatar_img['url'] ) ) : ?>
										<img
											class="nt-testimonial-card__avatar"
											src="<?php echo esc_url( $avatar_img['url'] ); ?>"
											alt="<?php echo esc_attr( $avatar_img['alt'] ); ?>"
											width="56"
											height="56"
											loading="lazy"
											decoding="async"
										>
									<?php endif; ?>
									<div>
										<div class="nt-testimonial-card__name"><?php echo esc_html( $t_item['author_name'] ); ?></div>
										<div class="nt-testimonial-card__role">
											<?php echo esc_html( $t_item['author_role'] ); ?>
											<?php if ( ! empty( $t_item['company'] ) ) : ?>
												<span> &middot; <?php echo esc_html( $t_item['company'] ); ?></span>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<div class="swiper-pagination nt-testimonials-pagination" id="nt-testimonials-pagination"></div>
			</div>
		</div>
	</section>

	<!-- ================================================================== -->
	<!-- 5. PROCESS ACCORDION SECTION (MULTI-PANEL EXPANSION)               -->
	<!-- ================================================================== -->
	<section class="nt-section nt-section--alt nt-process" id="process" aria-labelledby="nt-process-heading">
		<div class="nt-container">
			<div class="nt-section__header">
				<?php if ( ! empty( $process_section['section_tag'] ) ) : ?>
					<p class="nt-section__tag"><?php echo esc_html( $process_section['section_tag'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $process_section['section_title'] ) ) : ?>
					<h2 id="nt-process-heading" class="nt-section__title"><?php echo esc_html( $process_section['section_title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( ! empty( $process_section['section_subtitle'] ) ) : ?>
					<p class="nt-section__subtitle"><?php echo esc_html( $process_section['section_subtitle'] ); ?></p>
				<?php endif; ?>
			</div>

			<div
				class="nt-accordion nt-process-accordion"
				id="nt-process-accordion"
				data-accordion="process"
				data-allow-multiple="<?php echo $allow_multi_proc ? 'true' : 'false'; ?>"
			>
				<?php foreach ( $process_steps as $idx => $step ) : ?>
					<?php
					$is_open      = ! empty( $step['default_open'] );
					$trigger_id   = 'nt-proc-trigger-' . ( $idx + 1 );
					$panel_id     = 'nt-proc-panel-' . ( $idx + 1 );
					$deliverables = ! empty( $step['deliverables'] ) ? array_filter( array_map( 'trim', explode( "\n", (string) $step['deliverables'] ) ) ) : array();
					?>
					<div class="nt-accordion__item <?php echo $is_open ? 'is-open' : ''; ?>" data-accordion-item>
						<h3 class="nt-accordion__heading">
							<button
								type="button"
								class="nt-accordion__trigger"
								id="<?php echo esc_attr( $trigger_id ); ?>"
								aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
								aria-controls="<?php echo esc_attr( $panel_id ); ?>"
								data-accordion-trigger
							>
								<span class="nt-process__step-num"><?php echo esc_html( $step['step_number'] ); ?></span>
								<span class="nt-accordion__title-group">
									<span class="nt-accordion__title"><?php echo esc_html( $step['title'] ); ?></span>
									<?php if ( ! empty( $step['duration_badge'] ) ) : ?>
										<span class="nt-process__duration"><?php echo esc_html( $step['duration_badge'] ); ?></span>
									<?php endif; ?>
								</span>
								<span class="nt-accordion__icon" aria-hidden="true">
									<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" focusable="false">
										<path d="M5 7.5 10 12.5 15 7.5" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</span>
							</button>
						</h3>

						<div
							id="<?php echo esc_attr( $panel_id ); ?>"
							class="nt-accordion__panel"
							role="region"
							aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
							<?php echo $is_open ? '' : 'hidden'; ?>
							data-accordion-panel
						>
							<div class="nt-accordion__panel-inner">
								<?php if ( ! empty( $step['summary'] ) ) : ?>
									<p class="nt-process__summary"><?php echo esc_html( $step['summary'] ); ?></p>
								<?php endif; ?>

								<?php if ( ! empty( $deliverables ) ) : ?>
									<div class="nt-process__deliverables-box">
										<h4 class="nt-process__subhead">Concrete Phase Deliverables:</h4>
										<ul class="nt-process__deliverables-list">
											<?php foreach ( $deliverables as $deliv_line ) : ?>
												<li><?php echo esc_html( $deliv_line ); ?></li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $step['governance_note'] ) ) : ?>
									<p class="nt-process__gate"><?php echo esc_html( $step['governance_note'] ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ================================================================== -->
	<!-- 6. FAQ ACCORDION SECTION                                           -->
	<!-- ================================================================== -->
	<section class="nt-section nt-faq" id="faq" aria-labelledby="nt-faq-heading">
		<div class="nt-container nt-container--narrow">
			<div class="nt-section__header">
				<?php if ( ! empty( $faq_section['section_tag'] ) ) : ?>
					<p class="nt-section__tag"><?php echo esc_html( $faq_section['section_tag'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $faq_section['section_title'] ) ) : ?>
					<h2 id="nt-faq-heading" class="nt-section__title"><?php echo esc_html( $faq_section['section_title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( ! empty( $faq_section['section_subtitle'] ) ) : ?>
					<p class="nt-section__subtitle"><?php echo esc_html( $faq_section['section_subtitle'] ); ?></p>
				<?php endif; ?>
			</div>

			<div
				class="nt-accordion nt-faq-accordion"
				id="nt-faq-accordion"
				data-accordion="faq"
				data-allow-multiple="false"
			>
				<?php foreach ( $faq_items as $f_idx => $faq ) : ?>
					<?php
					$faq_open    = ! empty( $faq['default_open'] );
					$f_trigger   = 'nt-faq-trigger-' . ( $f_idx + 1 );
					$f_panel     = 'nt-faq-panel-' . ( $f_idx + 1 );
					?>
					<div class="nt-accordion__item <?php echo $faq_open ? 'is-open' : ''; ?>" data-accordion-item>
						<h3 class="nt-accordion__heading">
							<button
								type="button"
								class="nt-accordion__trigger"
								id="<?php echo esc_attr( $f_trigger ); ?>"
								aria-expanded="<?php echo $faq_open ? 'true' : 'false'; ?>"
								aria-controls="<?php echo esc_attr( $f_panel ); ?>"
								data-accordion-trigger
							>
								<span class="nt-accordion__title-group">
									<?php if ( ! empty( $faq['category_badge'] ) ) : ?>
										<span class="nt-faq__badge"><?php echo esc_html( $faq['category_badge'] ); ?></span>
									<?php endif; ?>
									<span class="nt-accordion__title"><?php echo esc_html( $faq['question'] ); ?></span>
								</span>
								<span class="nt-accordion__icon" aria-hidden="true">
									<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" focusable="false">
										<path d="M5 7.5 10 12.5 15 7.5" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</span>
							</button>
						</h3>

						<div
							id="<?php echo esc_attr( $f_panel ); ?>"
							class="nt-accordion__panel"
							role="region"
							aria-labelledby="<?php echo esc_attr( $f_trigger ); ?>"
							<?php echo $faq_open ? '' : 'hidden'; ?>
							data-accordion-panel
						>
							<div class="nt-accordion__panel-inner">
								<p class="nt-faq__answer"><?php echo esc_html( $faq['answer'] ); ?></p>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ================================================================== -->
	<!-- 7. CONTACT SECTION (CONTACT FORM 7 + LOCAL MAILPIT)                -->
	<!-- ================================================================== -->
	<section class="nt-section nt-section--alt nt-contact" id="contact" aria-labelledby="nt-contact-heading">
		<div class="nt-container">
			<div class="nt-contact__grid">
				<div class="nt-contact__info">
					<?php if ( ! empty( $contact_section['contact_tag'] ) ) : ?>
						<p class="nt-section__tag"><?php echo esc_html( $contact_section['contact_tag'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $contact_section['contact_title'] ) ) : ?>
						<h2 id="nt-contact-heading" class="nt-section__title"><?php echo esc_html( $contact_section['contact_title'] ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $contact_section['contact_subtitle'] ) ) : ?>
						<p class="nt-contact__lead"><?php echo esc_html( $contact_section['contact_subtitle'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $contact_section['response_sla_note'] ) ) : ?>
						<div class="nt-contact__sla-badge">
							<span class="nt-contact__sla-dot" aria-hidden="true"></span>
							<span><?php echo esc_html( $contact_section['response_sla_note'] ); ?></span>
						</div>
					<?php endif; ?>

					<dl class="nt-contact__details">
						<?php if ( ! empty( $contact_section['company_email'] ) ) : ?>
							<div class="nt-contact__detail-row">
								<dt>Architecture Desk</dt>
								<dd><a href="mailto:<?php echo esc_attr( $contact_section['company_email'] ); ?>"><?php echo esc_html( $contact_section['company_email'] ); ?></a></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $contact_section['company_phone'] ) ) : ?>
							<div class="nt-contact__detail-row">
								<dt>Direct Telephone</dt>
								<dd><?php echo esc_html( $contact_section['company_phone'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $contact_section['company_address'] ) ) : ?>
							<div class="nt-contact__detail-row">
								<dt>Headquarters</dt>
								<dd><?php echo esc_html( $contact_section['company_address'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $contact_section['office_hours'] ) ) : ?>
							<div class="nt-contact__detail-row">
								<dt>Support Window</dt>
								<dd><?php echo esc_html( $contact_section['office_hours'] ); ?></dd>
							</div>
						<?php endif; ?>
					</dl>
				</div>

				<div class="nt-contact__form-card">
					<?php
					if ( ! empty( $contact_section['cf7_shortcode'] ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output from Contact Form 7
						echo do_shortcode( (string) $contact_section['cf7_shortcode'] );
					}
					?>
				</div>
			</div>
		</div>
	</section>

</main>

<?php get_template_part( 'template-parts/novatech', 'footer' ); ?>

<?php wp_footer(); ?>
</body>
</html>
