/**
 * Small standalone backend for the Astro contact form (replaces Contact Form 7).
 *
 *   POST /api/contact   JSON (or urlencoded) body -> validates -> delivers to Mailpit
 *   GET  /api/health    liveness probe
 *
 * Config (env, see .env.example): PORT, HOST, MAILPIT_URL, MAIL_FROM, MAIL_TO, ALLOWED_ORIGINS.
 * No dependencies beyond Node's standard library.
 */
import http from 'node:http';
import { pathToFileURL } from 'node:url';
import { createMailer } from './mailer.mjs';
import { validateSubmission } from './validate.mjs';

const MAX_BODY_BYTES = 16 * 1024;
const RATE_LIMIT = { windowMs: 60_000, max: 10 };

export function loadConfig(env = process.env) {
  return {
    host: env.FORM_HOST || '127.0.0.1',
    port: Number(env.FORM_PORT || 4322),
    mailpitUrl: env.MAILPIT_URL || 'http://127.0.0.1:8025',
    from: env.MAIL_FROM || 'no-reply@astro-lab.local',
    to: env.MAIL_TO || 'architecture-desk@novatech.local',
    allowedOrigins: (env.ALLOWED_ORIGINS || 'http://127.0.0.1:4321,http://localhost:4321')
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean),
  };
}

class HttpError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let size = 0;
    const chunks = [];
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > MAX_BODY_BYTES) {
        reject(new HttpError(413, 'Request body too large.'));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}

function parseBody(raw, contentType = '') {
  if (contentType.includes('application/json')) {
    try {
      return JSON.parse(raw);
    } catch {
      throw new HttpError(400, 'Malformed JSON body.');
    }
  }
  if (contentType.includes('application/x-www-form-urlencoded')) return Object.fromEntries(new URLSearchParams(raw));
  throw new HttpError(415, 'Unsupported content type.');
}

export function createFormServer(config = loadConfig(), { mailer, logger = console } = {}) {
  const deliver = mailer ?? createMailer({ apiUrl: config.mailpitUrl, from: config.from, to: config.to });
  const hits = new Map();

  const rateLimited = (ip) => {
    const now = Date.now();
    const recent = (hits.get(ip) ?? []).filter((t) => now - t < RATE_LIMIT.windowMs);
    recent.push(now);
    hits.set(ip, recent);
    return recent.length > RATE_LIMIT.max;
  };

  return http.createServer(async (req, res) => {
    const origin = req.headers.origin;
    const corsHeaders = {};
    if (origin && config.allowedOrigins.includes(origin)) {
      corsHeaders['Access-Control-Allow-Origin'] = origin;
      corsHeaders.Vary = 'Origin';
    }
    const send = (status, payload) => {
      res.writeHead(status, {
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff',
        ...corsHeaders,
      });
      res.end(JSON.stringify(payload));
    };

    try {
      const { pathname } = new URL(req.url ?? '/', 'http://localhost');

      if (req.method === 'OPTIONS' && pathname === '/api/contact') {
        res.writeHead(204, {
          ...corsHeaders,
          'Access-Control-Allow-Methods': 'POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, Accept',
          'Access-Control-Max-Age': '600',
        });
        return res.end();
      }
      if (req.method === 'GET' && pathname === '/api/health') return send(200, { ok: true });
      if (pathname !== '/api/contact') return send(404, { ok: false, message: 'Not found.' });
      if (req.method !== 'POST') {
        res.setHeader('Allow', 'POST, OPTIONS');
        return send(405, { ok: false, message: 'Method not allowed.' });
      }
      // Reject cross-site browser posts from origins that are not explicitly allowed.
      if (origin && !config.allowedOrigins.includes(origin)) return send(403, { ok: false, message: 'Origin not allowed.' });
      if (rateLimited(req.socket.remoteAddress ?? 'unknown')) return send(429, { ok: false, message: 'Too many requests. Please try again in a minute.' });

      const body = parseBody(await readBody(req), req.headers['content-type']);
      const result = validateSubmission(body);
      if (!result.ok) return send(422, { ok: false, errors: result.errors });

      if (result.honeypot) {
        // Bots only: silently drop. Humans never see or fill this field.
        logger.warn('[form] honeypot field filled - submission dropped');
        return send(200, { ok: true, message: 'Thank you. Your technical inquiry has been delivered.' });
      }

      try {
        const { id } = await deliver.send(result.data);
        logger.info(`[form] delivered to Mailpit (id=${id})`);
        return send(200, {
          ok: true,
          message: 'Thank you. Your technical inquiry has been delivered to our local architecture desk (verified in Mailpit).',
        });
      } catch (err) {
        logger.error(`[form] delivery failed: ${err.message}`);
        return send(502, {
          ok: false,
          message: 'We could not deliver your message due to a server transport error. Please verify that the local Mailpit service is running.',
        });
      }
    } catch (err) {
      if (err instanceof HttpError) return send(err.status, { ok: false, message: err.message });
      logger.error(`[form] unexpected error: ${err.stack ?? err}`);
      return send(500, { ok: false, message: 'Unexpected server error.' });
    }
  });
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const config = loadConfig();
  createFormServer(config).listen(config.port, config.host, () => {
    console.log(`Form backend listening on http://${config.host}:${config.port} -> Mailpit ${config.mailpitUrl}`);
  });
}
