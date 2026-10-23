/**
 * Automated Headless Chrome Verification Suite (via CDP + Node.js 22 native WebSocket)
 *
 * Verifies in a real browser engine (Google Chrome Headless):
 * 1. Zero console errors or uncaught page exceptions.
 * 2. Responsive layout & CSS Grid / Swiper breakpoints at 375px, 768px, and 1440px
 *    (including zero horizontal scrollbar overflow).
 * 3. Mobile navigation drawer: open via click, close via Escape key, focus restoration.
 * 4. Swiper #1 (Case Studies): real Swiper instance, next-slide navigation arrow click.
 * 5. Swiper #2 (Testimonials): real Swiper instance, autoplay running state, manual pause/resume button,
 *    and automatic pause under `prefers-reduced-motion: reduce`.
 * 6. Process Accordion: independent multi-panel expansion.
 * 7. FAQ Accordion: single-panel expansion and keyboard arrow navigation.
 */

import { spawn } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const TARGET_URL = 'http://localhost:8001/novatech-solutions/';
const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const CDP_PORT = 9229;

const results = [];
function record(category, name, passed, detail) {
	const status = passed ? 'PASS' : 'FAIL';
	results.push({ category, name, status, detail });
	console.log(`[${status}] ${category} :: ${name} -- ${detail}`);
}

