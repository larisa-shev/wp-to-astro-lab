/**
 * Stage 3 measurement harness (read-only: it never modifies WordPress or the Astro project).
 *
 * Compares the WordPress page (WP_URL) with the built Astro page (served from dist/ on a local
 * static server) and writes raw data to docs/artifacts/stage3/measurements.json.
 *
 * Collected per site / profile (RUNS cold-cache runs, medians reported):
 *   - resources: count and bytes by type (raw body, plus gzip/brotli computed locally)
 *   - DOM: element count, max depth, HTML size
 *   - lab metrics: TTFB, FCP, LCP, CLS (Core Web Vitals candidates) and load time, TBT approximation
 *     (non-CWV lab metrics). INP is NOT measured: it needs real user interaction.
 *   - basic accessibility checks (NOT a WCAG audit)
 */
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import http from 'node:http';
import { once } from 'node:events';
import { dirname, extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync, brotliCompressSync } from 'node:zlib';
import { chromium } from 'playwright-core';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');
const OUT = join(ROOT, '..', 'docs', 'artifacts', 'stage3');
const WP_URL = process.env.WP_URL || 'http://localhost:8001/novatech-solutions/';
const PORT = 4341;
const RUNS = Number(process.env.RUNS || 7);
const SETTLE_MS = 3000;
// COMPRESS=gzip makes the local static server gzip text assets (like a production host / the WP Apache does).
const COMPRESS = process.env.COMPRESS === 'gzip';
const OUT_FILE = COMPRESS ? 'measurements-gzip.json' : 'measurements.json';
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png' };

const PROFILES = [
  { name: 'desktop-1440', viewport: { width: 1440, height: 900 }, cpu: 1, net: null },
  // Emulated "mid-range mobile": 4x CPU slowdown + 1.6 Mbps down / 150 ms RTT (Lighthouse-like "slow 4G").
  { name: 'mobile-375-throttled', viewport: { width: 375, height: 812 }, cpu: 4, net: { latency: 150, down: (1.6 * 1024 * 1024) / 8, up: (750 * 1024) / 8 } },
];

async function serveDist() {
  const server = http.createServer(async (req, res) => {
    let p = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    if (p.endsWith('/')) p += 'index.html';
    const f = normalize(join(DIST, p));
    if (!f.startsWith(DIST) || !existsSync(f)) return res.writeHead(404).end();
    const type = MIME[extname(f)] || 'application/octet-stream';
    const buf = await readFile(f);
    if (COMPRESS && /html|css|javascript/.test(type) && /gzip/.test(req.headers['accept-encoding'] || '')) return res.writeHead(200, { 'Content-Type': type, 'Content-Encoding': 'gzip' }).end(gzipSync(buf));
    res.writeHead(200, { 'Content-Type': type }).end(buf);
  });
  server.listen(PORT, '127.0.0.1');
  await once(server, 'listening');
  return server;
}

const median = (a) => {
  const s = [...a].sort((x, y) => x - y);
  const m = s.length >> 1;
  return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
};
const round = (n, d = 1) => (n == null ? null : Math.round(n * 10 ** d) / 10 ** d);

function typeOf(url, ct = '') {
  const u = url.split('?')[0].toLowerCase();
  if (ct.includes('text/html')) return 'html';
  if (/\.css$/.test(u) || ct.includes('text/css')) return 'css';
  if (/\.m?js$/.test(u) || ct.includes('javascript')) return 'js';
  if (/\.(png|jpe?g|gif|webp|avif|svg|ico)$/.test(u) || ct.startsWith('image/')) return 'img';
  if (/\.(woff2?|ttf|otf)$/.test(u) || ct.includes('font')) return 'font';
  return 'other';
}

const INIT = () => {
  window.__m = { lcp: 0, cls: 0, longtasks: [], fcp: null };
  new PerformanceObserver((l) => l.getEntries().forEach((e) => (window.__m.lcp = e.startTime))).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver((l) => l.getEntries().forEach((e) => { if (!e.hadRecentInput) window.__m.cls += e.value; })).observe({ type: 'layout-shift', buffered: true });
  new PerformanceObserver((l) => l.getEntries().forEach((e) => window.__m.longtasks.push([e.startTime, e.duration]))).observe({ type: 'longtask', buffered: true });
  new PerformanceObserver((l) => l.getEntries().forEach((e) => { if (e.name === 'first-contentful-paint') window.__m.fcp = e.startTime; })).observe({ type: 'paint', buffered: true });
};

