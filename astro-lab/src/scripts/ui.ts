/**
 * NovaTech Solutions - frontend interactive modules (port of the WordPress theme's `novatech.js`).
 *
 * 1. Sticky header + accessible mobile navigation drawer
 * 2. Swiper #1: case studies carousel
 * 3. Swiper #2: testimonials carousel (loop, autoplay, manual pause/resume, reduced-motion aware)
 * 4. Accessible accordions (process = multi-expand, FAQ = single-expand, arrow/Home/End keys)
 *
 * Behaviour and Swiper options are intentionally identical to the WordPress implementation.
 * The only structural differences: Swiper is imported as an ES module (tree-shaken to the used
 * modules) instead of the global `window.Swiper` bundle, and the code is typed.
 */
import Swiper from 'swiper';
import { Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';

const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
const prefersReducedMotion = (): boolean => Boolean(reducedMotionQuery && reducedMotionQuery.matches);

function initHeaderAndMobileNav(): void {
  const header = document.querySelector<HTMLElement>('[data-sticky-header]');
  const toggleBtn = document.querySelector<HTMLButtonElement>('[data-mobile-toggle]');
  const drawer = document.querySelector<HTMLElement>('[data-mobile-drawer]');

  if (header) {
    const updateStickyState = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 16);
    };
    updateStickyState();
    window.addEventListener('scroll', updateStickyState, { passive: true });
  }

  if (!toggleBtn || !drawer) return;

  const closeDrawer = (returnFocus: boolean) => {
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.setAttribute('aria-label', 'Open navigation menu');
    drawer.hidden = true;
    if (returnFocus) toggleBtn.focus();
  };

  const openDrawer = () => {
    toggleBtn.setAttribute('aria-expanded', 'true');
    toggleBtn.setAttribute('aria-label', 'Close navigation menu');
    drawer.hidden = false;
  };

  toggleBtn.addEventListener('click', () => {
    if (toggleBtn.getAttribute('aria-expanded') === 'true') closeDrawer(false);
    else openDrawer();
  });

  drawer.querySelectorAll('[data-mobile-link]').forEach((link) => {
    link.addEventListener('click', () => closeDrawer(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && toggleBtn.getAttribute('aria-expanded') === 'true') {
      event.preventDefault();
      closeDrawer(true);
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 960 && toggleBtn.getAttribute('aria-expanded') === 'true') closeDrawer(false);
  });
}

function initCasesSwiper(): Swiper | null {
  const container = document.getElementById('nt-cases-swiper');
  if (!container) return null;

  return new Swiper(container, {
    modules: [Navigation, Pagination, Keyboard],
    slidesPerView: 1,
    spaceBetween: 16,
    speed: prefersReducedMotion() ? 0 : 450,
    grabCursor: true,
    watchOverflow: true,
    keyboard: { enabled: true, onlyInViewport: true },
    navigation: { prevEl: '#nt-cases-prev', nextEl: '#nt-cases-next' },
    pagination: { el: '#nt-cases-pagination', clickable: true },
    breakpoints: {
      768: { slidesPerView: 2, spaceBetween: 20 },
      1024: { slidesPerView: 3, spaceBetween: 24 },
    },
  });
}

