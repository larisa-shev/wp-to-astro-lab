/**
 * Progressive-enhancement handler for the contact form.
 *
 * Locally, posts to the form backend (`server/form-server.mjs`) via fetch and renders its validation
 * errors / status inside the same markup CF7 used (`wpcf7-not-valid-tip`, `wpcf7-response-output`,
 * form status classes `sent` / `invalid` / `failed`). Success is only shown when the backend
 * answers `{ ok: true }`, i.e. after the message was handed to Mailpit.
 *
 * On the public static demo (`data-static-demo="true"`, e.g. GitHub Pages), validates inputs
 * client-side using the shared validation rules and displays a transparent static-demo notice
 * without making any network requests.
 */
import { validateSubmission } from '../../server/validate.mjs';

const MESSAGES = {
  invalid: 'One or more fields require attention. Please review the highlighted inputs below.',
  failed: 'We could not deliver your message due to a server transport error. Please try again later.',
  network: 'We could not reach the form service. Please make sure the local form backend is running (npm run form:server).',
  staticDemo:
    'Static Demo Mode (GitHub Pages): Client-side validation passed. No message was sent because the Node.js + Mailpit backend runs only in the local lab environment.',
};

type FieldErrors = Record<string, string>;

const form = document.querySelector<HTMLFormElement>('[data-contact-form]');

if (form) {
  const isStaticDemo = form.dataset.staticDemo === 'true';
  const endpoint = form.dataset.endpoint ?? form.action;
  const output = form.querySelector<HTMLElement>('.wpcf7-response-output')!;
  const live = document.querySelector<HTMLElement>('[data-form-live]');
  const submit = form.querySelector<HTMLInputElement>('input[type="submit"]')!;

  const clearErrors = () => {
    form.querySelectorAll('.wpcf7-not-valid-tip').forEach((el) => el.remove());
    form.querySelectorAll('.wpcf7-not-valid').forEach((el) => {
      el.classList.remove('wpcf7-not-valid');
      el.setAttribute('aria-invalid', 'false');
      el.removeAttribute('aria-describedby');
    });
  };

  const showErrors = (errors: FieldErrors) => {
    for (const [name, message] of Object.entries(errors)) {
      const control = form.querySelector<HTMLElement>(`[name="${CSS.escape(name)}"]`);
      const wrap = control?.closest('.wpcf7-form-control-wrap');
      if (!control || !wrap) continue;
      const tipId = `${control.id}-error`;
      control.classList.add('wpcf7-not-valid');
      control.setAttribute('aria-invalid', 'true');
      control.setAttribute('aria-describedby', tipId);
      const tip = document.createElement('span');
      tip.className = 'wpcf7-not-valid-tip';
      tip.id = tipId;
      tip.textContent = message;
      wrap.appendChild(tip);
    }
  };

  const setStatus = (status: 'sent' | 'invalid' | 'failed' | 'submitting', message = '') => {
    form.classList.remove('init', 'sent', 'invalid', 'failed', 'submitting');
    form.classList.add(status);
    form.dataset.status = status;
    output.textContent = message;
    output.setAttribute('role', 'alert');
    output.removeAttribute('aria-hidden');
    if (!message) output.setAttribute('aria-hidden', 'true');
    if (live) live.textContent = message;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    setStatus('submitting');
    submit.disabled = true;

    try {
      const body = Object.fromEntries(new FormData(form).entries());

      if (isStaticDemo) {
        const result = validateSubmission(body);
        if (!result.ok) {
          showErrors(result.errors as FieldErrors);
          setStatus('invalid', MESSAGES.invalid);
          form.querySelector<HTMLElement>('.wpcf7-not-valid')?.focus();
        } else {
          form.reset();
          setStatus('sent', MESSAGES.staticDemo);
        }
        return;
      }

      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json().catch(() => ({}));

      if (res.ok && data.ok === true) {
        form.reset();
        setStatus('sent', data.message ?? 'Thank you. Your technical inquiry has been delivered.');
      } else if (res.status === 422 && data.errors) {
        showErrors(data.errors as FieldErrors);
        setStatus('invalid', MESSAGES.invalid);
        form.querySelector<HTMLElement>('.wpcf7-not-valid')?.focus();
      } else {
        setStatus('failed', data.message ?? MESSAGES.failed);
      }
    } catch {
      setStatus('failed', MESSAGES.network);
    } finally {
      submit.disabled = false;
    }
  });
}
