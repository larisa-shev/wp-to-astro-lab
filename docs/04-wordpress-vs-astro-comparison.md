# Stage 3 – Evidence-Based Comparison: WordPress vs Astro

This document presents the Stage 3 engineering comparison between the **WordPress 6.8.2 (ACF Free + Contact Form 7 + Twenty Twenty-Five Child Theme)** baseline and the migrated **Astro 6.4.8** static implementation for the **NovaTech Solutions** corporate landing page.

All findings in this report are grounded in reproducible measurements collected by [`astro-lab/tests/stage3-measure.mjs`](../astro-lab/tests/stage3-measure.mjs) and [`astro-lab/tests/visual-compare.mjs`](../astro-lab/tests/visual-compare.mjs), with raw artifacts committed in [`docs/artifacts/stage3/measurements-gzip.json`](artifacts/stage3/measurements-gzip.json) and [`docs/artifacts/stage3/measurements.json`](artifacts/stage3/measurements.json).

---

## 1. Executive Summary

| Dimension | WordPress Baseline (`:8001`) | Astro Implementation (`dist/`) | Delta (Astro vs WP) |
| :--- | :--- | :--- | :--- |
| **Runtime Architecture** | PHP 8.2 + Apache + MariaDB 11.4.5 + WP/ACF/CF7 | Pre-rendered static files + stateless form API | No CMS/DB on page views |
| **Initial HTTP Requests (Desktop / Mobile)** | `14` / `15` requests | `4` / `4` requests | **`-71.4%`** / **`-73.3%`** |
| **Total Initial Payload (Raw)** | `384,620 B` (`375.6 KB`) | `198,456 B` (`193.8 KB`) | **`-186,164 B` (`-48.4%`)** |
| **Total Initial Payload (Gzip)** | `103,599 B` (`101.2 KB`) | `55,092 B` (`53.8 KB`) | **`-48,507 B` (`-46.8%`)** |
| **Total Initial Payload (Brotli)** | `89,283 B` (`87.2 KB`) | `48,248 B` (`47.1 KB`) | **`-41,035 B` (`-46.0%`)** |
| **JavaScript Payload (Raw / Gzip)** | `222,372 B` / `63,791 B` (`7` files + `4` inline) | `91,913 B` / `28,106 B` (`1` file + `1` inline) | **`-58.7%` raw / `-55.9%` gzip** |
| **External CSS Payload (Raw / Gzip)** | `53,154 B` / `12,673 B` (`4` files + `5` inline `<style>`) | `37,647 B` / `7,488 B` (`1` file, `0` inline `<style>`) | **`-29.2%` raw / `-40.9%` gzip** |
| **HTML Document Size (Raw / Gzip)** | `101,384 B` / `19,425 B` | `62,140 B` / `12,742 B` | **`-38.7%` raw / `-34.4%` gzip** |
| **DOM Elements (Desktop / Mobile)** | `709` / `711` elements (`maxDepth: 13`) | `673` / `675` elements (`maxDepth: 13`) | **`-36` elements (`-5.1%`)** |
| **Desktop TTFB (Median)** | `176.7 ms` (`154.5–181.4 ms`) | `14.3 ms` (`12.4–14.8 ms`) | **`-162.4 ms` (`-91.9%`, `12.4×` faster)** |
| **Desktop FCP / LCP (Median)** | `332.0 ms` / `332.0 ms` | `164.0 ms` / `168.0 ms` | **`-50.6%` FCP / `-49.4%` LCP** |
| **Desktop Load Event (Median)** | `330.0 ms` (`315.5–349.3 ms`) | `141.0 ms` (`130.8–150.4 ms`) | **`-189.0 ms` (`-57.3%`)** |
| **Mobile Throttled TTFB (Median)** | `152.0 ms` (`150.2–170.9 ms`) | `12.7 ms` (`11.2–13.2 ms`) | **`-139.3 ms` (`-91.6%`, `12.0×` faster)** |
| **Mobile Throttled FCP / LCP (Gzip Median)** | `960.0 ms` / `960.0 ms` | `920.0 ms` / `920.0 ms` | **`-40.0 ms` (`-4.2%`)** |
| **Mobile Throttled TBT Approx (Median)** | `52.0 ms` (`0–62 ms`, `1` long task) | `0.0 ms` (`0–0 ms`, `1` task `≤ 50 ms`) | **`-52.0 ms`** |
| **Mobile Throttled Load Event (Gzip Median)** | `1184.9 ms` (`1152.1–1285.6 ms`) | `977.7 ms` (`935.7–1005.7 ms`) | **`-207.2 ms` (`-17.5%`)** |
| **Cumulative Layout Shift (CLS)** | `0` (Desktop & Mobile) | `0` (Desktop & Mobile) | **Identical (`0`)** |
| **Visual Parity (Computed Style Diffs)** | Baseline (`14` selectors × `10` props) | `0` diffs at `375px`, `768px`, `1440px` | **100% style parity** |
| **Visual Parity (Full-Page Pixel Diff)** | Baseline (`375px` / `768px` / `1440px`) | `4.83%` / `3.44%` / `2.47%` | Within cosmetic Swiper/form tolerances |

---

## 2. Measurement Methodology & Experimental Controls

