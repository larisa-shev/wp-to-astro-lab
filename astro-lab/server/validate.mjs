/**
 * Server-side validation for the contact form. Mirrors the Contact Form 7 definition used by the
 * WordPress baseline (4 required fields, email format, maxlength 400 / 2000) and its English messages.
 */
export const LIMITS = { 'your-name': 400, 'your-email': 400, 'your-subject': 400, 'your-message': 2000 };

export const MESSAGES = {
  required: 'This field is required.',
  tooLong: 'The entered text exceeds the maximum allowed length.',
  email: 'Please enter a valid work email address.',
};

// Pragmatic address check (same spirit as CF7): local@domain.tld, no whitespace, no control chars.
const EMAIL_RE = /^[^\s@<>()[\]\\,;:"]+@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+$/;

/** Strip control characters (incl. CR/LF) from single-line header-bound values. */
const singleLine = (s) => s.replace(/[\u0000-\u001f\u007f]+/g, ' ').replace(/\s+/g, ' ').trim();
/** Normalise newlines in multi-line text, drop other control characters. */
const multiLine = (s) =>
  s
    .replace(/\r\n?/g, '\n')
    .replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '')
    .trim();

/**
 * @param {unknown} input parsed request body
 * @returns {{ ok: true, data: { name: string, email: string, subject: string, message: string }, honeypot: boolean } | { ok: false, errors: Record<string,string> }}
 */
export function validateSubmission(input) {
  const body = input && typeof input === 'object' ? input : {};
  const errors = {};
  const get = (key) => (typeof body[key] === 'string' ? body[key] : '');

  const values = {
    'your-name': singleLine(get('your-name')),
    'your-email': singleLine(get('your-email')),
    'your-subject': singleLine(get('your-subject')),
    'your-message': multiLine(get('your-message')),
  };

  for (const [key, max] of Object.entries(LIMITS)) {
    const raw = get(key);
    if (values[key] === '') errors[key] = MESSAGES.required;
    else if (raw.length > max) errors[key] = MESSAGES.tooLong;
  }
  if (!errors['your-email'] && !EMAIL_RE.test(values['your-email'])) errors['your-email'] = MESSAGES.email;

  if (Object.keys(errors).length > 0) return { ok: false, errors };

  return {
    ok: true,
    honeypot: get('website').trim() !== '',
    data: {
      name: values['your-name'],
      email: values['your-email'],
      subject: values['your-subject'],
      message: values['your-message'],
    },
  };
}
