/**
 * End-to-end browser tests for the Astro build (real Chrome via playwright-core).
 *
 * Serves the *built* `dist/` folder with a plain static server - WordPress is not involved at all -
 * and starts the real form backend against the real local Mailpit (http://127.0.0.1:8025).
 * Prerequisites: `npm run build`, Mailpit reachable.
 */
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { existsSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import http from 'node:http';
import { dirname, extname, join, normalize } from 'node:path';
import { after, before, describe, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');
const MAILPIT = process.env.MAILPIT_URL || 'http://127.0.0.1:8025';
const SITE_PORT = Number(process.env.E2E_SITE_PORT || 4321);
const FORM_PORT = Number(process.env.E2E_FORM_PORT || 4322);
const SITE = `http://127.0.0.1:${SITE_PORT}`;
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.json': 'application/json', '.svg': 'image/svg+xml' };

let staticServer;
let formProc;
let browser;

async function startStatic() {
  staticServer = http.createServer(async (req, res) => {
    let p = decodeURIComponent(new URL(req.url, SITE).pathname);
    if (p.endsWith('/')) p += 'index.html';
    const file = normalize(join(DIST, p));
    if (!file.startsWith(DIST) || !existsSync(file)) {
      res.writeHead(404).end('not found');
      return;
    }
    res.writeHead(200, { 'Content-Type': MIME[extname(file)] || 'application/octet-stream' }).end(await readFile(file));
  });
  staticServer.listen(SITE_PORT, '127.0.0.1');
  await once(staticServer, 'listening');
}

async function startForm() {
  formProc = spawn(process.execPath, ['server/form-server.mjs'], {
    cwd: ROOT,
    env: { ...process.env, FORM_PORT: String(FORM_PORT), MAILPIT_URL: MAILPIT, ALLOWED_ORIGINS: SITE },
    stdio: 'ignore',
  });
  for (let i = 0; i < 50; i++) {
    try {
      if ((await fetch(`http://127.0.0.1:${FORM_PORT}/api/health`)).ok) return;
    } catch {}
    await new Promise((r) => setTimeout(r, 100));
  }
  throw new Error('form server did not start');
}

/** Opens the page in a fresh context; collects console errors, page errors and failed/foreign requests. */
async function openPage(viewport, { reducedMotion = 'no-preference' } = {}) {
  const context = await browser.newContext({ viewport, reducedMotion });
  const page = await context.newPage();
  const problems = { console: [], pageErrors: [], failed: [], badStatus: [], foreign: [] };
  page.on('console', (m) => m.type() === 'error' && problems.console.push(m.text()));
  page.on('pageerror', (e) => problems.pageErrors.push(String(e)));
  page.on('requestfailed', (r) => problems.failed.push(`${r.url()} ${r.failure()?.errorText}`));
  page.on('response', (r) => r.status() >= 400 && problems.badStatus.push(`${r.status()} ${r.url()}`));
  page.on('request', (r) => {
    const u = new URL(r.url());
    if (u.origin !== SITE && u.origin !== `http://127.0.0.1:${FORM_PORT}`) problems.foreign.push(r.url());
  });
  await page.goto(`${SITE}/`, { waitUntil: 'networkidle' });
  return { page, context, problems };
}

before(async () => {
  assert.ok(existsSync(join(DIST, 'index.html')), 'dist/ missing - run `npm run build` first');
  const mp = await fetch(`${MAILPIT}/api/v1/info`).catch(() => null);
  assert.ok(mp?.ok, `Mailpit not reachable at ${MAILPIT}`);
  await startStatic();
  await startForm();
  browser = await chromium.launch({ channel: 'chrome', headless: true });
});
after(async () => {
  await browser?.close();
  formProc?.kill();
  staticServer?.close();
});

describe('content & structure', () => {
  test('renders all sections in WordPress order with expected counts', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const ids = await page.$$eval('main > section', (s) => s.map((x) => x.id));
    assert.deepEqual(ids, ['hero', 'services', 'cases', 'testimonials', 'process', 'faq', 'contact']);
    assert.equal(await page.locator('.nt-stat-card').count(), 3);
    assert.equal(await page.locator('.nt-service-card').count(), 6);
    assert.equal(await page.locator('#nt-cases-swiper .swiper-slide').count(), 5);
    // testimonials use loop mode: Swiper adds duplicate slides, count the originals
    assert.equal(await page.locator('#nt-testimonials-swiper .swiper-slide:not(.swiper-slide-duplicate)').count(), 4);
    assert.equal(await page.locator('#nt-process-accordion .nt-accordion__item').count(), 4);
    assert.equal(await page.locator('#nt-faq-accordion .nt-accordion__item').count(), 5);
    assert.equal(await page.locator('.nt-nav__link').count(), 5);
    assert.equal(await page.locator('h1').count(), 1);
    assert.equal(await page.title(), 'NovaTech Solutions \u2013 NovaTech Solutions');
    assert.equal(await page.locator('html').getAttribute('lang'), 'en-US');
    await context.close();
  });

  test('page text is English only (no Cyrillic)', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const text = await page.evaluate(() => document.body.innerText + document.title);
    assert.doesNotMatch(text, /[\u0400-\u04FF]/);
    await context.close();
  });

  test('all images load with natural size > 0 and the hero keeps ACF dimensions', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    await page.$$eval('img[loading="lazy"]', (imgs) => imgs.forEach((i) => (i.loading = 'eager')));
    await page.waitForFunction(() => [...document.images].every((i) => i.complete), null, { timeout: 10000 });
    const imgs = await page.$$eval('img', (list) => list.map((i) => ({ src: i.src, w: i.naturalWidth, alt: i.alt })));
    assert.equal(imgs.length, 10);
    for (const i of imgs) {
      assert.ok(i.w > 0, `broken image ${i.src}`);
      assert.ok(i.alt.length > 0, `missing alt ${i.src}`);
    }
    await context.close();
  });

  test('anchor links target existing ids', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const missing = await page.$$eval('a[href^="#"]', (as) => as.map((a) => a.getAttribute('href')).filter((h) => h.length > 1 && !document.getElementById(h.slice(1))));
    assert.deepEqual(missing, []);
    await page.click('.nt-nav__link[href="#faq"]');
    await page.waitForFunction(() => location.hash === '#faq');
    await context.close();
  });
});

