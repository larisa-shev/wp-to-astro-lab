import { defineConfig } from 'astro/config';

// Fully static output. Locally the contact form posts to server/form-server.mjs;
// on GitHub Pages (PUBLIC_STATIC_DEMO=true, BASE_PATH=/wp-to-astro-lab) it runs client-side only.
export default defineConfig({
  site: process.env.SITE_URL || undefined,
  base: process.env.BASE_PATH || '/',
  output: 'static',
  server: { host: '127.0.0.1', port: 4321 },
  devToolbar: { enabled: false },
});