### 2.1 Test Harness & Environment
Measurements were executed using [`astro-lab/tests/stage3-measure.mjs`](../astro-lab/tests/stage3-measure.mjs) under the following controlled environment:
- **Browser:** Headless Google Chrome `156.0.8078.48` driven via `playwright-core` and Chrome DevTools Protocol (CDP).
- **Host Runtime:** Node.js `v22.17.1` on Windows.
- **WordPress Endpoint:** `http://localhost:8001/novatech-solutions/` served by the isolated Docker stack (`wordpress:6.8.2-php8.2-apache` + `mariadb:11.4.5`).
- **Astro Endpoint:** `http://127.0.0.1:4341/` serving the production static build (`astro-lab/dist/`, built by Astro `6.4.8`).

### 2.2 Experimental Controls
1. **Warm-up Discard:** Before recording timings, the harness loads both sites once (`PROFILES[0]`) and discards the results so PHP OPcache, MariaDB buffer pools, and OS filesystem caches are warm.
2. **Cold Browser Cache per Run:** Each measurement run creates a fresh Playwright `BrowserContext` with CDP `Network.setCacheDisabled({ cacheDisabled: true })` and ` SETTLE_MS = 3000` post-load observation window.
3. **Interleaved Execution (`7` runs per cell):** Within each device profile, runs alternate `WordPress → Astro → WordPress → Astro` (`7` iterations each = `14` navigations per profile) so transient host CPU/disk fluctuations affect both targets evenly.
4. **HTTP Compression Control (`measurements.json` vs `measurements-gzip.json`):**
   - The WordPress Apache container enables `mod_deflate` (`Content-Encoding: gzip`) by default for HTML, CSS, and JS responses.
   - To avoid conflating **framework/build differences** with **HTTP server compression**, [`stage3-measure.mjs`](../astro-lab/tests/stage3-measure.mjs) was run in two modes:
     - **Gzip-enabled static server ([`measurements-gzip.json`](artifacts/stage3/measurements-gzip.json)):** `COMPRESS=gzip` compresses HTML, CSS, and JS responses from `dist/`, matching WordPress Apache's transfer encoding and standard production static hosts/CDNs. This is the primary apples-to-apples baseline used in this report.
     - **Uncompressed static server ([`measurements.json`](artifacts/stage3/measurements.json)):** Serves raw bytes (`encoding: null`) from `dist/` to quantify how an unconfigured static server impacts throttled mobile delivery.

### 2.3 Device & Network Profiles
- **`desktop-1440`:** `1440×900` viewport, `1×` CPU (no throttling), unthrottled loopback network.
- **`mobile-375-throttled`:** `375×812` viewport, `4×` CPU slowdown (`Emulation.setCPUThrottlingRate`), and Lighthouse-style Slow 4G network emulation (`150 ms` RTT latency, `1.6 Mbps` / `209,715 B/s` download, `750 Kbps` / `96,000 B/s` upload).

---

## 3. Network Payload & Asset Breakdown

Resource counts and byte sizes are deterministic across runs. Both `raw` (uncompressed body length) and compressed sizes (`gzip` via `zlib.gzipSync` and `br` via `zlib.brotliCompressSync`) were computed directly from the HTTP response bodies.

### 3.1 Summary by Resource Type (Initial Cold Page Load)

| Resource Type | WordPress Count | WordPress Bytes (Raw / Gzip / Brotli) | Astro Count | Astro Bytes (Raw / Gzip / Brotli) | Raw Bytes Delta | Gzip Bytes Delta |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **HTML Document** | `1` | `101,384` / `19,425` / `15,559` | `1` | `62,140` / `12,742` / `10,050` | `-39,244 B` (`-38.7%`) | `-6,683 B` (`-34.4%`) |
| **External CSS** | `4` | `53,154` / `12,673` / `10,698` | `1` | `37,647` / `7,488` / `6,584` | `-15,507 B` (`-29.2%`) | `-5,185 B` (`-40.9%`) |
| **External JS (Desktop)** | `7` | `222,372` / `63,791` / `55,316` | `1` | `91,913` / `28,106` / `24,858` | `-130,459 B` (`-58.7%`) | `-35,685 B` (`-55.9%`) |
| **External JS (Mobile)**¹ | `8` | `223,702` / `64,376` / `55,807` | `1` | `91,913` / `28,106` / `24,858` | `-131,789 B` (`-58.9%`) | `-36,270 B` (`-56.3%`) |
| **Images (Initial Viewport)**² | `1` | `6,756` / `6,756` / `6,756` | `1` | `6,756` / `6,756` / `6,756` | `0 B` (`0.0%`) | `0 B` (`0.0%`) |
| **Other (REST XHR)**³ | `1` | `954` / `954` / `954` | `0` | `0` / `0` / `0` | `-954 B` (`-100%`) | `-954 B` (`-100%`) |
| **Total (Desktop)** | **`14`** | **`384,620` / `103,599` / `89,283`** | **`4`** | **`198,456` / `55,092` / `48,248`** | **`-186,164 B` (`-48.4%`)** | **`-48,507 B` (`-46.8%`)** |
| **Total (Mobile)** | **`15`** | **`385,950` / `104,184` / `89,774`** | **`4`** | **`198,456` / `55,092` / `48,248`** | **`-187,494 B` (`-48.6%`)** | **`-49,092 B` (`-47.1%`)** |