function initTestimonialsSwiper(): Swiper | null {
  const container = document.getElementById('nt-testimonials-swiper');
  if (!container) return null;

  const toggleBtn = document.getElementById('nt-testimonials-autoplay-toggle');
  const toggleLabel = toggleBtn?.querySelector('[data-autoplay-label]') ?? null;
  const delayAttr = parseInt(container.getAttribute('data-autoplay-delay') || '6000', 10);
  const autoplayDelay = Number.isFinite(delayAttr) && delayAttr >= 1000 ? delayAttr : 6000;
  const startPaused = prefersReducedMotion();

  let isManuallyPaused = startPaused;

  const syncAutoplayButtonUI = (paused: boolean) => {
    if (!toggleBtn) return;
    toggleBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
    toggleBtn.setAttribute('aria-label', paused ? 'Resume testimonial autoplay' : 'Pause testimonial autoplay');
    if (toggleLabel) toggleLabel.textContent = paused ? 'Resume Autoplay' : 'Pause Autoplay';
  };

  const swiper = new Swiper(container, {
    modules: [Navigation, Pagination, Keyboard, Autoplay],
    slidesPerView: 1,
    spaceBetween: 24,
    loop: true,
    speed: prefersReducedMotion() ? 0 : 500,
    grabCursor: true,
    keyboard: { enabled: true, onlyInViewport: true },
    autoplay: { delay: autoplayDelay, disableOnInteraction: false, pauseOnMouseEnter: true },
    navigation: { prevEl: '#nt-testimonials-prev', nextEl: '#nt-testimonials-next' },
    pagination: { el: '#nt-testimonials-pagination', clickable: true },
  });

  if (startPaused && swiper.autoplay) {
    swiper.autoplay.stop();
    syncAutoplayButtonUI(true);
  } else {
    syncAutoplayButtonUI(false);
  }

  const pauseFromInteraction = () => {
    if (swiper.autoplay && swiper.autoplay.running) {
      swiper.autoplay.stop();
      isManuallyPaused = true;
      syncAutoplayButtonUI(true);
    }
  };

  swiper.on('touchStart', pauseFromInteraction);
  document.getElementById('nt-testimonials-prev')?.addEventListener('click', pauseFromInteraction);
  document.getElementById('nt-testimonials-next')?.addEventListener('click', pauseFromInteraction);

  toggleBtn?.addEventListener('click', () => {
    if (!swiper.autoplay) return;
    if (isManuallyPaused || !swiper.autoplay.running) {
      swiper.autoplay.start();
      isManuallyPaused = false;
      syncAutoplayButtonUI(false);
    } else {
      swiper.autoplay.stop();
      isManuallyPaused = true;
      syncAutoplayButtonUI(true);
    }
  });

  reducedMotionQuery.addEventListener?.('change', (e) => {
    if (e.matches && swiper.autoplay) {
      swiper.autoplay.stop();
      isManuallyPaused = true;
      syncAutoplayButtonUI(true);
    }
  });

  return swiper;
}

function initAccordions(): void {
  document.querySelectorAll<HTMLElement>('[data-accordion]').forEach((group) => {
    const allowMultiple = group.getAttribute('data-allow-multiple') === 'true';
    const triggers = Array.from(group.querySelectorAll<HTMLButtonElement>('[data-accordion-trigger]'));

    const setItemState = (trigger: HTMLButtonElement, expand: boolean) => {
      const item = trigger.closest('[data-accordion-item]');
      const panelId = trigger.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;

      trigger.setAttribute('aria-expanded', expand ? 'true' : 'false');
      item?.classList.toggle('is-open', expand);
      if (panel) panel.hidden = !expand;
    };

    triggers.forEach((trigger, index) => {
      trigger.addEventListener('click', () => {
        const targetState = trigger.getAttribute('aria-expanded') !== 'true';
        if (!allowMultiple && targetState) {
          triggers.forEach((other) => {
            if (other !== trigger) setItemState(other, false);
          });
        }
        setItemState(trigger, targetState);
      });

      // Arrow key navigation between accordion headers (WAI-ARIA Accordion Pattern)
      trigger.addEventListener('keydown', (event) => {
        let targetIndex: number | null = null;
        if (event.key === 'ArrowDown') targetIndex = (index + 1) % triggers.length;
        else if (event.key === 'ArrowUp') targetIndex = (index - 1 + triggers.length) % triggers.length;
        else if (event.key === 'Home') targetIndex = 0;
        else if (event.key === 'End') targetIndex = triggers.length - 1;

        if (targetIndex !== null) {
          event.preventDefault();
          triggers[targetIndex].focus();
        }
      });
    });
  });
}

initHeaderAndMobileNav();
initCasesSwiper();
initTestimonialsSwiper();
initAccordions();
