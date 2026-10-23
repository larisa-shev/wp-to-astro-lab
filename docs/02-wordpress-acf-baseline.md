# Stage 1 Baseline Documentation: WordPress, ACF Free & Interactive Components

## 1. Overview
This document records the exact implementation details, data contracts, interactive behaviors, and test verification results of the **NovaTech Solutions** WordPress baseline (`http://localhost:8001/novatech-solutions/`). It serves as the engineering specification for the upcoming Stage 2 migration to **Astro**.

---

## 2. Installed Stack & Dependencies

| Component | Version / Package | Role in Stage 1 | Astro Migration Target (Stage 2) |
| :--- | :--- | :--- | :--- |
| **WordPress Core** | `6.8.2` (`php8.2-apache`) | CMS runtime, routing, REST API | Replaced by Astro 5 static build & file routing |
| **Database** | `mariadb:11.4.5` | Stores posts, options, and `wp_postmeta` | Replaced by Astro Content Collections / local JSON/MD |
| **Parent Theme** | `twentytwentyfive` (`1.2`) | Base FSE theme required by child theme | Not needed in Astro |
| **Child Theme** | `ai-hub-theme` (`1.1.0`) | `page-novatech.php`, CSS design system, JS modules | Migrated to `BaseLayout.astro` + Astro components |
| **ACF (Free)** | `advanced-custom-fields` (`6.4.3`) | Structured content fields & REST API (`acf` payload) | Migrated to Zod schemas in `src/content.config.ts` |
| **Contact Form 7** | `contact-form-7` (`6.0+`) | Form rendering, client/server validation, `wp_mail()` | Migrated to native Astro form component + API handler |
| **Mailpit** | `axllent/mailpit:v1.22.3` | Local SMTP capture (`mailpit:1025`, UI `127.0.0.1:8025`) | Reused for local email verification in Astro |
| **Swiper** | `11.2.6` (Local bundle) | Case Studies carousel (#1) & Testimonials carousel (#2) | Migrated to Astro client script / island with Swiper 11 |

---

## 3. ACF Free Content Schema (`group_novatech_landing`)

All content on `/novatech-solutions/` is managed through the **`NovaTech Corporate Landing Fields`** field group (`wordpress/themes/ai-hub-theme/acf-json/group_novatech_landing.json`), attached to `page_template == page-novatech.php` with **`show_in_rest: 1`**.

### 3.1 Top-Level ACF Groups (8 Admin Tabs)
1. **`header_settings` (`group`)**
   - `logo_text` (`text`), `logo_badge` (`text`), `announcement_text` (`text`), `cta_label` (`text`), `cta_url` (`text`)
   - `nav_items` (`group`: `nav_1`..`nav_5` -> `{ enabled, label, url }`)
2. **`hero_section` (`group`)**
   - `eyebrow_badge` (`text`), `title` (`text`), `subtitle` (`textarea`)
   - `primary_cta_text`, `primary_cta_url`, `secondary_cta_text`, `secondary_cta_url` (`text`)
   - `hero_image` (`image` -> `{ id, url, alt, width, height }`)
   - `architecture_caption` (`text`)
   - `stats` (`group`: `stat_1`..`stat_3` -> `{ value, label, detail }`)
3. **`services_section` (`group`)**
   - `section_tag` (`text`), `section_title` (`text`), `section_subtitle` (`textarea`)
   - `cards` (`group`: `card_1`..`card_6` -> `{ enabled, icon_key, badge, title, description, deliverable_highlight, link_text, link_url }`)
4. **`cases_slider_section` (`group`)**
   - `section_tag` (`text`), `section_title` (`text`), `section_subtitle` (`textarea`), `demo_disclaimer` (`text`)
   - `slides` (`group`: `slide_1`..`slide_5` -> `{ enabled, category, client_name, title, summary, metric_value, metric_label, secondary_metric, tech_stack, image, case_url }`)
5. **`testimonials_section` (`group`)**
   - `section_tag` (`text`), `section_title` (`text`), `section_subtitle` (`textarea`), `autoplay_delay` (`number`)
   - `slides` (`group`: `slide_1`..`slide_4` -> `{ enabled, quote, author_name, author_role, company, engagement_scope, rating, avatar }`)
6. **`process_section` (`group`)**
   - `section_tag` (`text`), `section_title` (`text`), `section_subtitle` (`textarea`), `allow_multiple` (`true_false`)
   - `steps` (`group`: `step_1`..`step_4` -> `{ enabled, step_number, title, duration_badge, summary, deliverables, governance_note, default_open }`)
7. **`faq_section` (`group`)**
   - `section_tag` (`text`), `section_title` (`text`), `section_subtitle` (`textarea`)
   - `items` (`group`: `faq_1`..`faq_5` -> `{ enabled, category_badge, question, answer, default_open }`)
8. **`contact_footer_section` (`group`)**
   - `contact_tag`, `contact_title`, `contact_subtitle`, `response_sla_note`, `cf7_shortcode`, `company_email`, `company_phone`, `company_address`, `office_hours`, `footer_description`, `copyright_text`, `demo_environment_notice`

### 3.2 REST API Contract & Normalization Strategy for Astro
- **Endpoint:** `GET http://localhost:8001/wp-json/wp/v2/pages?slug=novatech-solutions&acf_format=standard`
- **Image Expansion:** The MU-plugin `novatech-mailpit-smtp.php` hooks into `acf/rest/format_value_for_rest/type=image` so that image fields always resolve to full metadata objects (`{ id, url, alt, width, height, mime }`) even if `?acf_format=standard` is omitted.
- **Transforming ACF Free Fixed Sub-Groups to Arrays in Astro:**
  In ACF Free, repeatable items are returned as keyed objects (`cards: { card_1: {...}, card_2: {...} }`). During the Stage 2 migration to Astro, these keyed objects are normalized via `Object.values(group).filter(item => item.enabled !== false)` into standard TypeScript/Zod arrays (`z.array(...)`), removing the fixed-slot constraint of ACF Free.

---

## 4. Interactive Frontend Specifications (To Preserve in Astro)

1. **Sticky Header & Accessible Mobile Drawer (`initHeaderAndMobileNav`):**
   - Toggles `.is-scrolled` when `window.scrollY > 16`.
   - Mobile button (`#nt-mobile-menu-toggle`) controls `#nt-mobile-menu-drawer` via `aria-expanded` and the native `hidden` attribute.
   - Pressing `Escape` closes the drawer and restores focus to `#nt-mobile-menu-toggle`.
   - Clicking any in-page navigation link (`[data-mobile-link]`) or resizing to desktop (`>= 960px`) closes the drawer automatically.
2. **Swiper Slider #1 — Case Studies (`#nt-cases-swiper`):**
   - Powered by Swiper 11.2.6.
   - Breakpoints: `1` slide per view (`< 768px`), `2` slides per view (`768px–1023px`), `3` slides per view (`>= 1024px`).
   - Independent navigation (`#nt-cases-prev`, `#nt-cases-next`), clickable pagination (`#nt-cases-pagination`), keyboard navigation, and touch swipe support.
3. **Swiper Slider #2 — Testimonials (`#nt-testimonials-swiper`):**
   - Powered by Swiper 11.2.6 (`slidesPerView: 1`, `loop: true`, `autoplay: { delay: 6000 }`).
   - Pauses automatically on hover, touch start, or manual arrow click.
   - Includes a dedicated Play/Pause toggle button (`#nt-testimonials-autoplay-toggle`) that updates `aria-pressed` (`"true"` when paused, `"false"` when playing) and its visible label (`Pause Autoplay` / `Resume Autoplay`).
   - Respects `prefers-reduced-motion: reduce`: if enabled at the OS/browser level, autoplay starts in the paused state and slide transition speed is set to `0ms`.
4. **Process Accordion (`#nt-process-accordion`) & FAQ Accordion (`#nt-faq-accordion`):**
   - Process Accordion sets `data-allow-multiple="true"` (multiple phases can remain open simultaneously; Phases 01 and 02 are open by default).
   - FAQ Accordion sets `data-allow-multiple="false"` (opening a question automatically closes other open questions; Question 1 is open by default).
   - Both implement WAI-ARIA keyboard navigation (`ArrowDown`, `ArrowUp`, `Home`, `End`, `Enter`, `Space`).
5. **Contact Form 7 (`NovaTech Project Inquiry`):**
   - Fields: `your-name` (required), `your-email` (required, email validation), `your-subject` (required), `your-message` (required).
   - REST feedback endpoint: `POST /wp-json/contact-form-7/v1/contact-forms/{id}/feedback`.
   - Valid submissions are delivered via SMTP (`mailpit:1025`) and captured in Mailpit (`http://127.0.0.1:8025`).

---

## 5. Automated Verification Results (`59 / 59 PASS`)

Verification is fully automated via two scripts in `wordpress/scripts/`:
- `wordpress/scripts/verify-stage1.ps1` (43 checks: Docker health, HTTP 200, zero PHP errors, 100% English UI check, DOM structure, asset HTTP 200, ACF REST API contract, seed idempotency, CF7 validation & Mailpit SMTP delivery).
- `wordpress/scripts/verify-browser.mjs` (16 checks in Headless Chrome via CDP: responsive viewports at `375px`, `768px`, `1440px`, zero horizontal scroll, CSS Grid column counts, Swiper #1 & #2 interactive behaviors, autoplay toggle button, `prefers-reduced-motion: reduce`, Process multi-panel accordion, FAQ single-panel accordion + keyboard navigation, mobile drawer + `Escape` focus restoration, and zero console errors).

| Category | Check Description | Result |
| :--- | :--- | :--- |
| **Infrastructure** | `db` (`mariadb:11.4.5`), `wordpress` (`6.8.2-php8.2-apache`), `mailpit` (`v1.22.3`) running | `PASS` |
| **HTTP & PHP** | `GET /novatech-solutions/` returns `200 OK` with zero PHP errors/warnings/notices | `PASS` |
| **Localization** | 100% English (`en-US`) content & system messages (zero Cyrillic characters in DOM) | `PASS` |
| **ACF & REST API** | All 8 top-level ACF groups and expanded image objects returned via `/wp-json/wp/v2/pages?slug=novatech-solutions` | `PASS` |
| **Idempotency** | Re-running `seed-novatech.php` maintains exactly 1 page, 1 CF7 form, and 1 ACF field group | `PASS` |
| **Responsive (375px)** | Zero horizontal overflow (`scrollWidth=375`), 1-column Services Grid, 1 visible Case slide | `PASS` |
| **Responsive (768px)** | Zero horizontal overflow (`scrollWidth=753`), 2-column Services Grid, 2 visible Case slides | `PASS` |
| **Responsive (1440px)** | Zero horizontal overflow (`scrollWidth=1425`), 3-column Services Grid, 3 visible Case slides | `PASS` |
| **Mobile Menu** | Opens on toggle click (`aria-expanded="true"`), closes on `Escape` with focus returned to button | `PASS` |
| **Swiper #1 (Cases)** | Real Swiper 11.2.6 instance; next arrow advances active slide (`0 -> 1`) | `PASS` |
| **Swiper #2 (Testimonials)** | Real Swiper 11.2.6 instance; autoplay runs, pauses/resumes via button (`aria-pressed` toggles) | `PASS` |
| **Reduced Motion** | Emulating `prefers-reduced-motion: reduce` stops Swiper #2 autoplay and sets transition speed to `0ms` | `PASS` |
| **Process Accordion** | Allows multiple delivery phases to remain expanded simultaneously | `PASS` |
| **FAQ Accordion** | Single-expand behavior + `ArrowDown`/`ArrowUp`/`Home`/`End` keyboard focus navigation | `PASS` |
| **Contact Form 7** | Rejects invalid/empty fields (`validation_failed`) and accepts valid submission (`mail_sent`) | `PASS` |
| **Mailpit Capture** | Submitted CF7 inquiry email verified inside Mailpit API (`http://127.0.0.1:8025/api/v1/messages`) | `PASS` |
| **Browser Console** | Zero `console.error` messages or uncaught page exceptions | `PASS` |