const DOM_AND_A11Y = () => {
  const all = [...document.querySelectorAll('*')];
  const depth = (el) => { let d = 0; while (el.parentElement) { d++; el = el.parentElement; } return d; };
  const name = (el) => (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') && el.getAttribute('aria-labelledby').split(/\s+/).map((i) => document.getElementById(i)?.textContent || '').join(' ') || el.textContent || el.getAttribute('title') || el.querySelector('img[alt]')?.getAttribute('alt') || '').trim();
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const ids = all.map((e) => e.id).filter(Boolean);
  const dupIds = ids.filter((v, i) => ids.indexOf(v) !== i);
  const hs = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')];
  let skips = 0;
  hs.reduce((prev, h) => { const l = +h.tagName[1]; if (prev && l > prev + 1) skips++; return l; }, 0);
  const ctrl = [...document.querySelectorAll('[aria-controls]')];
  const fields = [...document.querySelectorAll('input:not([type=hidden]):not([type=submit]),textarea,select')].filter(visible);
  const labelled = (f) => !!(f.getAttribute('aria-label') || f.getAttribute('aria-labelledby') || (f.id && document.querySelector(`label[for="${CSS.escape(f.id)}"]`)) || f.closest('label'));
  return {
    dom: {
      elements: all.length,
      maxDepth: Math.max(...all.map(depth)),
      htmlBytesDom: new TextEncoder().encode(document.documentElement.outerHTML).length,
      scriptTags: document.scripts.length,
      inlineScriptTags: [...document.scripts].filter((s) => !s.src).length,
      styleTags: document.querySelectorAll('style').length,
      linkedStylesheets: document.querySelectorAll('link[rel=stylesheet]').length,
    },
    a11y: {
      htmlLang: document.documentElement.lang || null,
      title: document.title || null,
      metaViewport: !!document.querySelector('meta[name=viewport]'),
      h1Count: document.querySelectorAll('h1').length,
      headingLevelSkips: skips,
      landmarks: { header: document.querySelectorAll('header,[role=banner]').length, nav: document.querySelectorAll('nav,[role=navigation]').length, main: document.querySelectorAll('main,[role=main]').length, footer: document.querySelectorAll('footer,[role=contentinfo]').length },
      skipLink: !!document.querySelector('a[href="#main-content"],a[href="#content"],a.skip-link,a.screen-reader-shortcut'),
      imagesTotal: document.images.length,
      imagesMissingAlt: [...document.images].filter((i) => !i.hasAttribute('alt')).length,
      imagesEmptyAlt: [...document.images].filter((i) => i.getAttribute('alt') === '').length,
      buttonsWithoutName: [...document.querySelectorAll('button,[role=button]')].filter((b) => !name(b)).length,
      linksWithoutName: [...document.querySelectorAll('a[href]')].filter((a) => !name(a)).length,
      visibleFormFields: fields.length,
      formFieldsWithoutLabel: fields.filter((f) => !labelled(f)).length,
      duplicateIds: [...new Set(dupIds)].length,
      ariaControlsBroken: ctrl.filter((c) => c.getAttribute('aria-controls').split(/\s+/).some((i) => !document.getElementById(i))).length,
      ariaExpandedTriggers: document.querySelectorAll('[aria-expanded]').length,
      positiveTabindex: document.querySelectorAll('[tabindex]:not([tabindex="0"]):not([tabindex="-1"])').length,
      liveRegions: document.querySelectorAll('[aria-live],[role=status],[role=alert]').length,
    },
  };
};

async function runOnce(browser, url, profile) {
  const ctx = await browser.newContext({ viewport: profile.viewport, locale: 'en-US' });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  if (profile.cpu > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: profile.cpu });
  await cdp.send('Network.enable');
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
  if (profile.net) await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: profile.net.latency, downloadThroughput: profile.net.down, uploadThroughput: profile.net.up });
  await page.addInitScript(INIT);
  const resources = [];
  const pending = [];
  page.on('response', (r) => pending.push((async () => {
    try {
      const body = await r.body();
      const ct = r.headers()['content-type'] || '';
      resources.push({ url: r.url(), status: r.status(), type: typeOf(r.url(), ct), raw: body.length, gzip: ['html', 'css', 'js'].includes(typeOf(r.url(), ct)) ? gzipSync(body).length : body.length, br: ['html', 'css', 'js'].includes(typeOf(r.url(), ct)) ? brotliCompressSync(body).length : body.length, encoding: r.headers()['content-encoding'] || null });
    } catch { /* redirects etc. have no body */ }
  })()));
  const failed = [];
  page.on('requestfailed', (r) => failed.push(r.url()));
  await page.goto(url, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(SETTLE_MS);
  await Promise.all(pending);
  const m = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0];
    return { ...window.__m, ttfb: nav.responseStart, domContentLoaded: nav.domContentLoadedEventEnd, load: nav.loadEventEnd };
  });
  const fcp = m.fcp;
  const tbt = m.longtasks.filter(([s]) => s >= (fcp ?? 0) && s <= m.load + SETTLE_MS).reduce((a, [, d]) => a + Math.max(0, d - 50), 0);
  const extra = await page.evaluate(DOM_AND_A11Y);
  await ctx.close();
  return { metrics: { ttfb: m.ttfb, fcp, lcp: m.lcp, cls: m.cls, tbtApprox: tbt, domContentLoaded: m.domContentLoaded, load: m.load, longTaskCount: m.longtasks.length }, resources, failed, ...extra };
}

