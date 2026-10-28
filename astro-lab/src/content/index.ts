import raw from './novatech.json';
import { contentSchema } from './schema';

const base = import.meta.env.BASE_URL.replace(/\/$/, '');
const withBase = <T extends { src: string } | undefined>(img: T): T =>
  img && base && img.src.startsWith('/') ? { ...img, src: `${base}${img.src}` } : img;

const parsed = contentSchema.parse(raw);

/** Build-time validated landing-page content (generated from WordPress ACF by `npm run export:content`). */
export const content = base
  ? {
      ...parsed,
      hero: { ...parsed.hero, image: withBase(parsed.hero.image) },
      cases: {
        ...parsed.cases,
        slides: parsed.cases.slides.map((s) => ({ ...s, image: withBase(s.image) })),
      },
      testimonials: {
        ...parsed.testimonials,
        slides: parsed.testimonials.slides.map((s) => ({ ...s, avatar: withBase(s.avatar) })),
      },
    }
  : parsed;
