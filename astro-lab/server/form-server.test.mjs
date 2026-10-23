import assert from 'node:assert/strict';
import { once } from 'node:events';
import http from 'node:http';
import { after, before, describe, test } from 'node:test';
import { createFormServer, loadConfig } from './form-server.mjs';
import { createMailer } from './mailer.mjs';
import { validateSubmission } from './validate.mjs';

const valid = {
  'your-name': 'Alex Morgan',
  'your-email': 'alex@company.example',
  'your-subject': 'Cloud audit',
  'your-message': 'Hello\nworld',
};

describe('validateSubmission', () => {
  test('accepts a valid submission', () => {
    const r = validateSubmission(valid);
    assert.equal(r.ok, true);
    assert.equal(r.data.email, 'alex@company.example');
    assert.equal(r.honeypot, false);
  });
  test('requires all four fields', () => {
    const r = validateSubmission({});
    assert.equal(r.ok, false);
    assert.deepEqual(Object.keys(r.errors).sort(), ['your-email', 'your-message', 'your-name', 'your-subject']);
  });
  test('rejects invalid email', () => {
    for (const bad of ['nope', 'a@b', 'a b@c.de', 'a@b.c\r\nBcc: x@y.z']) {
      const r = validateSubmission({ ...valid, 'your-email': bad });
      assert.equal(r.ok, false, bad);
      assert.match(r.errors['your-email'], /valid work email/);
    }
  });
  test('enforces max lengths', () => {
    const r = validateSubmission({ ...valid, 'your-message': 'x'.repeat(2001), 'your-name': 'n'.repeat(401) });
    assert.equal(r.ok, false);
    assert.match(r.errors['your-message'], /exceeds/);
    assert.match(r.errors['your-name'], /exceeds/);
  });
  test('strips CR/LF from header-bound fields and ignores non-string values', () => {
    const r = validateSubmission({ ...valid, 'your-subject': 'Hi\r\nBcc: evil@x.y', 'your-name': ['a'] });
    assert.equal(r.ok, false);
    assert.ok(r.errors['your-name']);
    const r2 = validateSubmission({ ...valid, 'your-subject': 'Hi\r\nBcc: evil@x.y' });
    assert.equal(r2.data.subject, 'Hi Bcc: evil@x.y');
  });
  test('flags honeypot', () => {
    assert.equal(validateSubmission({ ...valid, website: 'http://spam' }).honeypot, true);
  });
});

describe('createMailer', () => {
  test('posts a structured message to the Mailpit send API', async () => {
    let captured;
    const mailer = createMailer({
      apiUrl: 'http://mailpit.test/',
      from: 'f@x.test',
      to: 't@x.test',
      fetchImpl: async (url, init) => {
        captured = { url, body: JSON.parse(init.body) };
        return new Response(JSON.stringify({ ID: 'abc' }), { status: 200 });
      },
    });
    const out = await mailer.send({ name: 'A', email: 'a@b.cd', subject: 'S', message: 'M' });
    assert.equal(out.id, 'abc');
    assert.equal(captured.url, 'http://mailpit.test/api/v1/send');
    assert.equal(captured.body.To[0].Email, 't@x.test');
    assert.equal(captured.body.ReplyTo[0].Email, 'a@b.cd');
    assert.equal(captured.body.Subject, '[NovaTech Lab Inquiry] S');
  });
  test('throws when Mailpit rejects', async () => {
    const mailer = createMailer({ apiUrl: 'http://m.test', from: 'f@x.test', to: 't@x.test', fetchImpl: async () => new Response('no', { status: 500 }) });
    await assert.rejects(() => mailer.send({ name: 'A', email: 'a@b.cd', subject: 'S', message: 'M' }), /HTTP 500/);
  });
});

describe('form server HTTP behaviour', () => {
  const sent = [];
  let failNext = false;
  let server;
  let base;
  const silent = { info() {}, warn() {}, error() {} };

  before(async () => {
    const mailer = {
      async send(msg) {
        if (failNext) throw new Error('boom');
        sent.push(msg);
        return { id: `id-${sent.length}` };
      },
    };
    server = createFormServer(loadConfig({ ALLOWED_ORIGINS: 'http://allowed.test' }), { mailer, logger: silent });
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    base = `http://127.0.0.1:${server.address().port}`;
  });
  after(() => server.close());

  const post = (body, headers = {}) =>
    fetch(`${base}/api/contact`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...headers },
      body: typeof body === 'string' ? body : JSON.stringify(body),
    });

  test('health', async () => {
    assert.deepEqual(await (await fetch(`${base}/api/health`)).json(), { ok: true });
  });
  test('422 with field errors, nothing sent', async () => {
    const res = await post({ 'your-name': 'x' });
    assert.equal(res.status, 422);
    assert.ok((await res.json()).errors['your-email']);
    assert.equal(sent.length, 0);
  });
  test('400 on malformed JSON, 415 on wrong content type', async () => {
    assert.equal((await post('{bad')).status, 400);
    assert.equal((await post('x', { 'Content-Type': 'text/plain' })).status, 415);
  });
  test('403 for disallowed browser origin, CORS header for allowed one', async () => {
    assert.equal((await post(valid, { Origin: 'http://evil.test' })).status, 403);
    const ok = await post(valid, { Origin: 'http://allowed.test' });
    assert.equal(ok.status, 200);
    assert.equal(ok.headers.get('access-control-allow-origin'), 'http://allowed.test');
  });
  test('200 delivers exactly one message', async () => {
    const before = sent.length;
    const res = await post(valid);
    assert.equal(res.status, 200);
    assert.equal((await res.json()).ok, true);
    assert.equal(sent.length, before + 1);
  });
  test('honeypot returns 200 but sends nothing', async () => {
    const before = sent.length;
    assert.equal((await post({ ...valid, website: 'spam' })).status, 200);
    assert.equal(sent.length, before);
  });
  test('502 when delivery fails (no fake success)', async () => {
    failNext = true;
    const res = await post(valid);
    failNext = false;
    assert.equal(res.status, 502);
    assert.equal((await res.json()).ok, false);
  });
  test('413 on oversized body, 405 on GET, 404 elsewhere', async () => {
    assert.equal((await post({ ...valid, pad: 'x'.repeat(20000) }).catch(() => ({ status: 413 }))).status, 413);
    assert.equal((await fetch(`${base}/api/contact`)).status, 405);
    assert.equal((await fetch(`${base}/nope`)).status, 404);
  });
});
