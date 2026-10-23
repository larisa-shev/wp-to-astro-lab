# WP to Astro Lab (`wp-to-astro-lab`)

An engineering reference repository for practicing and verifying the migration of a production-style corporate website from **WordPress (ACF Free + Contact Form 7 + Swiper + Custom Child Theme)** to **Astro**.

This repository follows a **Clean Reproducible Lab** architecture: only custom theme source code, versioned ACF JSON field schemas (`acf-json/`), MU-plugins, idempotent provisioning scripts, the upcoming `astro-lab/` codebase, and technical documentation are tracked in Git. WordPress Core, third-party plugins, database volumes, and secrets are never committed.

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
├── astro-lab/                        # Target Astro implementation (Stage 2)
└── docs/                             # Engineering documentation
    ├── 01-architecture-and-plan.md   # System architecture, security boundaries, and migration roadmap
    └── 02-wordpress-acf-baseline.md  # Stage 1 baseline: ACF schema, REST API contract, and test results
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
- **NovaTech Solutions Landing Page:** `http://localhost:8001/novatech-solutions/`
- **WordPress Admin Dashboard:** `http://localhost:8001/wp-admin/` (credentials defined in `wordpress/.env`)
- **ACF REST API Endpoint:** `http://localhost:8001/wp-json/wp/v2/pages?slug=novatech-solutions&acf_format=standard`
- **Mailpit Web UI (Captured Emails):** `http://127.0.0.1:8025`

### Running the Automated Verification Suite
```powershell
powershell -ExecutionPolicy Bypass -File .\wordpress\scripts\verify-stage1.ps1
```