describe('independence & console health', () => {
  test('no console errors, failed requests, 4xx/5xx or non-local requests (desktop)', async () => {
    const { page, context, problems } = await openPage({ width: 1440, height: 900 });
    await page.waitForTimeout(500);
    assert.deepEqual(problems, { console: [], pageErrors: [], failed: [], badStatus: [], foreign: [] });
    await context.close();
  });
  test('built HTML/JS/CSS contain no reference to the WordPress host', async () => {
    const files = [];
    const walk = async (dir) => {
      const { readdir } = await import('node:fs/promises');
      for (const e of await readdir(dir, { withFileTypes: true })) {
        const p = join(dir, e.name);
        if (e.isDirectory()) await walk(p);
        else if (/\.(html|js|css|json)$/.test(e.name)) files.push(p);
      }
    };
    await walk(DIST);
    assert.ok(files.length > 0);
    for (const f of files) assert.doesNotMatch(await readFile(f, 'utf8'), /localhost:8001|wp-content|wp-json/, f);
  });
});

describe('responsive layout', () => {
  for (const [w, h, cols, slides] of [
    [375, 812, 1, 1],
    [768, 1024, 2, 2],
    [1440, 900, 3, 3],
  ]) {
    test(`${w}px: no horizontal overflow, ${cols}-col services grid, ${slides} visible case slide(s)`, async () => {
      const { page, context } = await openPage({ width: w, height: h });
      const m = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        cols: getComputedStyle(document.querySelector('.nt-services__grid')).gridTemplateColumns.split(' ').length,
        slidesPerView: document.getElementById('nt-cases-swiper').swiper?.params.slidesPerView,
        navVisible: getComputedStyle(document.querySelector('.nt-nav')).display !== 'none',
        toggleVisible: getComputedStyle(document.querySelector('[data-mobile-toggle]')).display !== 'none',
      }));
      assert.ok(m.overflow <= 0, `horizontal overflow ${m.overflow}px`);
      assert.equal(m.cols, cols);
      assert.equal(m.slidesPerView, slides);
      if (w === 1440) assert.deepEqual([m.navVisible, m.toggleVisible], [true, false]);
      if (w === 375) assert.deepEqual([m.navVisible, m.toggleVisible], [false, true]);
      await context.close();
    });
  }
});

describe('mobile navigation', () => {
  test('opens, links navigate and close it, Escape closes and restores focus', async () => {
    const { page, context } = await openPage({ width: 375, height: 812 });
    const toggle = page.locator('[data-mobile-toggle]');
    const drawer = page.locator('[data-mobile-drawer]');
    assert.equal(await drawer.isHidden(), true);
    await toggle.click();
    assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await toggle.getAttribute('aria-label'), 'Close navigation menu');
    assert.equal(await drawer.isVisible(), true);
    assert.equal(await drawer.locator('[data-mobile-link]').count(), 6); // 5 nav + CTA

    await page.keyboard.press('Escape');
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
    assert.equal(await drawer.isHidden(), true);
    assert.equal(await page.evaluate(() => document.activeElement?.id), 'nt-mobile-menu-toggle');

    await toggle.click();
    await drawer.locator('a[href="#services"]').click();
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
    await page.waitForFunction(() => location.hash === '#services');
    await context.close();
  });
});