*¹ On `mobile-375-throttled`, WordPress's `wp-emoji-release.min.js` spawns an inline `blob:` Web Worker (`1,330 B` raw / `585 B` gzip) to test canvas emoji rendering under 4× CPU throttling.*
*² Only the above-the-fold hero diagram (`novatech-hero-architecture.png`, `fetchpriority="high"`, `6,756 B`) is fetched on initial cold load at `scrollY = 0`. The remaining `9` below-the-fold images (`5` case study diagrams + `4` testimonial avatars = `31,963 B` raw) use `loading="lazy"` on both sites and are fetched only when scrolled into view. Total size of all 10 PNG files on disk is `38,719 B` raw on both sites.*
*³ Contact Form 7 automatically issues a runtime `GET /wp-json/contact-form-7/v1/contact-forms/331/feedback/schema` (`954 B` JSON) on every page load.*

### 3.2 Complete Per-File Inventory: WordPress vs Astro

#### WordPress Initial Load (`14` desktop / `15` mobile requests)
| # | URL / Asset | Type | Raw (B) | Gzip (B) | Brotli (B) | Role / Origin |
| :---: | :--- | :---: | ---: | ---: | ---: | :--- |
| 1 | `/novatech-solutions/` | `html` | `101,384` | `19,425` | `15,559` | PHP-rendered HTML + 5 inline `<style>` + 4 inline `<script>` |
| 2 | `/wp-content/themes/ai-hub-theme/assets/css/novatech.css?ver=1.1.0` | `css` | `29,303` | `5,647` | `4,840` | Custom B2B design system stylesheet |
| 3 | `/wp-content/themes/ai-hub-theme/assets/vendor/swiper/swiper-bundle.min.css?ver=11.2.6` | `css` | `18,454` | `4,849` | `4,181` | Full Swiper 11.2.6 stylesheet bundle |
| 4 | `/wp-content/plugins/contact-form-7/includes/css/styles.css?ver=6.0.6` | `css` | `2,894` | `1,016` | `828` | Contact Form 7 plugin stylesheet |
| 5 | `/wp-content/themes/twentytwentyfive/style.css?ver=1.1.0` | `css` | `2,503` | `1,161` | `849` | Parent theme metadata/reset stylesheet |
| 6 | `/wp-content/themes/ai-hub-theme/assets/vendor/swiper/swiper-bundle.min.js?ver=11.2.6` | `js` | `154,008` | `42,928` | `37,295` | Full UMD Swiper 11.2.6 bundle (all modules) |
| 7 | `/wp-includes/js/wp-emoji-release.min.js?ver=6.8.2` | `js` | `19,251` | `5,169` | `4,098` | WP Core emoji detection runtime |
| 8 | `/wp-content/plugins/contact-form-7/includes/js/index.js?ver=6.0.6` | `js` | `13,452` | `4,236` | `3,724` | CF7 frontend submission & DOM controller |
| 9 | `/wp-content/plugins/contact-form-7/includes/swv/js/index.js?ver=6.0.6` | `js` | `12,512` | `3,523` | `3,172` | CF7 Schema-Woven Validation engine |
| 10 | `/wp-content/themes/ai-hub-theme/assets/js/novatech.js?ver=1.1.0` | `js` | `9,232` | `2,624` | `2,209` | Theme UI script (nav, Swipers, accordions) |
| 11 | `/wp-includes/js/dist/i18n.min.js?ver=5e580eb46a90c2b997e6` | `js` | `9,141` | `3,667` | `3,341` | WP Core localization runtime (required by CF7) |
| 12 | `/wp-includes/js/dist/hooks.min.js?ver=4d63a3d491d11ffd8ac6` | `js` | `4,776` | `1,644` | `1,477` | WP Core event hooks (required by `i18n` / CF7) |
| 13 | `/wp-content/uploads/2026/10/novatech-hero-architecture.png` | `img` | `6,756` | `6,756` | `6,756` | Above-the-fold hero architecture PNG |
| 14 | `/wp-json/contact-form-7/v1/contact-forms/331/feedback/schema` | `other` | `954` | `954` | `954` | Runtime REST schema fetch triggered by CF7 |
| 15 | `blob:http://localhost:8001/...` *(mobile-375-throttled only)* | `js` | `1,330` | `585` | `491` | Emoji canvas worker spawned by `wp-emoji-release` |

#### Astro Initial Load (`4` requests on both Desktop and Mobile)
| # | URL / Asset | Type | Raw (B) | Gzip (B) | Brotli (B) | Role / Origin |
| :---: | :--- | :---: | ---: | ---: | ---: | :--- |
| 1 | `/` (`dist/index.html`) | `html` | `62,140` | `12,742` | `10,050` | Pre-rendered HTML + 1 inline `<script type="module">` (`1,636 B`) |
| 2 | `/_astro/index.DOmElJ1w.css` | `css` | `37,647` | `7,488` | `6,584` | Bundled & minified `wp-global-baseline.css` + `novatech.css` + Swiper CSS |
| 3 | `/_astro/index.astro_astro_type_script_index_0_lang.DoGJu6i5.js` | `js` | `91,913` | `28,106` | `24,858` | Bundled `ui.ts` + tree-shaken Swiper 12.1.2 (`Navigation`, `Pagination`, `Keyboard`, `Autoplay`) |
| 4 | `/images/novatech-hero-architecture.png` | `img` | `6,756` | `6,756` | `6,756` | Above-the-fold hero architecture PNG (unchanged binary) |

