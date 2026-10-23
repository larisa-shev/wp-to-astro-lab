import raw from './novatech.json';
import { contentSchema } from './schema';

/** Build-time validated landing-page content (generated from WordPress ACF by `npm run export:content`). */
export const content = contentSchema.parse(raw);
