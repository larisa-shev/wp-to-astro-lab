# Architecture & Migration Plan: WordPress (ACF Free) to Astro

## 1. Purpose of the Laboratory
`wp-to-astro-lab` is an isolated engineering environment built to model a realistic corporate website migration from **WordPress** to **Astro**. Rather than migrating a trivial blog post, this project models a rich B2B engineering landing page (**NovaTech Solutions**) featuring:
- Structured content management via **Advanced Custom Fields (ACF Free 6.4.3)**.
- Classic PHP page templating inside a child theme (`ai-hub-theme`) of the **Twenty Twenty-Five** block theme.
- Two independent **Swiper v11** carousels (a multi-column responsive Case Studies slider and a single-slide Testimonials slider with autoplay controls and `prefers-reduced-motion` support).
- Two distinct accessible accordion patterns (a multi-expand Delivery Process accordion and a single-expand Technical FAQ accordion).
- Server-validated **Contact Form 7** integration with local SMTP delivery to **Mailpit**.

---

## 2. Infrastructure Isolation & Security Boundaries

A security audit of typical local WordPress repositories identified three common anti-patterns that this repository explicitly prevents:
1. Committing `wp-config.php` with static authentication salts or hardcoded `JWT_AUTH_SECRET_KEY` secrets.
2. Committing client scripts containing live Bearer tokens.
3. Tracking WordPress Core (`wp-admin/`, `wp-includes/`), third-party plugins, or user upload directories in Git.

### How `wp-to-astro-lab` Enforces Isolation
- **Dedicated Network & Ports:** WordPress binds strictly to `127.0.0.1:8001` on the `wp_lab_net` bridge network, leaving any existing containers on port `8000` untouched.
- **Pinned Image Tags:**
  - `wordpress:6.8.2-php8.2-apache`
  - `mariadb:11.4.5`
  - `wordpress:cli-2.11.0-php8.2`
  - `axllent/mailpit:v1.22.3`
- **Internal-Only SMTP:** Mailpit's SMTP server (`mailpit:1025`) is never exposed to the host OS; only its inspection UI is bound to `127.0.0.1:8025`. Outbound emails from Contact Form 7 cannot leave the local machine.
- **Selective Bind Mounts:** Only `./themes/ai-hub-theme`, `./mu-plugins`, and `./scripts` are mounted from the repository into `/var/www/html/wp-content/`.

---

## 3. WordPress & ACF Free Technical Architecture (Stage 1)

### 3.1 Child Theme Compatibility with Twenty Twenty-Five
`twentytwentyfive` is a Full Site Editing (FSE) block theme that stores HTML templates in `templates/page.html` and omits classic `header.php` / `footer.php` files.
- In WordPress 6.8.2, assigning a custom PHP template (`_wp_page_template = page-novatech.php`) gives `ai-hub-theme/page-novatech.php` higher specificity in `locate_block_template()` than the parent theme's generic `templates/page.html`.
- Because `twentytwentyfive` does not provide `header.php` or `footer.php`, `page-novatech.php` renders a complete HTML5 document (`<!DOCTYPE html>`, `wp_head()`, `wp_body_open()`, `wp_footer()`) and includes modular header/footer parts via `get_template_part( 'template-parts/novatech', 'header' )` and `get_template_part( 'template-parts/novatech', 'footer' )`.

### 3.2 Modeling Repeatable Sections in ACF Free (`Group` vs `Repeater`)
- **Constraint:** ACF Free 6.4.3 does not include the Pro `Repeater` or `Flexible Content` field types.
- **Solution in WordPress:** Repeatable UI items (5 navigation links, 3 hero stats, 6 service cards, 5 case study slides, 4 testimonials, 4 delivery phases, and 5 FAQ items) are modeled as nested `Group` fields (`card_1..card_6`, `slide_1..slide_5`, etc.) with an `enabled` boolean toggle.
- **Documented Limitation:** Editors can modify any item or toggle its visibility in WP Admin without touching code, but cannot add a 7th service card or 6th case study slide without adding a new slot to the ACF field group.
- **Normalization for Astro Migration:** Both the PHP helper `nt_normalize_acf_list()` and the upcoming Astro data loader convert these keyed sub-groups (`card_1..card_N`) into standard, variable-length arrays (`Array<ServiceCard>`, `Array<CaseSlide>`, etc.), removing the fixed-slot limitation in Astro.

### 3.3 Single Source of Truth for ACF Schema
To avoid duplication between PHP registration, the WordPress database, and `acf-json/`:
- The field group (`group_novatech_landing`) is imported into the database via `acf_import_field_group()` inside the idempotent `seed-novatech.php` script (matching any existing post ID by group key).
- ACF's native `ACF_Local_JSON` listener automatically writes and syncs `wordpress/themes/ai-hub-theme/acf-json/group_novatech_landing.json`.
- `acf_add_local_field_group()` is intentionally **not** called on every request in `functions.php`, ensuring the field group remains editable in **WP Admin → ACF → Field Groups** without duplicate entries.

---

## 4. Phased Migration Roadmap

- **Stage 0 (Completed):** Repository initialization, `.gitignore` hardening, Docker Compose configuration, and private GitHub synchronization.
- **Stage 1 (Completed):** Full implementation and verification of the **NovaTech Solutions** WordPress baseline (`http://localhost:8001/novatech-solutions/`) — see [`02-wordpress-acf-baseline.md`](02-wordpress-acf-baseline.md).
- **Stage 2 (Completed):** Initialized `astro-lab/`, exported the ACF REST API payload into a Zod-validated JSON schema (`src/content/novatech.json`), and migrated the layout, responsive CSS, Swiper carousels, accessible accordions, and Mailpit-backed contact form — see [`03-astro-migration.md`](03-astro-migration.md).
- **Stage 3 (Completed):** Evidence-based side-by-side comparison of DOM weight, HTTP/JavaScript/CSS payload, visual parity, accessibility, and lab Core Web Vitals between WordPress and Astro — see [`04-wordpress-vs-astro-comparison.md`](04-wordpress-vs-astro-comparison.md).