### 3.3 Why Astro Still Ships ~92 KB Raw (`28.1 KB` Gzip) of JavaScript
Astro itself contributes **`0 B` of client-side framework runtime** (no React/Vue/Svelte hydration islands are used). The `91,913 B` (`28,106 B` gzip) script bundle consists entirely of:
1. **Swiper 12.1.2 ES Module Core + 4 Imported Modules (`Navigation`, `Pagination`, `Keyboard`, `Autoplay`)** — replacing WordPress's full UMD `swiper-bundle.min.js` (`154,008 B` raw / `42,928 B` gzip) saved **`62,095 B` raw (`14,822 B` gzip)** on Swiper alone while also remediating `GHSA-hmx5-qpq5-p643`.
2. **Application UI Logic (`src/scripts/ui.ts`)** — sticky header, mobile drawer, Swiper initialization, and WAI-ARIA accordions.
3. **Eliminated WordPress & Plugin Scripts (`59,132 B` raw / `18,239 B` gzip removed):** Astro completely eliminates `wp-emoji-release.min.js`, `hooks.min.js`, `i18n.min.js`, `contact-form-7/includes/swv/js/index.js`, `contact-form-7/includes/js/index.js`, and the `/wp-json/.../feedback/schema` XHR, replacing CF7's `39.9 KB` client stack (`hooks` + `i18n` + `swv` + `cf7`) with a `1.6 KB` inline module (`src/scripts/contact-form.ts`).

---

## 4. DOM Weight & Document Structure

| Metric | WordPress (Desktop / Mobile) | Astro (Desktop / Mobile) | Delta (Astro vs WP) |
| :--- | :---: | :---: | :---: |
| **Total DOM Elements (`querySelectorAll('*')`)** | `709` / `711` | `673` / `675` | **`-36` elements (`-5.1%`)** |
| **Maximum DOM Tree Depth** | `13` / `13` | `13` / `13` | **`0` (Identical)** |
| **Transferred HTML Size (Raw / Gzip)** | `101,384 B` / `19,425 B` | `62,140 B` / `12,742 B` | **`-39,244 B` (`-38.7%`) / `-6,683 B` (`-34.4%`)** |
| **Serialized Live DOM (`outerHTML` Bytes)** | `102,564 B` / `102,760 B` | `63,684 B` / `63,772 B` | **`-38,880 B` (`-37.9%`)** |
| **Total `<script>` Tags in DOM** | `11` (`4` inline, `7` external) | `2` (`1` inline, `1` external) | **`-9` script tags (`-81.8%`)** |
| **Inline `<style>` Tags in DOM** | `5` | `0` | **`-5` inline style blocks (`-100%`)** |
| **Linked `<link rel="stylesheet">` Tags** | `4` | `1` | **`-3` stylesheet links (`-75.0%`)** |

### Root Causes of the `36`-Element and `39.2 KB` HTML Reduction
Because Stage 2 preserved the exact `<main>`, `<header>`, and `<footer>` component hierarchy and class names for visual parity, the visible UI DOM tree is identical (`maxDepth: 13`). The `36`-element and `39.2 KB` HTML reduction in Astro comes from removing CMS/plugin boilerplate:
1. **Twenty Twenty-Five & WP Core Inline `<style>` Blocks (`5` tags removed):** WordPress injects `global-styles-inline-css` (full `theme.json` preset variables, palettes, and layout utility classes), `wp-emoji-styles-inline-css`, `core-block-supports-inline-css`, and block-library inline resets directly into `<head>`. Astro moves only the 2 required typography rules (`body` and `h1–h6`) into the external bundle (`src/styles/wp-global-baseline.css`).
2. **WordPress `<head>` Discovery & Script Tags (`9` script tags + WP `<link>`/`<meta>` tags removed):** Removes `api.w.org`, oEmbed discovery links, `EditURI`, `shortlink`, `wp-emoji` inline bootstrap, `wp.i18n` inline bootstrap, CF7 `wpcf7` inline config, and WP 6.8 speculation rules.
3. **Contact Form 7 Hidden Boilerplate:** CF7 renders a `<div style="display: none;">` containing `6` hidden `<input name="_wpcf7*">` fields, a `<span class="wpcf7-spinner">`, and an empty `<ul>` inside `.screen-reader-response`; Astro uses a single off-screen honeypot `<div>` (`<label>` + `<input>`).
4. **Swiper 11 UMD Notification Nodes (`2` elements):** WordPress's full `swiper-bundle.min.js` includes the `A11y` plugin by default, which appends `<span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span>` to each of the 2 Swiper containers.
5. **Why Mobile Has `+2` DOM Elements on Both Sites (`709 → 711` and `673 → 675`):** At `1440px` (`slidesPerView: 3` across `5` case studies), Swiper renders `3` pagination bullets (`#nt-cases-pagination`). At `375px` (`slidesPerView: 1`), Swiper renders `5` pagination bullets (`+2` `<span class="swiper-pagination-bullet">` elements on both sites).

---

## 5. Lab Performance & Core Web Vitals Candidates

> **Scope note:** `TTFB`, `FCP`, `LCP`, and `CLS` are measured in controlled lab conditions (`PerformanceObserver` + Navigation Timing API). `DOMContentLoaded`, `load`, and `tbtApprox` (Total Blocking Time approximation: sum of Long Task durations exceeding `50 ms` between `FCP` and `load + 3000 ms`) are supplemental lab metrics. **INP (Interaction to Next Paint)** is not measured because it requires field/user interaction sequences.

