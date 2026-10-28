# WP to Astro Lab (`wp-to-astro-lab`)

An engineering reference repository for practicing and verifying the migration of a production-style corporate website from **WordPress (ACF Free + Contact Form 7 + Swiper + Custom Child Theme)** to **Astro**.

This repository follows a **Clean Reproducible Lab** architecture: only custom theme source code, versioned ACF JSON field schemas (`acf-json/`), MU-plugins, idempotent provisioning scripts, the `astro-lab/` codebase, and technical documentation are tracked in Git. WordPress Core, third-party plugins, database volumes, and secrets are never committed.

---

## Repository Structure

```text
wp-to-astro-lab/
├── .gitignore                        # Excludes .env, wp-config.php, WP Core, plugins, uploads, DB dumps, and build outputs
├── README.md                         # Project overview, architecture, and reproducible setup guide
├── wordpress/                        # Isolated Docker environment for the WordPress baseline (Port 8001)
│   ├── docker-compose.yml            # WordPress 6.8.2 + MariaDB 11.4.5 + WP-CLI 2.11.0 + Mailpit v1.22.3
│   ├── .env.example                  # Safe environment variable template (no real credentials)
│   ├── mu-plugins/
│   │   └── novatech-mailpit-smtp.php # Routes wp_mail() to internal Mailpit SMTP & expands ACF images in REST API
│   ├── themes/
│   │   └── ai-hub-theme/             # Child theme for Twenty Twenty-Five powering NovaTech Solutions
│   │       ├── style.css             # Child theme metadata
│   │       ├── functions.php         # Asset enqueuing, ACF Free list normalizer, and SVG icon helpers
│   │       ├── page-novatech.php     # Corporate landing page template (100% ACF-driven)
│   │       ├── template-parts/       # Modular header and footer templates
│   │       │   ├── novatech-header.php
│   │       │   └── novatech-footer.php
│   │       ├── acf-json/             # Synced ACF Free schema (group_novatech_landing.json, show_in_rest: 1)
│   │       └── assets/
│   │           ├── css/novatech.css  # Responsive B2B design system (375px / 768px / 1440px+)
│   │           ├── js/novatech.js    # Vanilla JS modules (Mobile Menu, Swiper #1 & #2, Accordions)
│   │           └── vendor/swiper/    # Local Swiper v11.2.6 bundle (CSS + JS)
│   └── scripts/
│       ├── setup-wp.ps1              # Idempotent one-command setup for Windows PowerShell
│       ├── setup-wp.sh               # Idempotent one-command setup for Linux / macOS
│       ├── seed-novatech.php         # WP-CLI seed script (ACF schema, GD PNG media, CF7 form, page content)
│       └── verify-stage1.ps1         # Automated end-to-end verification suite
├── astro-lab/                        # Migrated Astro 6.4.8 static implementation (Stage 2)
│   ├── scripts/export-content.mjs    # ACF REST API → Zod-validated JSON + media exporter
│   ├── server/                       # Standalone Node contact-form backend delivering to Mailpit
│   ├── src/                          # Astro components, BaseLayout, Zod schema, novatech.json, CSS & TS
│   └── tests/                        # E2E suite (e2e.test.mjs), visual diff (visual-compare.mjs), Stage 3 harness (stage3-measure.mjs)
└── docs/                             # Engineering documentation
    ├── 01-architecture-and-plan.md   # System architecture, security boundaries, and migration roadmap
    ├── 02-wordpress-acf-baseline.md  # Stage 1 baseline: ACF schema, REST API contract, and test results
    ├── 03-astro-migration.md         # Stage 2 migration: Astro architecture, CF7 replacement, and E2E/visual verification
    ├── 04-wordpress-vs-astro-comparison.md # Stage 3 comparison: payload, DOM, lab CWV, accessibility, and trade-offs
    └── artifacts/stage3/             # Raw Stage 3 measurement datasets (measurements-gzip.json, measurements.json)
```

---

## Key Architectural & Security Decisions

1. **Pinned Container Versions (No `latest` tags):**
   - `wordpress:6.8.2-php8.2-apache` bound locally to `127.0.0.1:8001` (avoids port collisions with port `8000`).
   - `mariadb:11.4.5` (LTS release with container healthcheck; no host port published).
   - `wordpress:cli-2.11.0-php8.2` (on-demand `cli` profile for deterministic setup and seeding).
   - `axllent/mailpit:v1.22.3` (Web UI bound to `127.0.0.1:8025`; SMTP port `1025` is internal to the `wp_lab_net` bridge network).