describe('Swiper sliders', () => {
  test('cases slider: real Swiper, next/prev, pagination bullets, slide order', async () => {
    const { page, context } = await openPage({ width: 768, height: 1024 });
    const titles = await page.$$eval('#nt-cases-swiper .nt-case-card__title', (n) => n.map((x) => x.textContent.trim()));
    assert.equal(titles.length, 5);
    const sw = (fn) => page.evaluate(fn);
    assert.equal(await sw(() => document.getElementById('nt-cases-swiper').swiper.activeIndex), 0);
    await page.click('#nt-cases-next');
    await page.waitForFunction(() => document.getElementById('nt-cases-swiper').swiper.activeIndex === 1);
    await page.click('#nt-cases-prev');
    await page.waitForFunction(() => document.getElementById('nt-cases-swiper').swiper.activeIndex === 0);
    const bullets = await page.locator('#nt-cases-pagination .swiper-pagination-bullet').count();
    assert.ok(bullets >= 2, `pagination bullets: ${bullets}`);
    await page.locator('#nt-cases-pagination .swiper-pagination-bullet').nth(1).click();
    await page.waitForFunction(() => document.getElementById('nt-cases-swiper').swiper.activeIndex >= 1);
    await context.close();
  });

  test('testimonials slider: loop, autoplay running, pause/resume button, arrows pause autoplay', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const st = () =>
      page.evaluate(() => {
        const s = document.getElementById('nt-testimonials-swiper').swiper;
        return { running: s.autoplay.running, loop: s.params.loop, real: s.realIndex };
      });
    let s = await st();
    assert.deepEqual([s.running, s.loop], [true, true]);
    const btn = page.locator('#nt-testimonials-autoplay-toggle');
    assert.equal(await btn.getAttribute('aria-pressed'), 'false');
    await btn.click();
    assert.equal((await st()).running, false);
    assert.equal(await btn.getAttribute('aria-pressed'), 'true');
    assert.equal(await btn.getAttribute('aria-label'), 'Resume testimonial autoplay');
    await btn.click();
    assert.equal((await st()).running, true);
    assert.equal(await btn.getAttribute('aria-label'), 'Pause testimonial autoplay');

    const before = (await st()).real;
    await page.click('#nt-testimonials-next');
    await page.waitForFunction((b) => document.getElementById('nt-testimonials-swiper').swiper.realIndex !== b, before);
    assert.equal((await st()).running, false, 'manual navigation pauses autoplay');
    await context.close();
  });

  test('testimonials autoplay actually advances on its own', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    // shrink the delay at runtime through Swiper's own API so the test stays fast
    await page.evaluate(() => {
      const s = document.getElementById('nt-testimonials-swiper').swiper;
      s.params.autoplay.delay = 1200;
      s.autoplay.stop();
      s.autoplay.start();
    });
    await page.waitForFunction(() => document.getElementById('nt-testimonials-swiper').swiper.realIndex === 1, null, { timeout: 8000 });
    await context.close();
  });

  test('prefers-reduced-motion: testimonials autoplay stopped, speed 0', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 }, { reducedMotion: 'reduce' });
    const r = await page.evaluate(() => {
      const s = document.getElementById('nt-testimonials-swiper').swiper;
      return { running: s.autoplay.running, speed: s.params.speed };
    });
    assert.deepEqual(r, { running: false, speed: 0 });
    assert.equal(await page.locator('#nt-testimonials-autoplay-toggle').getAttribute('aria-pressed'), 'true');
    await context.close();
  });
});