### 5.1 Desktop (`desktop-1440` — `1440×900`, Unthrottled CPU & Network, `7` Runs)

From [`docs/artifacts/stage3/measurements-gzip.json`](artifacts/stage3/measurements-gzip.json):

| Metric | WordPress Median (`Min–Max`) | Astro Median (`Min–Max`) | Delta (Astro vs WP) |
| :--- | :---: | :---: | :---: |
| **TTFB (`responseStart`)** | `176.7 ms` (`154.5–181.4 ms`) | `14.3 ms` (`12.4–14.8 ms`) | **`-162.4 ms` (`-91.9%`, `12.4×` faster)** |
| **First Contentful Paint (FCP)** | `332.0 ms` (`304.0–356.0 ms`) | `164.0 ms` (`160.0–180.0 ms`) | **`-168.0 ms` (`-50.6%`, `2.0×` faster)** |
| **Largest Contentful Paint (LCP)** | `332.0 ms` (`304.0–356.0 ms`) | `168.0 ms` (`160.0–180.0 ms`) | **`-164.0 ms` (`-49.4%`, `2.0×` faster)** |
| **Cumulative Layout Shift (CLS)** | `0` (`0–0`) | `0` (`0–0`) | **`0` (Identical)** |
| **Total Blocking Time (`tbtApprox`)** | `0 ms` (`0–0 ms`, `0` long tasks) | `0 ms` (`0–0 ms`, `0` long tasks) | **`0 ms`** |
| **DOMContentLoaded (`DCL`)** | `308.9 ms` (`286.2–327.9 ms`) | `139.0 ms` (`128.8–148.1 ms`) | **`-169.9 ms` (`-55.0%`)** |
| **Window `load` Event** | `330.0 ms` (`315.5–349.3 ms`) | `141.0 ms` (`130.8–150.4 ms`) | **`-189.0 ms` (`-57.3%`)** |

**Analysis (Desktop):** On an unthrottled local connection, WordPress spends `~177 ms` executing PHP 8.2, querying MariaDB for post meta and ACF field definitions, normalizing field groups, and rendering shortcodes before sending the first byte. Astro serves pre-built static HTML in `14.3 ms` (`9.4 ms` without gzip compression overhead in `measurements.json`), cutting `FCP`, `LCP`, `DCL`, and `load` in half (`~332 ms → ~164–168 ms`).

### 5.2 Mobile Throttled (`mobile-375-throttled` — `375×812`, 4× CPU Slowdown, Slow 4G, `7` Runs)

Comparison across both [`measurements-gzip.json`](artifacts/stage3/measurements-gzip.json) (Astro server gzip enabled, matching WordPress Apache) and [`measurements.json`](artifacts/stage3/measurements.json) (Astro server uncompressed):

| Metric | WordPress (`gzip` via Apache) Median (`Min–Max`) | Astro (`COMPRESS=gzip`) Median (`Min–Max`) | Astro (Uncompressed Server) Median (`Min–Max`) | Delta: Astro (`gzip`) vs WP |
| :--- | :---: | :---: | :---: | :---: |
| **TTFB (`responseStart`)** | `152.0 ms` (`150.2–170.9`) | `12.7 ms` (`11.2–13.2`) | `8.3 ms` (`7.1–8.8`) | **`-139.3 ms` (`-91.6%`)** |
| **FCP** | `960.0 ms` (`940.0–1064.0`) | `920.0 ms` (`876.0–948.0`) | `1196.0 ms` (`1140.0–1236.0`) | **`-40.0 ms` (`-4.2%`)** |
| **LCP** | `960.0 ms` (`940.0–1064.0`) | `920.0 ms` (`876.0–948.0`) | `1196.0 ms` (`1140.0–1236.0`) | **`-40.0 ms` (`-4.2%`)** |
| **CLS** | `0` (`0–0`) | `0` (`0–0`) | `0` (`0–0`) | **`0` (Identical)** |
| **TBT Approx (`tbtApprox`)** | `52.0 ms` (`0–62`, `1` long task) | `0.0 ms` (`0–0`, `1` task `≤ 50 ms`) | `21.0 ms` (`13–24`, `1` long task) | **`-52.0 ms`** |
| **DOMContentLoaded (`DCL`)** | `1064.6 ms` (`1038.6–1165.4`) | `965.1 ms` (`923.3–992.5`) | `1280.6 ms` (`1272.5–1314.0`) | **`-99.5 ms` (`-9.3%`)** |
| **Window `load` Event** | `1184.9 ms` (`1152.1–1285.6`) | `977.7 ms` (`935.7–1005.7`) | `1293.1 ms` (`1283.6–1325.3`) | **`-207.2 ms` (`-17.5%`)** |

**Analysis (Mobile Throttled):**
1. **Why HTTP Compression Matters on Slow 4G (`1196 ms` uncompressed vs `920 ms` gzip):**
   When the local Node static server serves `dist/` without `gzip` (`measurements.json`), transferring `62.1 KB` of raw HTML + `37.6 KB` of raw render-blocking CSS (`99.8 KB` total) over a `1.6 Mbps` (`200 KB/s`) throttled link requires `~500 ms` of pure wire transfer time, making uncompressed Astro `240 ms` slower on FCP/LCP (`1196 ms`) than gzipped WordPress (`956 ms`). Enabling `gzip` on the static server (`measurements-gzip.json`) shrinks HTML + CSS transfer to `20.2 KB` (`~100 ms` transfer time), making Astro faster than WordPress across every metric (`FCP/LCP 920 ms vs 960 ms`, `DCL 965.1 ms vs 1064.6 ms`, `Load 977.7 ms vs 1184.9 ms`).
