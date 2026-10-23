/**
 * NovaTech Solutions — Frontend Interactive Modules (Vanilla JS + Swiper 11)
 *
 * Modules:
 * 1. Sticky Header & Accessible Mobile Navigation Drawer (Escape key, focus return, link auto-close)
 * 2. Swiper #1: Case Studies Carousel (1 / 2 / 3 responsive slidesPerView, arrows, pagination, touch)
 * 3. Swiper #2: Testimonials Carousel (1 slide, loop, autoplay, manual pause/resume button, reduced-motion aware)
 * 4. Accessible Accordions: Process Accordion (multi-expand) & FAQ Accordion (single-expand + keyboard arrows)
 */
(function () {
	'use strict';

	const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
	const prefersReducedMotion = function () {
		return Boolean(reducedMotionQuery && reducedMotionQuery.matches);
	};

	/**
	 * 1. Sticky Header & Mobile Navigation Drawer
	 */
	function initHeaderAndMobileNav() {
		const header = document.querySelector('[data-sticky-header]');
		const toggleBtn = document.querySelector('[data-mobile-toggle]');
		const drawer = document.querySelector('[data-mobile-drawer]');

		if (header) {
			const updateStickyState = function () {
				if (window.scrollY > 16) {
					header.classList.add('is-scrolled');
				} else {
					header.classList.remove('is-scrolled');
				}
			};
			updateStickyState();
			window.addEventListener('scroll', updateStickyState, { passive: true });
		}

		if (!toggleBtn || !drawer) {
			return;
		}

		const closeDrawer = function (returnFocus) {
			toggleBtn.setAttribute('aria-expanded', 'false');
			toggleBtn.setAttribute('aria-label', 'Open navigation menu');
			drawer.hidden = true;
			if (returnFocus) {
				toggleBtn.focus();
			}
		};

		const openDrawer = function () {
			toggleBtn.setAttribute('aria-expanded', 'true');
			toggleBtn.setAttribute('aria-label', 'Close navigation menu');
			drawer.hidden = false;
		};

		toggleBtn.addEventListener('click', function () {
			const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
			if (isExpanded) {
				closeDrawer(false);
			} else {
				openDrawer();
			}
		});

		// Close mobile drawer when clicking any anchor link inside it
		const drawerLinks = drawer.querySelectorAll('[data-mobile-link]');
		drawerLinks.forEach(function (link) {
			link.addEventListener('click', function () {
				closeDrawer(false);
			});
		});

		// Close on Escape key and return focus to toggle button
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && toggleBtn.getAttribute('aria-expanded') === 'true') {
				event.preventDefault();
				closeDrawer(true);
			}
		});

		// Close automatically if viewport is resized to desktop width
		window.addEventListener('resize', function () {
			if (window.innerWidth >= 960 && toggleBtn.getAttribute('aria-expanded') === 'true') {
				closeDrawer(false);
			}
		});
	}

	/**
	 * 2. Swiper #1 — Case Studies / Projects Carousel
	 */
	function initCasesSwiper() {
		const container = document.getElementById('nt-cases-swiper');
		if (!container || typeof window.Swiper !== 'function') {
			return null;
		}

		return new window.Swiper('#nt-cases-swiper', {
			slidesPerView: 1,
			spaceBetween: 16,
			speed: prefersReducedMotion() ? 0 : 450,
			grabCursor: true,
			watchOverflow: true,
			keyboard: {
				enabled: true,
				onlyInViewport: true,
			},
			navigation: {
				prevEl: '#nt-cases-prev',
				nextEl: '#nt-cases-next',
			},
			pagination: {
				el: '#nt-cases-pagination',
				clickable: true,
			},
			breakpoints: {
				768: {
					slidesPerView: 2,
					spaceBetween: 20,
				},
				1024: {
					slidesPerView: 3,
					spaceBetween: 24,
				},
			},
		});
	}

	/**
	 * 3. Swiper #2 — Client Testimonials Carousel (with Autoplay & Manual Pause/Resume)
	 */
	function initTestimonialsSwiper() {
		const container = document.getElementById('nt-testimonials-swiper');
		if (!container || typeof window.Swiper !== 'function') {
			return null;
		}

		const toggleBtn = document.getElementById('nt-testimonials-autoplay-toggle');
		const toggleLabel = toggleBtn ? toggleBtn.querySelector('[data-autoplay-label]') : null;
		const delayAttr = parseInt(container.getAttribute('data-autoplay-delay') || '6000', 10);
		const autoplayDelay = Number.isFinite(delayAttr) && delayAttr >= 1000 ? delayAttr : 6000;
		const startPaused = prefersReducedMotion();

		let isManuallyPaused = startPaused;

		const syncAutoplayButtonUI = function (paused) {
			if (!toggleBtn) {
				return;
			}
			toggleBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
			toggleBtn.setAttribute(
				'aria-label',
				paused ? 'Resume testimonial autoplay' : 'Pause testimonial autoplay'
			);
			if (toggleLabel) {
				toggleLabel.textContent = paused ? 'Resume Autoplay' : 'Pause Autoplay';
			}
		};

		const swiperInstance = new window.Swiper('#nt-testimonials-swiper', {
			slidesPerView: 1,
			spaceBetween: 24,
			loop: true,
			speed: prefersReducedMotion() ? 0 : 500,
			grabCursor: true,
			keyboard: {
				enabled: true,
				onlyInViewport: true,
			},
			autoplay: {
				delay: autoplayDelay,
				disableOnInteraction: false,
				pauseOnMouseEnter: true,
			},
			navigation: {
				prevEl: '#nt-testimonials-prev',
				nextEl: '#nt-testimonials-next',
			},
			pagination: {
				el: '#nt-testimonials-pagination',
				clickable: true,
			},
		});

		if (startPaused && swiperInstance.autoplay) {
			swiperInstance.autoplay.stop();
			syncAutoplayButtonUI(true);
		} else {
			syncAutoplayButtonUI(false);
		}

		// Pause autoplay if user manually swipes or uses arrow buttons
		const pauseFromInteraction = function () {
			if (swiperInstance.autoplay && swiperInstance.autoplay.running) {
				swiperInstance.autoplay.stop();
				isManuallyPaused = true;
				syncAutoplayButtonUI(true);
			}
		};

		swiperInstance.on('touchStart', pauseFromInteraction);

		const prevBtn = document.getElementById('nt-testimonials-prev');
		const nextBtn = document.getElementById('nt-testimonials-next');
		if (prevBtn) {
			prevBtn.addEventListener('click', pauseFromInteraction);
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', pauseFromInteraction);
		}

		if (toggleBtn) {
			toggleBtn.addEventListener('click', function () {
				if (!swiperInstance.autoplay) {
					return;
				}
				if (isManuallyPaused || !swiperInstance.autoplay.running) {
					swiperInstance.autoplay.start();
					isManuallyPaused = false;
					syncAutoplayButtonUI(false);
				} else {
					swiperInstance.autoplay.stop();
					isManuallyPaused = true;
					syncAutoplayButtonUI(true);
				}
			});
		}

		// Listen for dynamic OS reduced-motion preference changes
		if (reducedMotionQuery && typeof reducedMotionQuery.addEventListener === 'function') {
			reducedMotionQuery.addEventListener('change', function (e) {
				if (e.matches && swiperInstance.autoplay) {
					swiperInstance.autoplay.stop();
					isManuallyPaused = true;
					syncAutoplayButtonUI(true);
				}
			});
		}

		return swiperInstance;
	}

	/**
	 * 4. Accessible Accordions (Process Multi-Expand & FAQ Single-Expand)
	 */
	function initAccordions() {
		const accordionGroups = document.querySelectorAll('[data-accordion]');

		accordionGroups.forEach(function (group) {
			const allowMultiple = group.getAttribute('data-allow-multiple') === 'true';
			const triggers = Array.from(group.querySelectorAll('[data-accordion-trigger]'));

			const setItemState = function (trigger, expand) {
				const item = trigger.closest('[data-accordion-item]');
				const panelId = trigger.getAttribute('aria-controls');
				const panel = panelId ? document.getElementById(panelId) : null;

				trigger.setAttribute('aria-expanded', expand ? 'true' : 'false');
				if (item) {
					item.classList.toggle('is-open', expand);
				}
				if (panel) {
					panel.hidden = !expand;
				}
			};

			triggers.forEach(function (trigger, index) {
				trigger.addEventListener('click', function () {
					const isCurrentlyOpen = trigger.getAttribute('aria-expanded') === 'true';
					const targetState = !isCurrentlyOpen;

					if (!allowMultiple && targetState) {
						triggers.forEach(function (otherTrigger) {
							if (otherTrigger !== trigger) {
								setItemState(otherTrigger, false);
							}
						});
					}

					setItemState(trigger, targetState);
				});

				// Arrow key navigation between accordion headers (WAI-ARIA Accordion Pattern)
				trigger.addEventListener('keydown', function (event) {
					let targetIndex = null;
					if (event.key === 'ArrowDown') {
						targetIndex = (index + 1) % triggers.length;
					} else if (event.key === 'ArrowUp') {
						targetIndex = (index - 1 + triggers.length) % triggers.length;
					} else if (event.key === 'Home') {
						targetIndex = 0;
					} else if (event.key === 'End') {
						targetIndex = triggers.length - 1;
					}

					if (targetIndex !== null) {
						event.preventDefault();
						triggers[targetIndex].focus();
					}
				});
			});
		});
	}

	/**
	 * Initialize all interactive modules when DOM is ready
	 */
	function initAll() {
		initHeaderAndMobileNav();
		initCasesSwiper();
		initTestimonialsSwiper();
		initAccordions();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