function summarize(runs) {
  const metrics = {};
  for (const k of Object.keys(runs[0].metrics)) {
    const v = runs.map((r) => r.metrics[k]).filter((x) => x != null);
    metrics[k] = { median: round(median(v), k === 'cls' ? 4 : 1), min: round(Math.min(...v), k === 'cls' ? 4 : 1), max: round(Math.max(...v), k === 'cls' ? 4 : 1) };
  }
  // Resource sizes are deterministic; take them from the last run.
  const res = runs.at(-1).resources.filter((r) => r.status < 400);
  const byType = {};
  for (const r of res) {
    const t = (byType[r.type] ||= { count: 0, raw: 0, gzip: 0, br: 0 });
    t.count++; t.raw += r.raw; t.gzip += r.gzip; t.br += r.br;
  }
  const total = Object.values(byType).reduce((a, t) => ({ count: a.count + t.count, raw: a.raw + t.raw, gzip: a.gzip + t.gzip, br: a.br + t.br }), { count: 0, raw: 0, gzip: 0, br: 0 });
  const last = runs.at(-1);
  return {
    runs: runs.length,
    metrics,
    resources: { byType, total, list: res.map(({ url, type, raw, gzip, br, encoding }) => ({ url, type, raw, gzip, br, encoding })), failedRequests: last.failed, status4xx5xx: runs.at(-1).resources.filter((r) => r.status >= 400).map((r) => `${r.status} ${r.url}`) },
    dom: last.dom,
    a11y: last.a11y,
  };
}

const server = await serveDist();
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const sites = { wordpress: WP_URL, astro: `http://127.0.0.1:${PORT}/` };
const result = { compress: COMPRESS ? 'gzip (static server)' : 'none (static server)', generatedAt: new Date().toISOString(), runsPerCell: RUNS, browser: `Chrome ${browser.version()}`, node: process.version, profiles: PROFILES, urls: sites, results: {} };
try {
  // Warm-up (server-side caches, JIT) – discarded.
  for (const url of Object.values(sites)) await runOnce(browser, url, PROFILES[0]);
  for (const profile of PROFILES) {
    result.results[profile.name] = {};
    // Interleave sites to spread machine noise evenly.
    const runs = { wordpress: [], astro: [] };
    for (let i = 0; i < RUNS; i++) for (const [site, url] of Object.entries(sites)) runs[site].push(await runOnce(browser, url, profile));
    for (const site of Object.keys(sites)) result.results[profile.name][site] = summarize(runs[site]);
    console.log('done', profile.name);
  }
} finally {
  await browser.close();
  server.close();
}
await mkdir(OUT, { recursive: true });
await writeFile(join(OUT, OUT_FILE), JSON.stringify(result, null, 2));
console.log('written', join(OUT, OUT_FILE));