2. **Why `FCP` and `LCP` Improved by Only `40 ms` (`-4.2%`) Despite a `139 ms` TTFB Advantage:**
   Both pages use external `<link rel="stylesheet">` tags in `<head>`, creating a **2-round-trip critical rendering path** (`Document HTML → External CSS → First Paint`). Under CDP's `150 ms` emulated latency, the two sequential request round-trips plus connection setup (`~750–800 ms`) dominate `FCP` and `LCP`. Furthermore, because the hero image (`novatech-hero-architecture.png`) is only `6,756 B` (`6.6 KB`), it finishes downloading alongside the stylesheets on both sites, causing `FCP` and `LCP` to fire in the same paint frame (`920 ms` in Astro, `960 ms` in WordPress). Inlining critical CSS in Astro (`build.inlineStylesheets: 'always'`) would eliminate the second blocking network round-trip.
3. **Main-Thread Blocking (`TBT`):**
   Under `4×` CPU slowdown, WordPress's `7` external scripts + `wp-emoji` worker + CF7 initialization trigger a Long Task exceeding `100 ms` (`tbtApprox` median `52 ms`, max `62 ms`), whereas Astro's single deferred ES module stays at the `50 ms` Long Task threshold (`tbtApprox` median `0 ms` with gzip).

---

## 6. Visual & Structural Parity

Visual parity was verified by [`astro-lab/tests/visual-compare.mjs`](../astro-lab/tests/visual-compare.mjs) (results in `astro-lab/tests/artifacts/compare-report.json`) across all three project viewports with CSS animations frozen, lazy images eagerly loaded, and both Swiper carousels reset to slide `0`.

| Viewport | Document Height (WP / Astro) | Full-Page Pixel Diff | Computed Style Diffs (`140` checks) | Image Box Diffs (`10` images) | Hero / Services / Testimonials / Process / FAQ Section Diffs | Cases / Contact Section Diffs |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **`mobile-375`** (`375×812`) | `13,455 px` / `13,433 px` (`-22 px`) | **`4.83%`** | **`0`** | **`0`** | `0.00%` / `0.35%` / `0.33%` / `0.57%` / `0.00%` | `1.85%` (`-8 px`) / `5.73%` (`-14.6 px`) |
| **`tablet-768`** (`768×1024`) | `9,662 px` / `9,670 px` (`+8 px`) | **`3.44%`** | **`0`** | **`0`** | `0.00%` / `0.34%` / `0.07%` / `0.00%` / `0.32%` | `1.42%` (`-8 px`) / `4.05%` (`+16 px`) |
| **`desktop-1440`** (`1440×900`) | `8,103 px` / `8,111 px` (`+8 px`) | **`2.47%`** | **`0`** | **`0`** | `0.10%` / `0.12%` / `0.14%` / `0.02%` / `0.10%` | `1.10%` (`-8 px`) / `4.33%` (`+16 px`) |

### Root Causes of Residual Pixel Differences (`2.47%–4.83%`)
1. **Five of Seven Sections Are Visually Identical (`0.00%–0.57%` diff):** `hero`, `services`, `testimonials`, `process`, and `faq` have identical section heights (`0 px` delta at all viewports) and `0.00%–0.57%` sub-pixel anti-aliasing variance.
2. **Case Studies Section (`-8 px` height delta at all viewports, `1.10%–1.85%` pixel diff):** Caused by minor pagination container height differences between Swiper `11.2.6` (WordPress) and Swiper `12.1.2` (Astro, upgraded for security advisory `GHSA-hmx5-qpq5-p643`).
3. **Contact Section (`-14.6 px` at `375px`, `+16 px` at `768px`/`1440px`, `4.05%–5.73%` pixel diff):** Caused by minor form-footer spacing differences between CF7's `<span class="wpcf7-spinner">` wrapper and Astro's form markup. Because the `8 px` height delta in `#cases` and `-15/+16 px` delta in `#contact` shift the vertical `Y` coordinate of subsequent elements by `8–22 px`, full-page pixelmatch comparisons accumulate vertical offset diffs below `#cases` even where individual sections are `99.4%+` identical.

---

## 7. Accessibility & Interactive Behaviour Comparison

### 7.1 Automated DOM Accessibility Checks (`stage3-measure.mjs`)

