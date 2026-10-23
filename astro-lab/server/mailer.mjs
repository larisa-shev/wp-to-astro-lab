/**
 * Mail delivery via the Mailpit HTTP Send API (POST /api/v1/send).
 *
 * Why not SMTP: the baseline's Mailpit container publishes only its web/API port (127.0.0.1:8025);
 * its SMTP port 1025 is internal to the Docker network, and the WordPress compose file must not be
 * modified. The Send API puts a real message into the same Mailpit inbox with no extra dependency.
 * For production, swap this module for an SMTP / transactional-email provider (see docs).
 */
export function createMailer({ apiUrl, from, to, fetchImpl = fetch, timeoutMs = 5000 }) {
  return {
    async send({ name, email, subject, message }) {
      const text = [
        `Name: ${name}`,
        `Email: ${email}`,
        `Subject: ${subject}`,
        '',
        message,
        '',
        '--',
        'This e-mail was sent from the NovaTech Astro lab contact form.',
      ].join('\n');

      const res = await fetchImpl(`${apiUrl.replace(/\/$/, '')}/api/v1/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          From: { Email: from, Name: 'NovaTech Astro Lab' },
          To: [{ Email: to }],
          ReplyTo: [{ Email: email, Name: name }],
          Subject: `[NovaTech Lab Inquiry] ${subject}`,
          Text: text,
          Tags: ['astro-contact-form'],
        }),
        signal: AbortSignal.timeout(timeoutMs),
      });
      if (!res.ok) throw new Error(`Mailpit responded with HTTP ${res.status}`);
      const data = await res.json().catch(() => ({}));
      return { id: data.ID ?? null };
    },
  };
}
