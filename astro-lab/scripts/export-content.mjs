/**
 * ACF -> Astro content exporter.
 *
 * Reads the populated NovaTech page from the WordPress REST API (migration-time input only),
 * flattens ACF Free's fixed sub-groups (card_1..card_n etc.) into ordered arrays, drops disabled
 * items, downloads referenced media into `public/images/`, rewrites media URLs to local paths and
 * writes a schema-validated `src/content/novatech.json`.
 *
 * The generated site never talks to WordPress at runtime.
 *
 * Usage:  npm run export:content            (WP_BASE_URL defaults to http://localhost:8001)
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { basename, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { contentSchema } from '../src/content/schema.ts';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const WP_BASE = (process.env.WP_BASE_URL || 'http://localhost:8001').replace(/\/$/, '');
const SLUG = process.env.WP_PAGE_SLUG || 'novatech-solutions';
const IMAGE_DIR = join(ROOT, 'public', 'images');
const OUT_FILE = join(ROOT, 'src', 'content', 'novatech.json');

const warnings = [];
const warn = (msg) => {
  warnings.push(msg);
  console.warn(`[warn] ${msg}`);
};

/** Trim; return undefined for empty / missing values so optional fields stay explicit. */
const str = (v) => {
  if (v === undefined || v === null || v === false) return undefined;
  const s = String(v).trim();
  return s === '' ? undefined : s;
};
const bool = (v, dflt = false) => (v === undefined || v === null || v === '' ? dflt : v === true || v === 1 || v === '1' || v === 'true');

/** ACF Free fixed sub-groups arrive as {card_1:{..}, card_2:{..}}; WP's own helper treats arrays/objects alike. */
function list(container, { checkEnabled = true } = {}) {
  if (!container || typeof container !== 'object') return [];
  const items = Array.isArray(container) ? container : Object.values(container);
  return items.filter((it) => it && typeof it === 'object' && !(checkEnabled && 'enabled' in it && !bool(it.enabled)));
}

function link(text, url, fallbackUrl) {
  const t = str(text);
  if (!t) return undefined;
  return { text: t, url: str(url) ?? fallbackUrl };
}

const downloaded = new Map();
async function image(field, fallbackAlt, what) {
  if (!field) return undefined;
  if (typeof field !== 'object' || !field.url) {
    warn(`${what}: expected expanded image object, got ${JSON.stringify(field)} - skipped`);
    return undefined;
  }
  const url = new URL(field.url);
  const fileName = basename(url.pathname);
  if (!downloaded.has(fileName)) {
    const res = await fetch(field.url);
    if (!res.ok) throw new Error(`Failed to download ${field.url}: HTTP ${res.status}`);
    await mkdir(IMAGE_DIR, { recursive: true });
    await writeFile(join(IMAGE_DIR, fileName), Buffer.from(await res.arrayBuffer()));
    downloaded.set(fileName, true);
  }
  const width = Number(field.width) || 800;
  const height = Number(field.height) || 500;
  if (!field.width || !field.height) warn(`${what}: missing width/height, using ${width}x${height} (same fallback as the WP theme)`);
  return { src: `/images/${fileName}`, alt: str(field.alt) ?? fallbackAlt ?? '', width, height };
}