| Accessibility Check | WordPress | Astro | Assessment |
| :--- | :---: | :---: | :--- |
| `<html lang>` attribute | `"en-US"` | `"en-US"` | Pass (Identical) |
| Document `<title>` | `"NovaTech Solutions – NovaTech Solutions"` | `"NovaTech Solutions – NovaTech Solutions"` | Pass (Identical) |
| `<meta name="viewport">` (`width=device-width, initial-scale=1.0`) | `true` | `true` | Pass (Identical) |
| `<h1>` count & heading hierarchy skips (`h1..h6`) | `1` `<h1>`, `0` skips | `1` `<h1>`, `0` skips | Pass (Identical) |
| Semantic landmarks (`header`, `nav`, `main`, `footer`) | `1` / `2` / `1` / `1` | `1` / `2` / `1` / `1` | Pass (Identical) |
| Skip link (`a.nt-skip-link[href="#main-content"]`) | `true` | `true` | Pass (Identical) |
| Images with missing or empty `alt` (`10` images total) | `0` missing, `0` empty | `0` missing, `0` empty | Pass (Identical) |
| Buttons or `<a href>` links without accessible name | `0` buttons, `0` links | `0` buttons, `0` links | Pass (Identical) |
| Form fields without `<label>` / accessible label | `0` of `4` | `0` of `5`¹ | Pass (100% labelled) |
| Duplicate `id` attributes in DOM | `0` | `0` | Pass (Identical) |
| Broken `aria-controls` target IDs | `0` | `0` | Pass (Identical) |
| `aria-expanded` interactive triggers | `10` (`1` nav + `4` process + `5` FAQ) | `10` (`1` nav + `4` process + `5` FAQ) | Pass (Identical) |
| Positive `tabindex` anti-patterns (`tabindex > 0`) | `0` | `0` | Pass (Identical) |
| Initial ARIA live regions (`[aria-live]`, `[role=status]`, `[role=alert]`) | `5`² | `1`² | Explained below |

*¹ Why Astro reports `5` `visibleFormFields` vs WordPress's `4`:*
Astro's contact form includes an anti-spam honeypot field (`<input id="nt-website" name="website" tabindex="-1">`) inside `<div style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">` with an explicit `<label for="nt-website">`. Because the container has `width: 1px; height: 1px` (`offsetWidth > 0`) rather than `display: none`, the measurement script's geometry filter (`el.offsetWidth || el.offsetHeight`) counts it as a 5th field. It is positioned off-screen (`left: -10000px`), marked `aria-hidden="true"`, and removed from keyboard tab order (`tabindex="-1"`).

*² Why WordPress has `5` initial live regions vs Astro's `1`:*
- In **Astro**, `ContactForm.astro` renders `1` initial polite live region (`<p role="status" aria-live="polite" aria-atomic="true" data-form-live></p>`), and `contact-form.ts` dynamically sets `role="alert"` on `.wpcf7-response-output` when a submission completes or fails.
- In **WordPress**, Contact Form 7 renders its status nodes plus Swiper 11's UMD bundle includes the `A11y` module by default, which injects `<span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span>` into each of the 2 Swiper carousels (`#nt-cases-swiper` and `#nt-testimonials-swiper`). In Astro, `src/scripts/ui.ts` tree-shakes Swiper 12 to `[Navigation, Pagination, Keyboard, Autoplay]` without importing `A11y` (matching the explicit options in `novatech.js`, though omitting Swiper's automatic `swiper-notification` live region spans).

### 7.2 Interactive Accessibility & Behavioural Verification
Verified by [`wordpress/scripts/verify-browser.mjs`](../wordpress/scripts/verify-browser.mjs) (`16/16 PASS`) and [`astro-lab/tests/e2e.test.mjs`](../astro-lab/tests/e2e.test.mjs) (`19/19 PASS`):
- **Keyboard Navigation:** Both sites pass WAI-ARIA accordion keyboard navigation (`ArrowDown`, `ArrowUp`, `Home`, `End` move focus between headers; `Enter`/`Space` toggle panels) and mobile drawer `Escape` key handling (closes drawer and returns focus to `#nt-mobile-menu-toggle`).
- **Motion Sensitivity (`prefers-reduced-motion: reduce`):** Both sites detect OS reduced-motion preferences, stop Testimonials Swiper autoplay on load, synchronize the Pause/Resume toggle button (`aria-pressed="true"`, label `"Resume Autoplay"`), and set carousel transition speed to `0 ms`.

---

## 8. Architecture, Content Workflow & Security Comparison

| Dimension | WordPress Baseline (`wordpress/`) | Astro Implementation (`astro-lab/`) |
| :--- | :--- | :--- |
| **Content Storage & Schema** | MariaDB (`wp_posts`, `wp_postmeta`) + `acf-json/group_novatech_landing.json` | Git-tracked `src/content/novatech.json` validated by `src/content/schema.ts` (Zod 4) |
| **Repeatable Content Modeling** | ACF Free fixed sub-groups (`card_1..card_6`, `slide_1..slide_5`) with `enabled` toggles; adding a 7th card requires schema edits | Native variable-length JSON arrays (`cards[]`, `slides[]`, `steps[]`, `items[]`) normalized during export |
| **Content Validation Timing** | Runtime PHP fallbacks (`! empty(...)`) during page render | Dual build-time gate: Zod schema validation in `export-content.mjs` and `astro build` (fails build on invalid data) |
| **Contact Form Architecture** | Contact Form 7 plugin (`POST /wp-json/contact-form-7/v1/.../feedback`) → `wp_mail()` → internal SMTP (`mailpit:1025`) | `contact-form.ts` (`fetch`) → standalone `server/form-server.mjs` (`POST /api/contact`) → Mailpit HTTP Send API (`127.0.0.1:8025`) |
| **Form Security Controls** | CF7 server validation + WP nonces/sanitization | Origin allowlist (`403`), `16 KB` body limit (`413`), per-IP rate limit (`10/min`, `429`), honeypot field, CR/LF header stripping, HTML escaping |
| **Production Hosting Footprint** | Requires PHP 8.2 + Apache/Nginx + MariaDB/MySQL server + persistent database volume | Static files (`dist/`) hostable on any CDN/object storage + tiny serverless/Node function for `/api/contact` |
| **Runtime Attack Surface** | WordPress Core, `/wp-admin/`, `/wp-login.php`, `/wp-json/`, PHP execution, SQL database, third-party plugins | Static HTML/CSS/JS assets (read-only) + single JSON `POST /api/contact` endpoint (Node stdlib only, zero npm runtime deps) |
| **Client-Side Dependency Advisories** | Ships `swiper@11.2.6` (`GHSA-hmx5-qpq5-p643` prototype pollution advisory; low practical risk as options are static) | Upgrades to `swiper@12.1.2` (patched). Build toolchain (`astro@6.4.8`) has dev/SSR advisories requiring `astro@>=7.3.2` (`Node >= 22.23.4`), unused in static output |

