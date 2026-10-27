/**
 * Visual + structural comparison of the WordPress baseline and the Astro build.
 *
 * Needs: WordPress at WP_URL (default http://localhost:8001/novatech-solutions/) and a built `dist/`
 * (served here). Writes screenshots + diff images to `tests/artifacts/` (git-ignored) and the
 * metrics to `tests/artifacts/compare-report.json`.
 *
 * For each viewport (375 / 768 / 1440):
 *   - full-page screenshots of both sites, animations frozen, Swiper reset to slide 0, autoplay stopped
 *   - pixel diff (pixelmatch, threshold 0.1) overall and per section
 *   - per-section height / width, computed styles of key elements, visible text comparison
 */
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import http from 'node:http';
import { once } from 'node:events';
import { dirname, extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');
const OUT = join(ROOT, 'tests', 'artifacts');
const WP_URL = process.env.WP_URL || 'http://localhost:8001/novatech-solutions/';
const PORT = 4331;
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png' };
const VIEWPORTS = [
  { name: 'mobile-375', width: 375, height: 812 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'desktop-1440', width: 1440, height: 900 },
];
const SECTIONS = ['hero', 'services', 'cases', 'testimonials', 'process', 'faq', 'contact'];
const STYLE_PROPS = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'color', 'backgroundColor', 'paddingTop', 'paddingLeft', 'borderRadius', 'letterSpacing'];
const SAMPLES = ['body', '.nt-topbar', '.nt-header', '.nt-hero__title', '.nt-hero__subtitle', '.nt-btn--primary', '.nt-stat-card', '.nt-section__title', '.nt-service-card', '.nt-case-card', '.nt-testimonial-card', '.nt-accordion__trigger', '.nt-footer', '.nt-form-field input'];

async function serveDist() {
  const server = http.createServer(async (req, res) => {
    let p = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    if (p.endsWith('/')) p += 'index.html';
    const f = normalize(join(DIST, p));
    if (!f.startsWith(DIST) || !existsSync(f)) return res.writeHead(404).end();
    res.writeHead(200, { 'Content-Type': MIME[extname(f)] || 'application/octet-stream' }).end(await readFile(f));
  });
  server.listen(PORT, '127.0.0.1');
  await once(server, 'listening');
  return server;
}

