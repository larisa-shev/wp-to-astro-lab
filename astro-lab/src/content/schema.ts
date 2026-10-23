/**
 * Zod schema for the migrated NovaTech landing-page content (`src/content/novatech.json`).
 *
 * The JSON file is produced by `scripts/export-content.mjs` from the WordPress ACF REST response and
 * is validated both by that script and again at build time (see `src/content/index.ts`).
 * Optional ACF fields are modelled explicitly instead of being silently defaulted in templates.
 */
import { z } from 'zod';

const text = z.string();
const optionalText = z.string().optional();

export const linkSchema = z.object({ text: text.min(1), url: text.min(1) });

export const imageSchema = z.object({
  src: text.startsWith('/images/'),
  alt: text,
  width: z.number().int().positive(),
  height: z.number().int().positive(),
});

const sectionHead = {
  tag: optionalText,
  title: optionalText,
  subtitle: optionalText,
};

export const iconKeySchema = z.enum(['cloud', 'data', 'platform', 'security', 'ai', 'modernization']);

export const contentSchema = z.object({
  meta: z.object({
    pageTitle: text.min(1),
    lang: text.min(1),
    sourcePageId: z.number().int(),
    sourceModified: text,
  }),
  header: z.object({
    logoText: text.min(1),
    logoBadge: optionalText,
    announcement: optionalText,
    cta: linkSchema.optional(),
    nav: z.array(z.object({ label: text.min(1), url: text.min(1) })).min(1),
  }),
  hero: z.object({
    eyebrow: optionalText,
    title: text.min(1),
    subtitle: optionalText,
    primaryCta: linkSchema.optional(),
    secondaryCta: linkSchema.optional(),
    image: imageSchema.optional(),
    caption: optionalText,
    stats: z.array(z.object({ value: text.min(1), label: text, detail: optionalText })),
  }),
  services: z.object({
    ...sectionHead,
    cards: z
      .array(
        z.object({
          icon: iconKeySchema,
          badge: optionalText,
          title: text.min(1),
          description: optionalText,
          deliverable: optionalText,
          link: linkSchema.optional(),
        }),
      )
      .min(1),
  }),
  cases: z.object({
    ...sectionHead,
    disclaimer: optionalText,
    slides: z
      .array(
        z.object({
          category: optionalText,
          clientName: optionalText,
          title: text.min(1),
          summary: optionalText,
          metricValue: optionalText,
          metricLabel: optionalText,
          secondaryMetric: optionalText,
          techStack: optionalText,
          image: imageSchema.optional(),
        }),
      )
      .min(1),
  }),
  testimonials: z.object({
    ...sectionHead,
    autoplayDelay: z.number().int().min(1000),
    slides: z
      .array(
        z.object({
          quote: text.min(1),
          authorName: text.min(1),
          authorRole: text,
          company: optionalText,
          engagementScope: optionalText,
          rating: z.number().int().min(1).max(5),
          avatar: imageSchema.optional(),
        }),
      )
      .min(1),
  }),
  process: z.object({
    ...sectionHead,
    allowMultiple: z.boolean(),
    steps: z
      .array(
        z.object({
          number: text,
          title: text.min(1),
          duration: optionalText,
          summary: optionalText,
          deliverables: z.array(text),
          governanceNote: optionalText,
          defaultOpen: z.boolean(),
        }),
      )
      .min(1),
  }),
  faq: z.object({
    ...sectionHead,
    items: z
      .array(
        z.object({
          badge: optionalText,
          question: text.min(1),
          answer: text.min(1),
          defaultOpen: z.boolean(),
        }),
      )
      .min(1),
  }),
  contact: z.object({
    tag: optionalText,
    title: optionalText,
    subtitle: optionalText,
    slaNote: optionalText,
    email: optionalText,
    phone: optionalText,
    address: optionalText,
    officeHours: optionalText,
  }),
  footer: z.object({
    description: optionalText,
    copyright: optionalText,
    demoNotice: optionalText,
  }),
});

export type NovatechContent = z.infer<typeof contentSchema>;
export type ImageRef = z.infer<typeof imageSchema>;
