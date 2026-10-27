# Stage 2 – WordPress → Astro Migration

This document describes the Astro implementation in [`astro-lab/`](../astro-lab), how the content was migrated from WordPress/ACF, and the verified results. All numbers below come from commands that were actually run during Stage 2 (see [Test coverage](#7-test-coverage-and-results)).

## 1. Architecture

```text
WordPress (Docker, :8001) ──REST──▶ scripts/export-content.mjs ──▶ src/content/novatech.json  (zod-validated)
        (migration-time only)                  │                    public/images/*.png (downloaded media)
                                               ▼
                                   astro build (static)  ──▶  dist/  (plain HTML + hashed JS/CSS + images)

Browser ──▶ dist/index.html                              (no WordPress, no REST calls at runtime)
Browser ──POST /api/contact──▶ server/form-server.mjs ──HTTP Send API──▶ Mailpit (127.0.0.1:8025)
```

```text
astro-lab/
├─ src/content/schema.ts        zod schema (single source of truth for the content shape)
├─ src/content/novatech.json    generated content (committed, reproducible)
├─ src/content/index.ts         parses the JSON with the schema at build time
├─ src/components/              Hero, Services, Cases, Testimonials, Process, Faq, Contact(+Form), SiteHeader/Footer, BrandMark, icons.ts
├─ src/layouts/BaseLayout.astro html shell, imports CSS + Swiper CSS
├─ src/pages/index.astro        composes the sections (same order as page-novatech.php)
├─ src/scripts/ui.ts            mobile nav, both Swipers, accordions (port of novatech.js)
├─ src/scripts/contact-form.ts  fetch-based form enhancement
├─ src/styles/novatech.css      the WordPress stylesheet, copied unchanged
├─ public/images/               10 PNGs downloaded from the WP media library
├─ scripts/export-content.mjs   ACF → JSON exporter
├─ server/                      form backend (+ validation, Mailpit mailer, unit tests)
└─ tests/                       e2e.test.mjs (Playwright + Chrome), visual-compare.mjs
```

Key decisions:

| Decision | Reason |
| :--- | :--- |
| Astro **6.4.8**, static output | Astro 7.x requires Node ≥ 22.23.4; the installed Node is 22.17.1 (see §9). |
| Typed JSON + zod instead of Content Collections | One page, one document. A collection would add indirection without benefit. The schema is validated by the exporter *and* again at build time. |
| Stylesheet copied unchanged | The user approved the WP design; reusing the CSS gives the closest parity. Class names (`nt-*`, and `wpcf7*` in the form) are preserved on purpose. |
| Plain `<img>` pointing to `public/images` | Same markup/attributes as WP (explicit `width`/`height`, lazy loading, `fetchpriority`) – no image re-encoding, no `sharp` code path. |
| Swiper as an ES module (12.1.2) | Tree-shaken to Navigation/Pagination/Keyboard/Autoplay. WP ships 11.2.6 as a global bundle. 12.1.2 is the first release without the prototype-pollution advisory GHSA-hmx5-qpq5-p643 (affects 6.5.1–12.1.1); the options used are API-compatible. |
| Vanilla TS, no UI framework | Interactions are small and were already vanilla JS. |

## 2. Setup and commands

Requirements: Node ≥ 22.12, Google Chrome (tests use `playwright-core` with the installed Chrome, no browser download), Docker WordPress stack only for the exporter / comparison / Mailpit.

```powershell
cd astro-lab
npm install                 # on networks with TLS interception: $env:NODE_OPTIONS='--use-system-ca'
npm run export:content      # optional: re-export from WordPress (needs http://localhost:8001)
npm run dev                 # http://127.0.0.1:4321
npm run build               # static site in dist/
npm run preview             # serve dist/ on http://127.0.0.1:4321
npm run form:server         # form backend on http://127.0.0.1:4322 (needs Mailpit on :8025)
npm run check               # astro check (TypeScript + Astro diagnostics)
npm run test:unit           # backend unit/integration tests (no network)
npm run test:e2e            # browser tests against dist/ + real backend + real Mailpit
npm run compare             # WordPress vs Astro screenshots/metrics -> tests/artifacts/
```

To use the contact form locally run `npm run preview` and `npm run form:server` side by side. The WordPress containers (`wordpress/`) only need to run for Mailpit; the Astro page itself never contacts WordPress.

Configuration is via environment variables (template: `astro-lab/.env.example`, no secrets). Only `PUBLIC_FORM_ENDPOINT` is build-time.

## 3. ACF → Astro data mapping

`scripts/export-content.mjs` reads `GET /wp-json/wp/v2/pages?slug=novatech-solutions` (ACF Free with `show_in_rest`; image fields are expanded to objects by the project's MU-plugin). Verified response facts that drive the transformation:

* ACF Free has no repeater, so lists are **fixed sub-groups** (`card_1…card_6`, `slide_1…`, `step_1…`, `nav_1…`). The exporter takes the values in key order, and drops items with `enabled = false` (the same rule as `nt_normalize_acf_list()` in `functions.php`; hero stats are not filtered, as in the template).
* Image fields arrive as `{id,url,alt,title,width,height,mime}`. The exporter downloads each file once into `public/images/` and rewrites `url` → `/images/<file>`.
* The process `deliverables` textarea is split on newlines into an array (same as the PHP template).
* Empty strings become *absent* optional fields; required fields are enforced by the schema, so the export fails loudly instead of producing a half-empty page.
* `cf7_shortcode` is intentionally **not** exported (CF7 is replaced).

| ACF group → field | Astro (`novatech.json`) | Rendered by |
| :--- | :--- | :--- |
| `header_settings` → `logo_text`, `logo_badge`, `announcement_text`, `cta_label`/`cta_url`, `nav_items.nav_n.{label,url}` | `header.{logoText,logoBadge,announcement,cta,nav[]}` | `SiteHeader`, `SiteFooter`, `BrandMark` |
| `hero_section` → `eyebrow_badge`, `title`, `subtitle`, `primary/secondary_cta_*`, `hero_image`, `architecture_caption`, `stats.stat_n.{value,label,detail}` | `hero.{eyebrow,title,subtitle,primaryCta,secondaryCta,image,caption,stats[]}` | `Hero` |
| `services_section` → `section_*`, `cards.card_n.{icon_key,badge,title,description,deliverable_highlight,link_text,link_url}` | `services.{tag,title,subtitle,cards[]{icon,badge,title,description,deliverable,link}}` | `Services` + `icons.ts` |
| `cases_slider_section` → `section_*`, `demo_disclaimer`, `slides.slide_n.{category,client_name,title,summary,metric_value,metric_label,secondary_metric,tech_stack,image}` | `cases.{…,disclaimer,slides[]}` | `Cases` |
| `testimonials_section` → `section_*`, `autoplay_delay` (optional, default 6000), `slides.slide_n.{quote,author_*,company,engagement_scope,rating,avatar}` | `testimonials.{…,autoplayDelay,slides[]}` | `Testimonials` |
| `process_section` → `section_*`, `allow_multiple`, `steps.step_n.{step_number,title,duration_badge,summary,deliverables,governance_note,default_open}` | `process.{…,allowMultiple,steps[]{number,…,deliverables[],defaultOpen}}` | `Process` |
| `faq_section` → `section_*`, `items.faq_n.{category_badge,question,answer,default_open}` | `faq.{…,items[]{badge,question,answer,defaultOpen}}` | `Faq` |
| `contact_footer_section` → `contact_*`, `response_sla_note`, `company_*`, `office_hours` | `contact.{tag,title,subtitle,slaNote,email,phone,address,officeHours}` | `Contact` |
| `contact_footer_section` → `footer_description`, `copyright_text`, `demo_environment_notice` | `footer.{description,copyright,demoNotice}` | `SiteFooter` |
| rendered `<title>` / `<html lang>` | `meta.{pageTitle,lang}` | `BaseLayout` |

Export result on the live baseline: 5 nav items, 3 stats, 6 services, 5 case slides, 4 testimonials, 4 process steps, 5 FAQ items, 10 images, 0 warnings.

## 4. Component migration

The PHP template was split along its existing section boundaries; each section became one `.astro` component taking its typed slice of the content (`NovatechContent['hero']`, …). Markup, class names, ids, ARIA attributes and `data-*` hooks are byte-for-byte equivalent to the WP output apart from whitespace and the points below:

* Header/footer brand link: WP printed the page permalink; Astro uses `/`.
* `BrandMark` (new) removes the duplicated logo SVG that existed in header and footer templates.
* Service icons: PHP helper `nt_get_service_icon_svg()` → `icons.ts` (same SVG paths, injected with `set:html` from a constant table, never from user input).
* The accordion DOM is repeated in `Process` and `Faq` (as in WP) – the differences (step number, badge, deliverables) are large enough that a shared component would have needed many slots; this was not worth abstracting.
* Output escaping: Astro escapes all interpolated text by default, matching `esc_html()`.

## 5. Swiper and JavaScript migration

`src/scripts/ui.ts` is a typed port of `novatech.js`. Behaviour and options are identical (cases: 1/2/3 slides at <768/768/1024 px, spacing 16/20/24, keyboard, arrows, clickable pagination; testimonials: loop, autoplay with `disableOnInteraction:false`, `pauseOnMouseEnter`, pause/resume button, pause on arrows/touch, reduced-motion → speed 0 and autoplay stopped). Differences: ES-module Swiper instead of `window.Swiper`; `Swiper` modules are listed explicitly; the script is bundled and loaded by Astro as a deferred module. Swiper CSS is imported from `swiper/css/bundle` (bundled by Vite).

## 6. CF7 replacement and form delivery

* **Frontend** – `ContactForm.astro` reproduces the CF7 markup (ids, labels, placeholders, `wpcf7-*` classes so the stylesheet applies unchanged). `contact-form.ts` posts JSON via `fetch`, renders per-field errors (`aria-invalid`, `aria-describedby`) and a status message in `.wpcf7-response-output` + a live region. A success message is displayed **only** after the backend answered `{ok:true}`; network or backend failures show an error.
* **Backend** – `server/form-server.mjs` (Node standard library only): `POST /api/contact`, `GET /api/health`. It validates server-side (4 required fields, e-mail format, max 400/2000 chars, CR/LF stripped from header-bound fields, non-string values rejected), limits body size (16 KB), rate-limits per IP (10/min), allows only configured origins (CORS + 403), has a honeypot field, and never echoes user input as HTML.
* **Delivery** – `server/mailer.mjs` uses Mailpit's HTTP **Send API** (`POST /api/v1/send`). The baseline's Mailpit publishes only port 8025 (SMTP 1025 is internal to the Docker network) and the WordPress compose file must not be changed for this experiment, so SMTP was not reachable from the host. Messages land in the same inbox as the CF7 mails (recipient `architecture-desk@novatech.local`, `Reply-To` = submitter, subject prefix `[NovaTech Lab Inquiry]`).
* **Production** – replace the host with a deployed service (serverless function, small Node service or the hosting provider's form handler); swap `mailer.mjs` for an SMTP/transactional-email provider using secrets from the platform's environment store; set `PUBLIC_FORM_ENDPOINT` at build time (or serve the API on the same origin under `/api/contact`); restrict `ALLOWED_ORIGINS` to the production origin; replace the in-memory rate limiter by a shared store / WAF and add CAPTCHA if abuse appears; add logging/monitoring and a delivery-failure fallback (queue/retry).

## 7. Test coverage and results

Run on 2026-10-23 (e2e) and re-verified on 2026-10-27 for check/build/unit, Windows, Node 22.17.1, Chrome (headless), Astro 6.4.8.

| Suite | Command | Result |
| :--- | :--- | :--- |
| Type/Astro diagnostics | `npm run check` | 0 errors, 0 warnings, 0 hints (23 files), re-run 2026-10-27 |
| Production build | `npm run build` | success, 1 page, re-run 2026-10-27 |
| Backend unit/integration (validation, mailer, HTTP behaviour incl. 422/400/415/403/405/404/413/429-free paths, honeypot, 502 on failure) | `npm run test:unit` | 16 passed, 0 failed (re-run 2026-10-27; needs loopback networking) |
| Browser e2e (served from `dist/`, WordPress not used) | `npm run test:e2e` | 19 passed, 0 failed on 2026-10-23; **not re-run 2026-10-27** (Docker/Mailpit unavailable) |

The e2e suite covers: section order and counts; English-only text; all 10 images load and have `alt`; anchor targets exist; zero console errors / page errors / failed requests / 4xx-5xx / non-local requests; built output contains no `localhost:8001`, `wp-content`, `wp-json`; no horizontal overflow, grid columns and Swiper `slidesPerView` at 375/768/1440; mobile drawer (open, `aria-expanded`/label, Escape + focus return, link closes); cases Swiper (next/prev/pagination); testimonials Swiper (loop, autoplay running, pause/resume, arrows pause autoplay, autoplay advances on its own, reduced-motion); process accordion (ACF default state, multi-expand); FAQ accordion (single-expand, arrow/Home/End keys, Enter/Space, ARIA wiring); form validation (4 field errors, nothing sent), **a real submission found in Mailpit via its API** (recipient, reply-to, subject, body, escaping), and error display when the backend is unreachable.

Not covered automatically: visual quality judgement (see §8), cross-browser (Chrome only), touch gestures, screen-reader behaviour.

## 8. Visual comparison findings

See the generated section below / `astro-lab/tests/artifacts/compare-report.json` (screenshots and diff images are git-ignored and regenerated by `npm run compare`).

> [!NOTE]
> The comparison below comes from the existing report generated on 2026-10-23 (`npm run compare`). It was **not re-run** on 2026-10-27 because Docker Desktop could not be started (see �12), so WordPress was unavailable.

Full-page screenshots at the project's three viewports (WordPress :8001 vs. Astro `dist/`, Chrome headless, reduced motion):

| Viewport | Document height WP / Astro | Full-page pixel diff |
| :--- | :--- | :--- |
| mobile 375 | 13455 / 13835 px (+2.8 %) | 15.85 % |
| tablet 768 | 9662 / 9885 px (+2.3 %) | 12.26 % |
| desktop 1440 | 8103 / 8261 px (+1.9 %) | 11.34 % |

Per-section pixel diff (375 / 768 / 1440): hero 11.3 / 9.9 / 9.6 %, services 10.6 / 6.3 / 9.3 %, cases 6.6 / 7.6 / 3.0 %, testimonials 21.3 / 4.2 / 3.2 %, process 10.8 / 5.1 / 3.3 %, FAQ 12.7 / 2.5 / 1.2 %, contact 10.0 / 12.6 / 12.9 %.

Interpretation (not fully root-caused): the section structure, order, text and images match; the diffs are dominated by small vertical offsets that accumulate down the page (Astro is 2-3 % taller), which shifts everything below and inflates a pixel-wise diff. The Swiper-driven sections (testimonials autoplay position, case slider) also differ by state at capture time. No missing content or broken layout was observed in the report. The cause of the height offset was not identified, so no CSS changes were made (the stylesheet is the unchanged WP file); it is documented as a limitation rather than chased pixel by pixel.

## 9. Known limitations

* **Astro security advisories.** `npm audit` reports advisories against Astro ≤ 7.3.1 (spread-attribute/transition XSS, AVIF image optimisation, `base` stripping, MDX rendering) and dev-server `esbuild` file-read on Windows. The fixed Astro 7.x needs Node ≥ 22.23.4; upgrading the machine's Node is a global change outside this task. The project uses none of the affected features (no `transition:*`, spread attributes, `astro:assets`, `base`, MDX), ships only static files, and `npm run dev` is bound to 127.0.0.1 – but upgrading Node and Astro is recommended.
* **Form delivery needs the backend** and Mailpit's HTTP API; without JavaScript the form posts to the backend and shows raw JSON (no no-JS HTML fallback).
* Rate limiting is in-memory and per process.
* Mail goes only to the local Mailpit (by design for this lab). Note that test mails (including one manual probe) were added to the shared Mailpit inbox.
* Chrome-only browser tests; tests need the Docker Mailpit running.
* The exporter needs a running WordPress; content in `novatech.json` is a snapshot of WP modified at `meta.sourceModified`.

## 10. WordPress baseline observations (not changed)

* The WP theme bundles Swiper **11.2.6**, which is affected by GHSA-hmx5-qpq5-p643 (prototype pollution, fixed in 12.1.2). Exploitation needs attacker-controlled Swiper options, which the theme does not have, so the practical risk is low.
* `README`'s Stage 1 numbers (43 WordPress checks, 16 browser checks) were not re-run in Stage 2; WordPress was only used as the input and visual reference and was healthy (HTTP 200, REST returned all 8 ACF groups).
* npm in this environment failed with `UNABLE_TO_VERIFY_LEAF_SIGNATURE` (TLS interception); worked around per process with `NODE_OPTIONS=--use-system-ca`, TLS verification was **not** disabled.

## 11. Lessons learned

* ACF Free's fixed sub-groups map cleanly to ordered arrays; the exporter is ~250 lines and the schema catches missing data at export and build time. A repeater would have been simpler on the WP side, but not required.
* Copying the CSS and DOM contract kept the migration mechanical; the real work was the JS/behaviour port and replacing CF7.
* The biggest decisions were not framework-related but environmental: Node vs. Astro engine requirements, a vulnerable Swiper version, and an SMTP port that is not published from Docker. Checking `engines` and `npm audit` before choosing versions saved a rework.
* Driving real Chrome tests against `dist/` (not the dev server) gives confidence that the deployable artifact works without WordPress.
* Always confirm delivery at the receiving end: the e2e test queries Mailpit rather than trusting the success message.

## 12. Stage 2 closing notes (2026-10-27)

* **Blocker:** Docker Desktop did not start (engine pipe missing after launching the app and waiting ~3 min), so Mailpit and WordPress were down. E2E, the Mailpit delivery check and the visual comparison were therefore not re-run; results above are from 2026-10-23. Re-run with `npm run test:e2e` and `npm run compare` once Docker is up.
* On this machine `npm.ps1` is blocked by the PowerShell execution policy; use `npm.cmd` (policy not changed).
* `npm audit` findings are unchanged from �9 (Astro 7.x needs a newer Node; not upgraded).
* The temporary `_styles-diff.mjs` diagnostic script was removed.
