import { defineConfig } from 'astro/config';

// Fully static output. The contact form posts to a separate backend (see server/form-server.mjs).
export default defineConfig({
  output: 'static',
  server: { host: '127.0.0.1', port: 4321 },
  devToolbar: { enabled: false },
});