/** Freeze everything that moves so both screenshots are comparable. */
async function prepare(page) {
  await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
  await page.$$eval('img[loading="lazy"]', (i) => i.forEach((x) => (x.loading = 'eager')));
  await page.waitForFunction(() => [...document.images].every((i) => i.complete), null, { timeout: 15000 });
  await page.evaluate(() => {
    for (const id of ['nt-cases-swiper', 'nt-testimonials-swiper']) {
      const s = document.getElementById(id)?.swiper;
      if (!s) continue;
      s.autoplay?.stop();
      if (s.params.loop) s.slideToLoop(0, 0);
      else s.slideTo(0, 0);
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(300);
}

async function measure(page) {
  return page.evaluate(
    ({ SECTIONS, SAMPLES, STYLE_PROPS }) => {
      const r = (el) => {
        const b = el.getBoundingClientRect();
        return { w: Math.round(b.width * 10) / 10, h: Math.round(b.height * 10) / 10, top: Math.round((b.top + scrollY) * 10) / 10 };
      };
      const sections = Object.fromEntries(SECTIONS.map((id) => [id, r(document.getElementById(id))]));
      const styles = {};
      for (const sel of SAMPLES) {
        const el = document.querySelector(sel);
        if (!el) continue;
        const cs = getComputedStyle(el);
        styles[sel] = Object.fromEntries(STYLE_PROPS.map((p) => [p, cs[p]]));
        styles[sel].box = r(el);
      }
      const text = (id) => document.getElementById(id).innerText.replace(/\s+/g, ' ').trim();
      return {
        docHeight: document.documentElement.scrollHeight,
        docWidth: document.documentElement.scrollWidth,
        header: r(document.querySelector('.nt-header')),
        footer: r(document.querySelector('.nt-footer')),
        sections,
        styles,
        images: [...document.images].map((i) => ({ w: Math.round(i.getBoundingClientRect().width), h: Math.round(i.getBoundingClientRect().height), fit: getComputedStyle(i).objectFit })),
        texts: { ...Object.fromEntries(SECTIONS.map((id) => [id, text(id)])), footer: document.querySelector('.nt-footer').innerText.replace(/\s+/g, ' ').trim(), header: document.querySelector('.nt-header').innerText.replace(/\s+/g, ' ').trim(), topbar: document.querySelector('.nt-topbar').innerText.replace(/\s+/g, ' ').trim() },
      };
    },
    { SECTIONS, SAMPLES, STYLE_PROPS },
  );
}

function diffPng(aBuf, bBuf, outPath) {
  const a = PNG.sync.read(aBuf);
  const b = PNG.sync.read(bBuf);
  const w = Math.max(a.width, b.width);
  const h = Math.max(a.height, b.height);
  const pad = (img) => {
    const o = new PNG({ width: w, height: h });
    o.data.fill(255);
    PNG.bitblt(img, o, 0, 0, img.width, img.height, 0, 0);
    return o;
  };
  const A = pad(a);
  const B = pad(b);
  const D = new PNG({ width: w, height: h });
  const diff = pixelmatch(A.data, B.data, D.data, w, h, { threshold: 0.1 });
  return { diff, total: w * h, pct: Math.round((diff / (w * h)) * 10000) / 100, png: PNG.sync.write(D), outPath, sizeA: [a.width, a.height], sizeB: [b.width, b.height] };
}

async function main() {
  await mkdir(OUT, { recursive: true });
  const server = await serveDist();
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const report = { generatedAt: new Date().toISOString(), viewports: {} };

  try {
    for (const vp of VIEWPORTS) {
      const shots = {};
      const data = {};
      for (const [label, url] of [['wp', WP_URL], ['astro', `http://127.0.0.1:${PORT}/`]]) {
        const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(url, { waitUntil: 'networkidle' });
        await prepare(page);
        data[label] = await measure(page);
        shots[label] = await page.screenshot({ fullPage: true });
        await writeFile(join(OUT, `${vp.name}-${label}.png`), shots[label]);
        // per-section screenshots
        for (const id of SECTIONS) {
          shots[`${label}:${id}`] = await page.locator(`#${id}`).screenshot();
        }
        await context.close();
      }

      const full = diffPng(shots.wp, shots.astro);
      await writeFile(join(OUT, `${vp.name}-diff.png`), full.png);
      const perSection = {};
      for (const id of SECTIONS) {
        const d = diffPng(shots[`wp:${id}`], shots[`astro:${id}`]);
        perSection[id] = { diffPixelsPct: d.pct, wpSize: d.sizeA, astroSize: d.sizeB };
      }

      const styleDiffs = [];
      for (const [sel, wpStyles] of Object.entries(data.wp.styles)) {
        const as = data.astro.styles[sel];
        if (!as) {
          styleDiffs.push({ sel, issue: 'missing in astro' });
          continue;
        }
        for (const [k, v] of Object.entries(wpStyles)) {
          if (k === 'box') continue;
          if (as[k] !== v) styleDiffs.push({ sel, prop: k, wp: v, astro: as[k] });
        }
      }
      const textDiffs = Object.keys(data.wp.texts).filter((k) => data.wp.texts[k] !== data.astro.texts[k]);
      const sectionHeightDeltas = Object.fromEntries(SECTIONS.map((id) => [id, Math.round((data.astro.sections[id].h - data.wp.sections[id].h) * 10) / 10]));
      const imageDiffs = data.wp.images.map((w, i) => ({ i, wp: w, astro: data.astro.images[i] })).filter((x) => JSON.stringify(x.wp) !== JSON.stringify(x.astro));

      report.viewports[vp.name] = {
        fullPage: { wpSize: full.sizeA, astroSize: full.sizeB, diffPixelsPct: full.pct },
        docHeight: { wp: data.wp.docHeight, astro: data.astro.docHeight },
        sectionHeightDeltas,
        perSection,
        textSectionsDiffering: textDiffs,
        styleDiffs,
        imageDiffs,
        imageCount: { wp: data.wp.images.length, astro: data.astro.images.length },
      };
      console.log(`[${vp.name}] full-page diff ${full.pct}% | doc height wp=${data.wp.docHeight} astro=${data.astro.docHeight} | text diffs: ${textDiffs.join(',') || 'none'} | style diffs: ${styleDiffs.length} | image diffs: ${imageDiffs.length}`);
    }
  } finally {
    await browser.close();
    server.close();
  }
  await writeFile(join(OUT, 'compare-report.json'), JSON.stringify(report, null, 2));
  console.log(`Report: ${join(OUT, 'compare-report.json')}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