async function main() {
  const apiUrl = `${WP_BASE}/wp-json/wp/v2/pages?slug=${encodeURIComponent(SLUG)}`;
  const res = await fetch(apiUrl);
  if (!res.ok) throw new Error(`REST request failed: ${res.status} ${apiUrl}`);
  const pages = await res.json();
  if (!Array.isArray(pages) || pages.length !== 1) throw new Error(`Expected exactly 1 page for slug "${SLUG}", got ${pages.length}`);
  const page = pages[0];
  const acf = page.acf;
  if (!acf || Array.isArray(acf)) throw new Error('Page has no ACF payload (acf is empty). Is ACF Free active and show_in_rest enabled?');

  // <title> is produced by WordPress ("{page title} – {site name}"); read it from the rendered HTML head.
  const html = await (await fetch(page.link)).text();
  const titleMatch = html.match(/<title>([\s\S]*?)<\/title>/i);
  const decode = (s) => s.replace(/&#8211;/g, '\u2013').replace(/&amp;/g, '&').replace(/&#038;/g, '&');
  const pageTitle = titleMatch ? decode(titleMatch[1].trim()) : str(page.title?.rendered) ?? SLUG;
  const lang = (html.match(/<html[^>]*\blang="([^"]+)"/i) || [])[1] || 'en-US';

  const h = acf.header_settings ?? {};
  const hero = acf.hero_section ?? {};
  const svc = acf.services_section ?? {};
  const cases = acf.cases_slider_section ?? {};
  const tes = acf.testimonials_section ?? {};
  const proc = acf.process_section ?? {};
  const faq = acf.faq_section ?? {};
  const contact = acf.contact_footer_section ?? {};

  const content = {
    meta: { pageTitle, lang, sourcePageId: page.id, sourceModified: page.modified_gmt ?? page.modified ?? '' },
    header: {
      logoText: str(h.logo_text),
      logoBadge: str(h.logo_badge),
      announcement: str(h.announcement_text),
      cta: link(h.cta_label, h.cta_url, '#contact'),
      nav: list(h.nav_items)
        .filter((n) => str(n.label))
        .map((n) => ({ label: str(n.label), url: str(n.url) ?? '#' })),
    },
    hero: {
      eyebrow: str(hero.eyebrow_badge),
      title: str(hero.title) ?? str(page.title?.rendered),
      subtitle: str(hero.subtitle),
      primaryCta: link(hero.primary_cta_text, hero.primary_cta_url, '#contact'),
      secondaryCta: link(hero.secondary_cta_text, hero.secondary_cta_url, '#cases'),
      image: await image(hero.hero_image, 'NovaTech platform architecture diagram', 'hero.hero_image'),
      caption: str(hero.architecture_caption),
      stats: list(hero.stats, { checkEnabled: false })
        .filter((s) => str(s.value))
        .map((s) => ({ value: str(s.value), label: str(s.label) ?? '', detail: str(s.detail) })),
    },
    services: {
      tag: str(svc.section_tag),
      title: str(svc.section_title),
      subtitle: str(svc.section_subtitle),
      cards: list(svc.cards).map((c) => ({
        icon: str(c.icon_key) ?? 'cloud',
        badge: str(c.badge),
        title: str(c.title),
        description: str(c.description),
        deliverable: str(c.deliverable_highlight),
        link: link(c.link_text, c.link_url, '#contact'),
      })),
    },
    cases: {
      tag: str(cases.section_tag),
      title: str(cases.section_title),
      subtitle: str(cases.section_subtitle),
      disclaimer: str(cases.demo_disclaimer),
      slides: await Promise.all(
        list(cases.slides).map(async (s, i) => ({
          category: str(s.category),
          clientName: str(s.client_name),
          title: str(s.title),
          summary: str(s.summary),
          metricValue: str(s.metric_value),
          metricLabel: str(s.metric_label),
          secondaryMetric: str(s.secondary_metric),
          techStack: str(s.tech_stack),
          image: await image(s.image, str(s.title), `cases.slides[${i}].image`),
        })),
      ),
    },
    testimonials: {
      tag: str(tes.section_tag),
      title: str(tes.section_title),
      subtitle: str(tes.section_subtitle),
      autoplayDelay: Number(tes.autoplay_delay) > 0 ? Number(tes.autoplay_delay) : 6000,
      slides: await Promise.all(
        list(tes.slides).map(async (t, i) => ({
          quote: str(t.quote),
          authorName: str(t.author_name),
          authorRole: str(t.author_role) ?? '',
          company: str(t.company),
          engagementScope: str(t.engagement_scope),
          rating: Math.max(1, Math.min(5, parseInt(t.rating, 10) || 5)),
          avatar: await image(t.avatar, str(t.author_name), `testimonials.slides[${i}].avatar`),
        })),
      ),
    },
    process: {
      tag: str(proc.section_tag),
      title: str(proc.section_title),
      subtitle: str(proc.section_subtitle),
      allowMultiple: bool(proc.allow_multiple, true),
      steps: list(proc.steps).map((s) => ({
        number: str(s.step_number) ?? '',
        title: str(s.title),
        duration: str(s.duration_badge),
        summary: str(s.summary),
        // ACF textarea: one deliverable per line (same split as the PHP template)
        deliverables: String(s.deliverables ?? '')
          .split('\n')
          .map((l) => l.trim())
          .filter(Boolean),
        governanceNote: str(s.governance_note),
        defaultOpen: bool(s.default_open),
      })),
    },
    faq: {
      tag: str(faq.section_tag),
      title: str(faq.section_title),
      subtitle: str(faq.section_subtitle),
      items: list(faq.items).map((f) => ({
        badge: str(f.category_badge),
        question: str(f.question),
        answer: str(f.answer),
        defaultOpen: bool(f.default_open),
      })),
    },
    contact: {
      tag: str(contact.contact_tag),
      title: str(contact.contact_title),
      subtitle: str(contact.contact_subtitle),
      slaNote: str(contact.response_sla_note),
      email: str(contact.company_email),
      phone: str(contact.company_phone),
      address: str(contact.company_address),
      officeHours: str(contact.office_hours),
    },
    footer: {
      description: str(contact.footer_description),
      copyright: str(contact.copyright_text),
      demoNotice: str(contact.demo_environment_notice),
    },
  };
  // NOTE: contact_footer_section.cf7_shortcode is intentionally NOT exported - CF7 is replaced by the Astro form + local backend.

  const parsed = contentSchema.safeParse(content);
  if (!parsed.success) {
    console.error(JSON.stringify(parsed.error.issues, null, 2));
    throw new Error('Exported content failed schema validation; nothing written.');
  }
  await mkdir(dirname(OUT_FILE), { recursive: true });
  await writeFile(OUT_FILE, JSON.stringify(parsed.data, null, 2) + '\n', 'utf8');

  const c = parsed.data;
  console.log(
    `Exported ${OUT_FILE}\n  nav=${c.header.nav.length} stats=${c.hero.stats.length} services=${c.services.cards.length} ` +
      `cases=${c.cases.slides.length} testimonials=${c.testimonials.slides.length} process=${c.process.steps.length} faq=${c.faq.items.length} ` +
      `images=${downloaded.size} warnings=${warnings.length}`,
  );
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