2. **Zero Hardcoded Content:**
   - The **NovaTech Solutions** landing page (`http://localhost:8001/novatech-solutions/`) reads 100% of its copy, navigation links, metrics, service cards, case study slides, testimonials, process phases, FAQs, and contact details from **Advanced Custom Fields (ACF Free 6.4.3)**.
3. **Real Contact Form 7 + Mailpit Delivery:**
   - Uses the official `contact-form-7` plugin with client/server validation and delivers submissions over SMTP to the internal `mailpit` container.

---

## Quick Start (Reproducible Setup)

### Windows (PowerShell)
```powershell
powershell -ExecutionPolicy Bypass -File .\wordpress\scripts\setup-wp.ps1
```

### Linux / macOS (Bash)
```bash
chmod +x ./wordpress/scripts/setup-wp.sh
./wordpress/scripts/setup-wp.sh
```

### Service Endpoints
- **NovaTech Solutions Landing Page (WordPress):** `http://localhost:8001/novatech-solutions/`
- **WordPress Admin Dashboard:** `http://localhost:8001/wp-admin/` (credentials defined in `wordpress/.env`)
- **ACF REST API Endpoint:** `http://localhost:8001/wp-json/wp/v2/pages?slug=novatech-solutions&acf_format=standard`
- **NovaTech Solutions Landing Page (Astro Dev / Preview):** `http://127.0.0.1:4321/`
- **Astro Contact Form Backend API:** `http://127.0.0.1:4322/api/contact`
- **Mailpit Web UI (Captured Emails):** `http://127.0.0.1:8025`

### Running the Automated Verification Suite
```powershell
# Stage 1: WordPress baseline verification (59 checks)
powershell -ExecutionPolicy Bypass -File .\wordpress\scripts\verify-stage1.ps1

# Stage 2: Astro typecheck, unit tests (16), E2E browser tests (19), and visual diff
cd .\astro-lab
npm.cmd run check
npm.cmd run test:unit
npm.cmd run test:e2e
npm.cmd run compare

# Stage 3: Evidence-based WordPress vs Astro performance/DOM/a11y measurements
$env:COMPRESS='gzip'; node .\tests\stage3-measure.mjs
```

---

## Stage 3 Summary: WordPress vs Astro Comparison

Full details, methodology, and limitations are documented in [`docs/04-wordpress-vs-astro-comparison.md`](docs/04-wordpress-vs-astro-comparison.md):

| Metric (Initial Cold Load) | WordPress Baseline (`:8001`) | Astro Build (`dist/`) | Delta (Astro vs WP) |
| :--- | :---: | :---: | :---: |
| **HTTP Requests (Desktop / Mobile)** | `14` / `15` | `4` / `4` | **`-71.4%` / `-73.3%`** |
| **Total Payload (Raw / Gzip / Brotli)** | `384.6 KB` / `103.6 KB` / `89.3 KB` | `198.5 KB` / `55.1 KB` / `48.2 KB` | **`-48.4%` raw / `-46.8%` gzip** |
| **JavaScript Payload (Raw / Gzip)** | `222.4 KB` / `63.8 KB` (`7` files) | `91.9 KB` / `28.1 KB` (`1` module) | **`-58.7%` raw / `-55.9%` gzip** |
| **External CSS Payload (Raw / Gzip)** | `53.2 KB` / `12.7 KB` (`4` files) | `37.6 KB` / `7.5 KB` (`1` file) | **`-29.2%` raw / `-40.9%` gzip** |
| **DOM Elements (`maxDepth: 13`)** | `709` (desktop) / `711` (mobile) | `673` (desktop) / `675` (mobile) | **`-36` elements (`-5.1%`)** |
| **Desktop TTFB / FCP / LCP (Median)** | `176.7 ms` / `332.0 ms` / `332.0 ms` | `14.3 ms` / `164.0 ms` / `168.0 ms` | **`-91.9%` TTFB / `-50.6%` FCP** |
| **Mobile Throttled TTFB / FCP / Load (Gzip)** | `152.0 ms` / `960.0 ms` / `1184.9 ms` | `12.7 ms` / `920.0 ms` / `977.7 ms` | **`-91.6%` TTFB / `-17.5%` Load** |
| **Visual Parity (Computed Style Diffs)** | Baseline (`375px` / `768px` / `1440px`) | `0` style diffs (`2.47%–4.83%` pixel diff) | **100% computed style parity** |