describe('accordions', () => {
  test('process accordion: default state from ACF, multi-expand, collapse', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const expanded = () => page.$$eval('#nt-process-accordion [data-accordion-trigger]', (t) => t.map((x) => x.getAttribute('aria-expanded')));
    assert.deepEqual(await expanded(), ['true', 'true', 'false', 'false']);
    await page.click('#nt-proc-trigger-3');
    assert.deepEqual(await expanded(), ['true', 'true', 'true', 'false']);
    assert.equal(await page.locator('#nt-proc-panel-3').isVisible(), true);
    await page.click('#nt-proc-trigger-1');
    assert.deepEqual(await expanded(), ['false', 'true', 'true', 'false']);
    assert.equal(await page.locator('#nt-proc-panel-1').isHidden(), true);
    await context.close();
  });

  test('FAQ accordion: single-expand + ArrowDown/ArrowUp/Home/End + Enter/Space toggles', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const expanded = () => page.$$eval('#nt-faq-accordion [data-accordion-trigger]', (t) => t.map((x) => x.getAttribute('aria-expanded')));
    const active = () => page.evaluate(() => document.activeElement?.id);
    assert.deepEqual(await expanded(), ['true', 'false', 'false', 'false', 'false']);
    await page.click('#nt-faq-trigger-3');
    assert.deepEqual(await expanded(), ['false', 'false', 'true', 'false', 'false']);
    await page.focus('#nt-faq-trigger-1');
    await page.keyboard.press('ArrowDown');
    assert.equal(await active(), 'nt-faq-trigger-2');
    await page.keyboard.press('End');
    assert.equal(await active(), 'nt-faq-trigger-5');
    await page.keyboard.press('ArrowDown');
    assert.equal(await active(), 'nt-faq-trigger-1');
    await page.keyboard.press('ArrowUp');
    assert.equal(await active(), 'nt-faq-trigger-5');
    await page.keyboard.press('Home');
    assert.equal(await active(), 'nt-faq-trigger-1');
    await page.keyboard.press('Enter');
    assert.equal((await expanded())[0], 'true');
    await page.keyboard.press('Space');
    assert.equal((await expanded())[0], 'false');
    // aria wiring
    const wiring = await page.$$eval('#nt-faq-accordion [data-accordion-trigger]', (ts) =>
      ts.every((t) => document.getElementById(t.getAttribute('aria-controls'))?.getAttribute('aria-labelledby') === t.id),
    );
    assert.equal(wiring, true);
    await context.close();
  });
});

describe('contact form -> backend -> Mailpit', () => {
  test('client+server validation shows errors and sends nothing', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    const before = (await (await fetch(`${MAILPIT}/api/v1/info`)).json()).Messages;
    await page.fill('#nt-your-email', 'not-an-email');
    await page.click('input[type="submit"]');
    await page.waitForSelector('form[data-status="invalid"]');
    assert.equal(await page.locator('.wpcf7-not-valid-tip').count(), 4);
    assert.match(await page.locator('#nt-your-email-error').innerText(), /valid work email/);
    assert.equal(await page.locator('#nt-your-name').getAttribute('aria-invalid'), 'true');
    assert.match(await page.locator('.wpcf7-response-output').innerText(), /One or more fields require attention/);
    const after = (await (await fetch(`${MAILPIT}/api/v1/info`)).json()).Messages;
    assert.equal(after, before);
    await context.close();
  });

  test('valid submission is really received by Mailpit', async () => {
    const { page, context, problems } = await openPage({ width: 1440, height: 900 });
    const token = `e2e-${Date.now()}`;
    await page.fill('#nt-your-name', 'Alex Morgan');
    await page.fill('#nt-your-email', 'alex.morgan@company.example');
    await page.fill('#nt-your-subject', `Astro form ${token} & <b>escape</b>`);
    await page.fill('#nt-your-message', 'Line one\nLine two with <script>alert(1)</script>');
    await page.click('input[type="submit"]');
    await page.waitForSelector('form[data-status="sent"]', { timeout: 10000 });
    assert.match(await page.locator('.wpcf7-response-output').innerText(), /delivered/);
    assert.equal(await page.inputValue('#nt-your-name'), '', 'form reset after success');

    // Verify in Mailpit itself.
    const search = await (await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(token)}`)).json();
    assert.equal(search.messages_count, 1, 'exactly one message with the token');
    const msg = await (await fetch(`${MAILPIT}/api/v1/message/${search.messages[0].ID}`)).json();
    assert.equal(msg.To[0].Address, 'architecture-desk@novatech.local');
    assert.equal(msg.ReplyTo[0].Address, 'alex.morgan@company.example');
    assert.match(msg.Subject, /^\[NovaTech Lab Inquiry\] Astro form e2e-/);
    assert.match(msg.Text, /Name: Alex Morgan/);
    assert.match(msg.Text, /Line one\nLine two/);
    assert.deepEqual(problems.foreign, []);
    await context.close();
  });

  test('backend failure is reported as an error, never as success', async () => {
    const { page, context } = await openPage({ width: 1440, height: 900 });
    await page.route(`http://127.0.0.1:${FORM_PORT}/api/contact`, (route) => route.abort('connectionrefused'));
    await page.fill('#nt-your-name', 'A');
    await page.fill('#nt-your-email', 'a@b.cd');
    await page.fill('#nt-your-subject', 'S');
    await page.fill('#nt-your-message', 'M');
    await page.click('input[type="submit"]');
    await page.waitForSelector('form[data-status="failed"]');
    assert.match(await page.locator('.wpcf7-response-output').innerText(), /could not reach the form service/);
    await context.close();
  });
});