async function main() {
	const userDataDir = `C:\\Users\\loras\\AppData\\Local\\Temp\\nt-chrome-cdp-${Date.now()}`;
	const chromeProc = spawn(
		CHROME_PATH,
		[
			'--headless=new',
			'--disable-gpu',
			'--no-first-run',
			'--no-default-browser-check',
			`--remote-debugging-port=${CDP_PORT}`,
			`--user-data-dir=${userDataDir}`,
			'about:blank',
		],
		{ stdio: 'ignore' }
	);

	try {
		// Wait for CDP endpoint
		let wsUrl = null;
		for (let i = 0; i < 25; i++) {
			try {
				const res = await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`);
				const targets = await res.json();
				const pageTarget = targets.find((t) => t.type === 'page');
				if (pageTarget && pageTarget.webSocketDebuggerUrl) {
					wsUrl = pageTarget.webSocketDebuggerUrl;
					break;
				}
			} catch {
				// retry
			}
			await delay(250);
		}

		if (!wsUrl) {
			throw new Error('Could not connect to Headless Chrome CDP endpoint.');
		}

		const ws = new WebSocket(wsUrl);
		await new Promise((resolve, reject) => {
			ws.addEventListener('open', resolve, { once: true });
			ws.addEventListener('error', reject, { once: true });
		});

		let msgId = 0;
		const pending = new Map();
		const consoleErrors = [];

		ws.addEventListener('message', (event) => {
			const msg = JSON.parse(event.data);
			if (msg.method === 'Runtime.exceptionThrown') {
				consoleErrors.push(msg.params.exceptionDetails?.text || 'Uncaught exception');
			}
			if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') {
				const text = (msg.params.args || []).map((a) => a.value || a.description || '').join(' ');
				consoleErrors.push(text);
			}
			if (msg.id && pending.has(msg.id)) {
				const { resolve, reject } = pending.get(msg.id);
				pending.delete(msg.id);
				if (msg.error) {
					reject(new Error(msg.error.message));
				} else {
					resolve(msg.result);
				}
			}
		});

		const sendCdp = (method, params = {}) =>
			new Promise((resolve, reject) => {
				const id = ++msgId;
				pending.set(id, { resolve, reject });
				ws.send(JSON.stringify({ id, method, params }));
			});

		const evaluate = async (expression) => {
			const res = await sendCdp('Runtime.evaluate', {
				expression,
				returnByValue: true,
				awaitPromise: true,
			});
			if (res.exceptionDetails) {
				throw new Error(res.exceptionDetails.text);
			}
			return res.result.value;
		};

		await sendCdp('Page.enable');
		await sendCdp('Runtime.enable');
		await sendCdp('Emulation.setEmulatedMedia', {
			features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }],
		});

		// Navigate to the NovaTech landing page
		await sendCdp('Page.navigate', { url: TARGET_URL });
		await delay(1500);

		// --------------------------------------------------------------------
		// 1. RESPONSIVE VIEWPORTS & BREAKPOINTS (375px, 768px, 1440px)
		// --------------------------------------------------------------------
		const viewports = [
			{ width: 375, height: 812, label: 'Mobile (375px)', expectedCols: 1, expectedSlides: 1 },
			{ width: 768, height: 1024, label: 'Tablet (768px)', expectedCols: 2, expectedSlides: 2 },
			{ width: 1440, height: 900, label: 'Desktop (1440px)', expectedCols: 3, expectedSlides: 3 },
		];

		for (const vp of viewports) {
			await sendCdp('Emulation.setDeviceMetricsOverride', {
				width: vp.width,
				height: vp.height,
				deviceScaleFactor: 1,
				mobile: vp.width < 768,
			});
			await evaluate('window.dispatchEvent(new Event("resize"))');
			await delay(350);

			const metrics = await evaluate(`(() => {
				const docWidth = document.documentElement.scrollWidth;
				const winWidth = window.innerWidth;
				const grid = document.querySelector('.nt-services__grid');
				const gridCols = grid ? window.getComputedStyle(grid).gridTemplateColumns.split(' ').length : 0;
				const casesEl = document.getElementById('nt-cases-swiper');
				const swiperSlidesPerView = casesEl && casesEl.swiper ? casesEl.swiper.params.slidesPerView : 0;
				const mobileToggleVisible = window.getComputedStyle(document.getElementById('nt-mobile-menu-toggle')).display !== 'none';
				return { docWidth, winWidth, gridCols, swiperSlidesPerView, mobileToggleVisible };
			})()`);

			record(
				'Responsive Layout',
				`${vp.label} zero horizontal overflow`,
				metrics.docWidth <= metrics.winWidth,
				`scrollWidth=${metrics.docWidth}, innerWidth=${metrics.winWidth}`
			);
			record(
				'Responsive Layout',
				`${vp.label} Services CSS Grid columns == ${vp.expectedCols}`,
				metrics.gridCols === vp.expectedCols,
				`columns=${metrics.gridCols}`
			);
			record(
				'Swiper #1 Breakpoints',
				`${vp.label} Cases Swiper slidesPerView == ${vp.expectedSlides}`,
				metrics.swiperSlidesPerView === vp.expectedSlides,
				`slidesPerView=${metrics.swiperSlidesPerView}`
			);
		}

		// --------------------------------------------------------------------
		// 2. MOBILE NAVIGATION DRAWER & KEYBOARD ESCAPE (AT 375px)
		// --------------------------------------------------------------------
		await sendCdp('Emulation.setDeviceMetricsOverride', {
			width: 375,
			height: 812,
			deviceScaleFactor: 1,
			mobile: true,
		});
		await evaluate('window.dispatchEvent(new Event("resize"))');
		await delay(250);

		const mobileNavTest = await evaluate(`(() => {
			const btn = document.getElementById('nt-mobile-menu-toggle');
			const drawer = document.getElementById('nt-mobile-menu-drawer');
			btn.click();
			const openedExpanded = btn.getAttribute('aria-expanded') === 'true';
			const openedVisible = drawer.hidden === false;

			document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
			const closedExpanded = btn.getAttribute('aria-expanded') === 'false';
			const closedHidden = drawer.hidden === true;
			const focusRestored = document.activeElement === btn;

			return { openedExpanded, openedVisible, closedExpanded, closedHidden, focusRestored };
		})()`);

		record(
			'Mobile Menu',
			'Drawer opens on toggle click & closes on Escape key with focus restored',
			mobileNavTest.openedExpanded &&
				mobileNavTest.openedVisible &&
				mobileNavTest.closedExpanded &&
				mobileNavTest.closedHidden &&
				mobileNavTest.focusRestored,
			JSON.stringify(mobileNavTest)
		);

		// Restore Desktop 1440px for remaining interactive tests
		await sendCdp('Emulation.setDeviceMetricsOverride', {
			width: 1440,
			height: 900,
			deviceScaleFactor: 1,
			mobile: false,
		});
		await evaluate('window.dispatchEvent(new Event("resize"))');
		await delay(250);

		// --------------------------------------------------------------------
		// 3. SWIPER #1 (CASES) & SWIPER #2 (TESTIMONIALS) INTERACTION
		// --------------------------------------------------------------------
		const swiperTest = await evaluate(`(() => {
			const casesSwiper = document.getElementById('nt-cases-swiper').swiper;
			const initialCaseIdx = casesSwiper.activeIndex;
			document.getElementById('nt-cases-next').click();
			const nextCaseIdx = casesSwiper.activeIndex;

			const testimSwiper = document.getElementById('nt-testimonials-swiper').swiper;
			const autoplayBtn = document.getElementById('nt-testimonials-autoplay-toggle');
			const initiallyRunning = testimSwiper.autoplay.running;
			const initialPressed = autoplayBtn.getAttribute('aria-pressed');

			// Click pause toggle button
			autoplayBtn.click();
			const afterPauseRunning = testimSwiper.autoplay.running;
			const afterPausePressed = autoplayBtn.getAttribute('aria-pressed');
			const afterPauseLabel = autoplayBtn.innerText.trim();

			// Click again to resume
			autoplayBtn.click();
			const afterResumeRunning = testimSwiper.autoplay.running;

			return {
				initialCaseIdx,
				nextCaseIdx,
				initiallyRunning,
				initialPressed,
				afterPauseRunning,
				afterPausePressed,
				afterPauseLabel,
				afterResumeRunning
			};
		})()`);

		record(
			'Swiper #1 (Cases)',
			'Next arrow advances active slide index',
			swiperTest.initialCaseIdx === 0 && swiperTest.nextCaseIdx === 1,
			`index ${swiperTest.initialCaseIdx} -> ${swiperTest.nextCaseIdx}`
		);
		record(
			'Swiper #2 (Testimonials)',
			'Autoplay runs initially and pauses/resumes via dedicated button',
			swiperTest.initiallyRunning === true &&
				swiperTest.initialPressed === 'false' &&
				swiperTest.afterPauseRunning === false &&
				swiperTest.afterPausePressed === 'true' &&
				swiperTest.afterPauseLabel === 'Resume Autoplay' &&
				swiperTest.afterResumeRunning === true,
			JSON.stringify(swiperTest)
		);

		// --------------------------------------------------------------------
		// 4. ACCORDIONS (PROCESS MULTI-EXPAND & FAQ SINGLE-EXPAND + KEYBOARD)
		// --------------------------------------------------------------------
		const accordionTest = await evaluate(`(() => {
			// Process Accordion: step 1 & 2 are open by default; click step 3 and verify 1, 2, 3 are all open
			const p1 = document.getElementById('nt-proc-trigger-1');
			const p2 = document.getElementById('nt-proc-trigger-2');
			const p3 = document.getElementById('nt-proc-trigger-3');
			p3.click();
			const multiOpenOk =
				p1.getAttribute('aria-expanded') === 'true' &&
				p2.getAttribute('aria-expanded') === 'true' &&
				p3.getAttribute('aria-expanded') === 'true' &&
				!document.getElementById('nt-proc-panel-3').hidden;

			// FAQ Accordion: faq 1 is open by default; click faq 2 and verify faq 1 closes while faq 2 opens
			const f1 = document.getElementById('nt-faq-trigger-1');
			const f2 = document.getElementById('nt-faq-trigger-2');
			f2.click();
			const singleOpenOk =
				f1.getAttribute('aria-expanded') === 'false' &&
				document.getElementById('nt-faq-panel-1').hidden === true &&
				f2.getAttribute('aria-expanded') === 'true' &&
				document.getElementById('nt-faq-panel-2').hidden === false;

			// Keyboard ArrowDown navigation on FAQ triggers
			f1.focus();
			f1.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
			const arrowFocusOk = document.activeElement === f2;

			return { multiOpenOk, singleOpenOk, arrowFocusOk };
		})()`);

		record(
			'Process Accordion',
			'Allows multiple panels open simultaneously',
			accordionTest.multiOpenOk,
			`multiOpenOk=${accordionTest.multiOpenOk}`
		);
		record(
			'FAQ Accordion',
			'Toggles panels cleanly and supports ArrowDown keyboard focus navigation',
			accordionTest.singleOpenOk && accordionTest.arrowFocusOk,
			JSON.stringify(accordionTest)
		);

		// --------------------------------------------------------------------
		// 5. PREFERS-REDUCED-MOTION VERIFICATION
		// --------------------------------------------------------------------
		await sendCdp('Emulation.setEmulatedMedia', {
			features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
		});
		await sendCdp('Page.reload');
		await delay(1200);

		const reducedMotionTest = await evaluate(`(() => {
			const testimSwiper = document.getElementById('nt-testimonials-swiper').swiper;
			const autoplayBtn = document.getElementById('nt-testimonials-autoplay-toggle');
			return {
				matchesMedia: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
				autoplayRunning: testimSwiper.autoplay.running,
				buttonPressed: autoplayBtn.getAttribute('aria-pressed'),
				speed: testimSwiper.params.speed
			};
		})()`);

		record(
			'Accessibility (Reduced Motion)',
			'Swiper #2 disables autoplay & transitions when prefers-reduced-motion: reduce is active',
			reducedMotionTest.matchesMedia === true &&
				reducedMotionTest.autoplayRunning === false &&
				reducedMotionTest.buttonPressed === 'true' &&
				reducedMotionTest.speed === 0,
			JSON.stringify(reducedMotionTest)
		);

		// --------------------------------------------------------------------
		// 6. BROWSER CONSOLE CLEANLINESS
		// --------------------------------------------------------------------
		record(
			'Browser Console',
			'Zero console.error or uncaught JS exceptions',
			consoleErrors.length === 0,
			consoleErrors.length === 0 ? '0 errors' : consoleErrors.join('; ')
		);

		ws.close();
	} finally {
		chromeProc.kill();
	}

	const failed = results.filter((r) => r.status === 'FAIL');
	console.log(`\n================ BROWSER SUMMARY ================`);
	console.log(`Total Browser Checks: ${results.length} | PASS: ${results.length - failed.length} | FAIL: ${failed.length}`);
	if (failed.length > 0) {
		process.exit(1);
	}
}

main().catch((err) => {
	console.error('[FATAL]', err);
	process.exit(1);
});