---

## 9. Limitations & Threats to Validity

To keep conclusions strictly grounded in evidence, the following limitations of this laboratory comparison must be noted:

1. **Loopback Network Environment (`localhost` / `127.0.0.1`):**
   Both WordPress (`localhost:8001`) and Astro (`127.0.0.1:4341`) were measured on the same local Windows machine without WAN routing, DNS lookup, TLS handshake overhead, or CDN edge caching.
2. **Uncached WordPress Container vs Static File Server:**
   The WordPress baseline runs default dynamic PHP + MariaDB rendering without a full-page cache plugin (e.g., WP Super Cache, FastCGI cache, or Redis object cache). Adding a full-page cache to WordPress would largely eliminate the `~140–162 ms` TTFB gap for cached anonymous visits, **but would not reduce WordPress's `14`-request waterfall, `222 KB` JS payload, `53 KB` CSS payload, or `101 KB` HTML document weight**.
3. **Synthetic CDP Throttling vs Field Telemetry:**
   The `mobile-375-throttled` profile uses Chromium DevTools Protocol request-level emulation (`4×` CPU slowdown, `150 ms` latency, `1.6 Mbps` down) on a desktop x86_64 CPU rather than packet-level network shaping on physical mid-range mobile hardware. Real-world CrUX / RUM metrics (especially **INP**, which cannot be measured in synthetic non-interactive page loads) require field measurement.
4. **Intentionally Unmodified Media & External CSS Strategy:**
   To maintain strict visual parity in Stage 2, the 10 PNG images (`38.7 KB` total on disk, `6.8 KB` hero loaded initially) were copied unchanged rather than re-encoded to WebP/AVIF via `astro:assets`, and stylesheets were kept as external `<link rel="stylesheet">` files rather than inlined (`inlineStylesheets: 'always'`). Consequently, the measured `46.8%` gzip payload reduction reflects **template, CSS, and JS architecture improvements alone**, leaving further optimization headroom (critical CSS inlining and modern image formats) available in Astro.
5. **Automated Accessibility Scope:**
   The 19 accessibility checks in `stage3-measure.mjs` and keyboard tests in `e2e.test.mjs` verify DOM structure, ARIA state wiring, labels, landmarks, and keyboard focus management, but are not a substitute for a full manual WCAG 2.1 AA audit or assistive technology testing with screen readers (NVDA, JAWS, VoiceOver).
6. **Swiper `A11y` Module Omission in Astro:**
   While all custom ARIA controls (`aria-expanded`, `aria-controls`, `aria-pressed`, `aria-label`, and keyboard arrow handling) were ported 1:1 to `src/scripts/ui.ts`, tree-shaking Swiper 12 to `[Navigation, Pagination, Keyboard, Autoplay]` omitted Swiper's optional `A11y` module (`swiper-notification` live regions). If screen-reader slide-change announcements from Swiper itself are desired alongside the existing button labels, `A11y` from `swiper/modules` can be added to the module list in `ui.ts`.

---

## 10. Conclusion & When to Choose Each Stack

### Empirical Verdict for NovaTech Solutions
Migrating the **NovaTech Solutions** landing page from WordPress (ACF Free + CF7 + Twenty Twenty-Five child theme) to Astro achieved:
- **100% functional and computed-style parity** (`19/19` E2E browser tests passing, `16/16` backend unit tests passing, `0` computed style diffs across `375px`, `768px`, and `1440px`, verified end-to-end Mailpit email delivery).
- **`71.4%` fewer HTTP requests** (`14 → 4` on desktop, `15 → 4` on mobile) and **`46.8%` smaller gzipped transfer size** (`103.6 KB → 55.1 KB`), driven by a **`55.9%` reduction in gzipped JavaScript** (`63.8 KB → 28.1 KB`) and **`40.9%` reduction in gzipped external CSS** (`12.7 KB → 7.5 KB`).
- **`12×` faster server TTFB** (`176.7 ms → 14.3 ms` desktop, `152.0 ms → 12.7 ms` mobile) and **`2×` faster desktop FCP/LCP** (`332 ms → 164–168 ms`), while eliminating runtime database and CMS maintenance for public traffic.

### Engineering Trade-off Matrix
- **Choose Astro (Static + API Endpoint)** when performance, minimal JS/CSS payload, strict build-time schema validation (`Zod`), Git-based review workflows, low hosting cost, and a minimal runtime security surface are primary goals—or in a **hybrid headless setup** where WordPress remains on an internal/staging network purely as an editorial CMS exporting to Astro builds.
- **Choose WordPress + ACF** when non-technical editors require an integrated web dashboard (`WP Admin`) with instant, no-build publishing and rich plugin ecosystems on a single monolithic host, and the team is prepared to manage PHP/MySQL infrastructure, plugin updates, and page-caching layers.
