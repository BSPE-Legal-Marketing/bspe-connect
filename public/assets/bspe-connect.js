/**
 * BSPE Connect — frontend bar behavior.
 *
 * Vanilla JS, no jQuery. Target: under 8KB minified+gzipped.
 *
 * Responsibilities:
 *   - Show the bar after the user scrolls past the configured threshold.
 *   - Hide on scroll up, show on scroll down (rAF-paced for 60fps).
 *   - Auto-show the welcome bubble after a delay following first bar appearance.
 *   - Persist bubble dismissal via session/local storage based on the repeat rule.
 *   - Wire button clicks to analytics dispatch (Phase 5 will collect them) and
 *     dispatch a `bspe:form_request_open` event when a form-mode button is hit
 *     so Phase 3 can subscribe and open the modal.
 */
(function () {
	'use strict';

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	function boot() {

	var root = document.getElementById('bspe-connect');
	if (!root) {
		return;
	}

	var data = window.BSPE_CONNECT_DATA || {};
	var bubbleData = data.bubble || {};

	var bar = root.querySelector('[data-bspe-bar]');
	var bubble = root.querySelector('[data-bspe-bubble]');
	var bubbleClose = bubble ? bubble.querySelector('[data-bspe-bubble-close]') : null;

	var showDelay = parseInt(data.showDelay, 10);
	if (!isFinite(showDelay) || showDelay < 0) { showDelay = 3; }
	showDelay *= 1000;

	// Scroll-trigger config (off when threshold is 0 — current default).
	// wp_localize_script casts top-level scalars to strings, so the bool
	// arrives as "1" / "0" — strict === true would always miss it. Truthy
	// check that explicitly rejects "0" and "false" handles every form.
	var scrollThreshold = parseInt(data.scrollThreshold, 10);
	if (!isFinite(scrollThreshold) || scrollThreshold < 0) { scrollThreshold = 0; }
	var hideOnScrollUp = !!data.hideOnScrollUp && data.hideOnScrollUp !== '0' && data.hideOnScrollUp !== 'false';

	var bubbleEnabled = bubbleData.enabled !== false;
	var bubbleTrigger = bubbleData.trigger || 'auto';
	var bubbleRepeat = bubbleData.repeat || 'session';
	var bubbleDelay = parseInt(bubbleData.delay, 10);
	if (!isFinite(bubbleDelay) || bubbleDelay < 0) { bubbleDelay = 3; }
	bubbleDelay *= 1000;

	var barVisible = false;
	var barEverShown = false;
	var bubbleTimer = null;
	var bubbleVisible = false;

	function setBarState(visible) {
		if (!bar) { return; }
		if (visible === barVisible) { return; }
		barVisible = visible;
		bar.setAttribute('data-bspe-state', visible ? 'visible' : 'hidden');
		root.setAttribute('data-bspe-state', visible ? 'visible' : 'hidden');

		if (visible && !barEverShown) {
			barEverShown = true;
			fire('bar_shown');
			scheduleBubble();
		}
	}

	// ----- Footer clearance --------------------------------------------
	// Reserve exactly the bar's rendered height as body padding-bottom,
	// so when the visitor scrolls to the very end of the page the fixed
	// bar doesn't cover the footer — and there's no oversized empty band
	// either. Measuring offsetHeight is pixel-exact: it already includes
	// the bar's min-height, padding, border, and the iOS safe-area inset,
	// none of which the PHP-side estimate can know.
	//
	// SNUG_GAP is the extra breathing space between the footer and the
	// bar. 0 = footer sits flush on top of the bar.
	//
	// The PHP CSS fallback (a rough estimate, !important, mobile media
	// query) stays in place for the no-JS case; this overrides it with
	// the precise value via an inline !important declaration, which wins
	// over a stylesheet !important rule.
	var SNUG_GAP = 0;
	function syncBodyClearance() {
		if (!bar || !document.body) { return; }
		// offsetHeight is the full box even while the bar is translated
		// off-screen (transform doesn't change layout height). It's only
		// 0 when the bar is display:none — i.e. above the mobile
		// breakpoint, where no padding should be reserved at all.
		var h = bar.offsetHeight;
		if (h > 0) {
			document.body.style.setProperty('padding-bottom', (h + SNUG_GAP) + 'px', 'important');
			// Publish the bar height so the inline stylesheet can lift a
			// third-party chat launcher above the bar via calc().
			document.documentElement.style.setProperty('--bspe-bar-h', h + 'px');
		} else {
			document.body.style.removeProperty('padding-bottom');
			document.documentElement.style.removeProperty('--bspe-bar-h');
		}
	}

	// ----- Anti-flash unhide -------------------------------------------
	// The bar ships with the `hidden` attribute so its raw markup stays
	// collapsed while an optimizer (NitroPack etc.) is still deferring our
	// stylesheet — no unstyled flash, zero layout height. The stylesheet
	// restores it via `.bspe-connect .bspe-connect__bar[hidden]
	// { display:flex }`, but a theme reset declaring
	// `[hidden] { display:none !important }` would keep the bar dead — so
	// strip the attribute as soon as we can PROVE the sheet has applied.
	// Sentinel: only our stylesheet makes the root position:fixed (the
	// inline head guard only touches visibility), so a fixed root means
	// the sheet is live. Poll briefly to ride out deferred-CSS setups.
	(function unhideWhenStyled() {
		if (!bar || !bar.hasAttribute('hidden')) { return; }
		var tries = 0;
		(function attempt() {
			var styled = false;
			try { styled = getComputedStyle(root).position === 'fixed'; } catch (e) { /* treat as not yet styled */ }
			if (styled) {
				bar.removeAttribute('hidden');
				syncBodyClearance();
				return;
			}
			if (++tries < 200) { setTimeout(attempt, 150); } // give up after ~30s: no CSS, no bar
		})();
	})();

	// ----- Bar show / hide ---------------------------------------------
	// Two layered triggers. Both are opt-in via the General → Display
	// behavior settings; with defaults (threshold = 0, hideOnScrollUp =
	// false) the bar simply slides in once the show-delay elapses and
	// stays put for the rest of the visit.
	//
	//   1. scrollThreshold > 0  → keep the bar hidden until the visitor
	//      scrolls past N pixels. Pairs with show-delay: the timer runs
	//      independently, so the bar appears at whichever signal fires
	//      LAST — meaning the threshold can't beat the delay and vice
	//      versa.
	//
	//   2. hideOnScrollUp = true → once the bar is visible, slide it
	//      back down on any meaningful upward scroll (>4 px) and bring
	//      it back on the next downward scroll. Threshold still applies
	//      while at the top.
	var delayElapsed = false;
	setTimeout(function () {
		delayElapsed = true;
		// If no scroll trigger is configured we just show the bar now;
		// otherwise we let the scroll handler decide whether the threshold
		// has been passed.
		if (scrollThreshold === 0) {
			setBarState(true);
		} else {
			updateBarFromScroll();
		}
	}, showDelay);

	var lastScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
	var scrollFrame = 0;
	function updateBarFromScroll() {
		if (!delayElapsed) { return; }
		var y = window.pageYOffset || document.documentElement.scrollTop || 0;
		var delta = y - lastScrollY;
		var passedThreshold = scrollThreshold === 0 || y >= scrollThreshold;

		if (!passedThreshold) {
			setBarState(false);
		} else if (hideOnScrollUp && delta < -4 && y > 0) {
			// Visitor scrolling up — slide the bar away.
			setBarState(false);
		} else if (delta > 0 || !hideOnScrollUp) {
			// Visitor scrolling down OR scroll-up-hide disabled.
			setBarState(true);
		}
		lastScrollY = y;
	}

	// Only attach the scroll listener if at least one scroll-driven
	// behavior is configured — saves a passive listener on default
	// installs.
	if (scrollThreshold > 0 || hideOnScrollUp) {
		window.addEventListener('scroll', function () {
			if (scrollFrame) { return; }
			scrollFrame = requestAnimationFrame(function () {
				scrollFrame = 0;
				updateBarFromScroll();
			});
		}, { passive: true });
	}

	// Pin our root as the last <body> child. Third-party widgets (live
	// chat, accessibility toolbars) inject themselves late and squat at
	// the 32-bit max z-index; since our root now matches that z-index,
	// DOM order breaks the tie — being last means our welcome bubble
	// wins. Run once after a beat (so those widgets have mounted) and
	// again whenever the welcome bubble is about to show.
	function pinRootLast() {
		try {
			if (document.body && document.body.lastElementChild !== root) {
				document.body.appendChild(root);
			}
		} catch (e) { /* ignore */ }
	}
	setTimeout(pinRootLast, 1500);

	// ----- Yield to the site's mobile menu -----------------------------
	// We (and the third-party chat / accessibility widgets we sit beside)
	// pin ourselves at the 32-bit max z-index so the welcome bubble beats
	// those widgets during normal browsing. The side effect: when the
	// visitor opens the theme's hamburger menu, all of that floating UI
	// sails on top of it — the menu overlays are usually a modest z-index
	// (Elementor popups sit at 9999, far below us). Rather than guess and
	// chase every theme's menu z-index (it varies per site and we don't
	// cleanly own the widgets' values), we detect the open menu and stamp
	// `html.bspe-menu-open`; the stylesheet then hides the bar, Intaker
	// (#icw, its green "Call us" included) and UserWay while it's set. The
	// menu itself is never touched.
	//
	// Detection is theme-agnostic and covers the two shapes these client
	// sites actually use:
	//   1. A toggle that flips aria-expanded / an active class
	//      (Elementor nav: `.elementor-menu-toggle.elementor-active`).
	//   2. A full-screen overlay/popup: we watch a TRANSIENT scroll-lock
	//      body class (Elementor `dialog-prevent-scroll`, Popup Maker
	//      `pum-open`) AND the actual visible modal element via
	//      hasBlockingOverlay(). We deliberately do NOT key off Elementor's
	//      `dialog-lightbox-body` / `dialog-container` classes: Elementor
	//      adds those to <body> on the first popup open and NEVER removes
	//      them, so treating them as "open" would leave the flag stuck on
	//      after the menu closes (bar + widgets hidden forever). The modal
	//      itself goes display:none on close, so hasBlockingOverlay() is the
	//      reliable open/closed signal.
	// A site owner can add an extra toggle signal via the
	// `menuOpenSelector` localized string for exotic themes.
	var MENU_OPEN_SELECTORS = [
		'.elementor-menu-toggle.elementor-active',
		'.elementor-menu-toggle[aria-expanded="true"]',
		'.menu-toggle[aria-expanded="true"]',
		'.menu-toggle.toggled-on',
		'.mobile-menu-toggle.active',
		'.mobile-nav-toggle.active',
		'.hamburger.is-active',
		'.hamburger.active',
		'[aria-controls][aria-expanded="true"][class*="menu"]',
		'[aria-controls][aria-expanded="true"][class*="nav"]',
		'[aria-controls][aria-expanded="true"][class*="hamburger"]'
	];
	if (data.menuOpenSelector) {
		MENU_OPEN_SELECTORS.push(String(data.menuOpenSelector));
	}
	// Body/html classes set ONLY while a menu / full-screen modal is open.
	// Every entry here must be TRANSIENT (added on open, removed on close).
	// Elementor's `dialog-prevent-scroll` qualifies; its `dialog-lightbox-body`
	// / `dialog-container` do NOT (they persist for the page lifetime) so they
	// are intentionally absent — see hasBlockingOverlay() for the popup signal.
	var MENU_OPEN_BODY_CLASSES = [
		'menu-open', 'nav-open', 'mobile-menu-open', 'mobile-nav-open',
		'off-canvas-open', 'ast-off-canvas-open', 'is-menu-open',
		'has-mobile-menu-open', 'slideout-open', 'oceanwp-off-canvas-open',
		'elementskit-menu-hamburger-active',
		'dialog-prevent-scroll',        // Elementor popup scroll-lock (transient)
		'pum-open', 'pum-open-overlay'   // Popup Maker
	];
	// Fixed, roughly full-viewport overlay elements — catches Elementor
	// popup menus / Popup Maker even if the body-class names ever change.
	var MENU_OPEN_OVERLAYS = '.elementor-popup-modal, .pum-overlay, .off-canvas.is-open';
	function hasBlockingOverlay() {
		var els;
		try { els = document.querySelectorAll(MENU_OPEN_OVERLAYS); }
		catch (e) { return false; }
		for (var i = 0; i < els.length; i++) {
			var el = els[i], cs = getComputedStyle(el);
			if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity || '1') < 0.05) { continue; }
			var r = el.getBoundingClientRect();
			// Only a large overlay counts as a menu — a fixed full-screen
			// modal, not a small inline embed.
			if (r.width >= window.innerWidth * 0.5 && r.height >= window.innerHeight * 0.4) { return true; }
		}
		return false;
	}
	function isMobileMenuOpen() {
		var de = document.documentElement, b = document.body;
		for (var i = 0; i < MENU_OPEN_BODY_CLASSES.length; i++) {
			var c = MENU_OPEN_BODY_CLASSES[i];
			if (de.classList.contains(c) || (b && b.classList.contains(c))) { return true; }
		}
		for (var j = 0; j < MENU_OPEN_SELECTORS.length; j++) {
			try {
				if (document.querySelector(MENU_OPEN_SELECTORS[j])) { return true; }
			} catch (e) { /* unsupported selector on this engine — skip */ }
		}
		// getComputedStyle path last — only reached when the cheap class /
		// selector checks all missed.
		return hasBlockingOverlay();
	}
	// Intaker's open panel (preview video or full chat frame) is a
	// bottom-anchored sheet whose answer chips, input row and Send button
	// sit exactly where the bar is. While it's open we stamp
	// `html.bspe-chat-open` and the stylesheet hides the bar (only the
	// bar — #icw itself must stay visible, unlike the menu case).
	function isChatOpen() {
		var icw = document.getElementById('icw');
		return !!icw && (icw.classList.contains('icw--open') || icw.classList.contains('icw--is-frame'));
	}
	var menuOpenNow = false;
	var chatOpenNow = false;
	function syncMenuOpenState() {
		var chat = isChatOpen();
		if (chat !== chatOpenNow) {
			chatOpenNow = chat;
			document.documentElement.classList.toggle('bspe-chat-open', chat);
		}
		var open = isMobileMenuOpen();
		if (open === menuOpenNow) { return; }
		menuOpenNow = open;
		document.documentElement.classList.toggle('bspe-menu-open', open);
	}
	var menuFrame = 0;
	function scheduleMenuSync() {
		if (menuFrame) { return; }
		menuFrame = requestAnimationFrame(function () {
			menuFrame = 0;
			syncMenuOpenState();
		});
	}
	try {
		// One observer on the document subtree. Watches the attributes
		// menus flip (class / aria-expanded / inline style) plus node
		// insertion (Elementor popups mount their modal lazily). Bursts
		// collapse to one cheap re-check per frame via requestAnimationFrame.
		new MutationObserver(scheduleMenuSync).observe(document.documentElement, {
			subtree: true,
			childList: true,
			attributes: true,
			attributeFilter: ['class', 'aria-expanded', 'style']
		});
	} catch (e) { /* MutationObserver unavailable — static check below still runs */ }
	syncMenuOpenState();

	// Reserve footer clearance now, and re-measure when anything that
	// can change the bar height happens: viewport resize / rotation
	// (also handles crossing the mobile breakpoint, where the bar goes
	// display:none and clearance drops to 0) and web-font load (font
	// swap can change the label line height).
	syncBodyClearance();
	var clearanceFrame = 0;
	window.addEventListener('resize', function () {
		if (clearanceFrame) { return; }
		clearanceFrame = requestAnimationFrame(function () {
			clearanceFrame = 0;
			syncBodyClearance();
		});
	}, { passive: true });
	if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
		document.fonts.ready.then(syncBodyClearance).catch(function () {});
	} else {
		// Fonts API unavailable — re-measure once after a beat so a late
		// font swap doesn't leave the reservation slightly off.
		setTimeout(syncBodyClearance, 600);
	}

	// ----- Welcome bubble -----
	function scheduleBubble() {
		if (!bubble || !bubbleEnabled) { return; }
		if (bubbleTrigger !== 'auto') { return; }
		if (!shouldShowBubble()) { return; }

		clearTimeout(bubbleTimer);
		bubbleTimer = setTimeout(showBubble, bubbleDelay);
	}

	function shouldShowBubble() {
		var key = 'bspe_connect_bubble_dismissed';
		if (bubbleRepeat === 'session') { return !readStorage(sessionStorage, key); }
		if (bubbleRepeat === 'once') { return !readStorage(localStorage, key); }
		return true;
	}

	function showBubble() {
		if (!bubble || bubbleVisible) { return; }
		// Make sure we're the last body child so the bubble sits above
		// any third-party widget that mounted after our initial pin.
		pinRootLast();
		bubble.removeAttribute('hidden');
		// Force reflow so the transition can run from initial state.
		void bubble.offsetWidth;
		bubble.setAttribute('data-bspe-state', 'visible');
		bubbleVisible = true;
		fire('bubble_shown');
	}

	function dismissBubble(persist) {
		if (!bubble) { return; }
		bubble.setAttribute('data-bspe-state', 'hidden');
		bubbleVisible = false;
		setTimeout(function () {
			if (!bubbleVisible) { bubble.setAttribute('hidden', ''); }
		}, 240);
		if (persist) {
			var key = 'bspe_connect_bubble_dismissed';
			if (bubbleRepeat === 'session') { writeStorage(sessionStorage, key, '1'); }
			else if (bubbleRepeat === 'once') { writeStorage(localStorage, key, '1'); }
		}
		fire('bubble_dismissed');
	}

	if (bubbleClose) {
		bubbleClose.addEventListener('click', function () { dismissBubble(true); });
	}

	function readStorage(store, key) {
		try { return store.getItem(key); } catch (e) { return null; }
	}
	function writeStorage(store, key, value) {
		try { store.setItem(key, value); } catch (e) { /* private mode, ignore */ }
	}

	// ----- Button click handlers -----
	function bindButton(selector, handler) {
		var el = root.querySelector(selector);
		if (el && handler) { el.addEventListener('click', handler); }
		return el;
	}

	bindButton('[data-action="connect"]', function (event) {
		event.preventDefault();
		fire('connect_click');
		clearTimeout(bubbleTimer);
		if (bubbleVisible) { dismissBubble(false); }
		else if (bubble && bubbleEnabled) { showBubble(); }
	});

	bindButton('[data-action="call"]', function () {
		fire('call_click');
		// tel: link continues with default behavior.
	});

	bindButton('[data-action="text"]', function (event) {
		var mode = event.currentTarget.getAttribute('data-bspe-mode');
		fire('text_click');
		if (mode !== 'sms') {
			event.preventDefault();
			openForm('text');
		}
	});

	bindButton('[data-action="email"]', function (event) {
		event.preventDefault();
		fire('email_click');
		openForm('email');
	});

	bindButton('[data-action="chat"]', function (event) {
		event.preventDefault();
		fire('chat_click');
		openProviderChat();
	});

	// Open the third-party chat (Intaker / custom) by triggering its own
	// launcher. The provider's launcher stays visible on the page; our
	// Chat button is a second way in. We dispatch a click on the first
	// matching launcher selector. Because the provider script loads
	// async, the launcher may not exist on the first try — retry a few
	// times over ~2.5s before giving up.
	function clickEl(el) {
		if (!el) { return false; }
		// Fire the full press sequence, not just `click`. Some widgets
		// (especially on touch devices) bind pointer/mouse events instead
		// of — or in addition to — click; a bare synthetic click() skips
		// those entirely. Extra events on listeners that only want click
		// are harmless.
		try {
			if (typeof PointerEvent === 'function') {
				el.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true }));
				el.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, cancelable: true }));
			}
			el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
			el.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
		} catch (e) { /* old engine — the click() below still fires */ }
		if (typeof el.click === 'function') { el.click(); }
		else { el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window })); }
		return true;
	}

	function openProviderChat() {
		var cfg = (data && data.chat) || {};
		var selectors = cfg.openSelectors || [];

		// Toggle: if Intaker's widget is already expanded (preview or
		// chat frame — both carry `icw--open`), close it. Just re-clicking
		// the launcher leaves the page-dimming backdrop behind, which was
		// the reported "still blurry" bug. We close robustly:
		//   1. Click the backdrop — Intaker's own "click outside to close"
		//      handler, which both closes the widget and clears the blur.
		//   2. Click the preview close button as well.
		//   3. Force-remove the backdrop's open class, so the blur is gone
		//      even if Intaker's own teardown left it on.
		var icw = document.querySelector('#icw');
		if (icw && icw.classList.contains('icw--open')) {
			var backdrop = document.querySelector('#icw--backdrop-container');
			if (backdrop && backdrop.classList.contains('icw--backdrop-open')) {
				clickEl(backdrop);
			}
			clickEl(document.querySelector('#icw--preview--close, #icw [aria-label*="Close Intaker"], #icw [class*="--close"]'));
			if (backdrop) { backdrop.classList.remove('icw--backdrop-open'); }
			return;
		}

		if (!selectors.length) { return; }

		// Provider script loads async — the launcher may not exist on the
		// first try. Worse, delay-JS optimizers (NitroPack) hold third-party
		// scripts until the FIRST user interaction: on a phone that first
		// interaction is this very tap, so the provider only starts
		// downloading now and can need several seconds on mobile networks to
		// mount its launcher. Poll fast for the common case (2.5s at 250ms),
		// then patiently at 1s for ~15s total before giving up.
		var attempts = 0;
		(function tryOpen() {
			for (var i = 0; i < selectors.length; i++) {
				var el = document.querySelector(selectors[i]);
				// Skip launchers that exist but are display:none (e.g. the
				// hidden green Call button shares the .widget-button class) —
				// clicking those would silently "succeed" doing nothing.
				if (el && el.offsetParent === null) { continue; }
				if (clickEl(el)) { return; }
			}
			attempts++;
			if (attempts < 10) {
				setTimeout(tryOpen, 250);       // fast phase: ~2.5s
			} else if (attempts < 23) {
				setTimeout(tryOpen, 1000);      // patient phase: ~15s total
			}
		})();
	}

	function openForm(source) {
		// Phase 3 subscribes to this event to open the bottom-sheet modal.
		try {
			window.dispatchEvent(new CustomEvent('bspe:form_request_open', {
				detail: { source: source }
			}));
		} catch (e) { /* IE-only fallback not needed; Phase 1 dropped IE */ }
		fire('form_open', { source: source });
	}

	// ----- Analytics dispatch -----
	var sessionId = ensureSessionId();
	var restEndpoint = data.restEndpoint || '';

	function ensureSessionId() {
		var key = 'bspe_connect_session_id';
		try {
			var existing = sessionStorage.getItem(key);
			if (existing) { return existing; }
			var fresh = generateUUID();
			sessionStorage.setItem(key, fresh);
			return fresh;
		} catch (e) {
			return generateUUID();
		}
	}

	function generateUUID() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			try { return window.crypto.randomUUID(); } catch (e) { /* fall through */ }
		}
		// Fallback: RFC4122-ish v4 from Math.random.
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			var v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	function deliverEvent(eventType) {
		if (!restEndpoint) {
			console.warn('[BSPE Connect] No REST endpoint configured — analytics dropped:', eventType);
			return;
		}
		try {
			fetch(restEndpoint, {
				method: 'POST',
				keepalive: true,
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify({
					event_type: eventType,
					page_url:   window.location.href,
					session_id: sessionId
				})
			}).then(function (resp) {
				if (!resp.ok) {
					console.warn('[BSPE Connect] Analytics event got non-OK', resp.status, eventType);
				}
			}).catch(function (err) {
				console.warn('[BSPE Connect] Analytics event fetch failed', eventType, err);
			});
		} catch (e) {
			console.warn('[BSPE Connect] Analytics event threw', eventType, e);
		}
	}

	function fire(eventType, detail) {
		try {
			window.dispatchEvent(new CustomEvent('bspe:' + eventType, {
				detail: Object.assign({ type: eventType }, detail || {})
			}));
		} catch (e) { /* ignore */ }

		deliverEvent(eventType);
	}

	// ----- Form modal -----
	var modal = root.querySelector('[data-bspe-modal]');
	var modalSheet = modal ? modal.querySelector('.bspe-connect__modal-sheet') : null;
	var modalBackdrop = modal ? modal.querySelector('[data-bspe-modal-backdrop]') : null;
	var modalClose = modal ? modal.querySelector('[data-bspe-modal-close]') : null;
	var modalHeading = modal ? modal.querySelector('[data-bspe-modal-heading]') : null;
	var modalSubheading = modal ? modal.querySelector('[data-bspe-modal-subheading]') : null;
	var modalSuccess = modal ? modal.querySelector('[data-bspe-modal-success]') : null;
	var successMessage = modal ? modal.querySelector('[data-bspe-success-message]') : null;
	var successWrapper = modal ? modal.querySelector('.bspe-connect__success') : null;
	var form = modal ? modal.querySelector('[data-bspe-form]') : null;
	var sourceField = form ? form.querySelector('[data-bspe-source]') : null;
	var pageUrlField = form ? form.querySelector('[data-bspe-page-url]') : null;
	var submitBtn = form ? form.querySelector('[data-bspe-submit]') : null;
	var formError = form ? form.querySelector('[data-bspe-form-error]') : null;
	var phoneInputs = form ? form.querySelectorAll('[data-bspe-phone-mask]') : [];

	// Multi-step (custom) form. Present only when the site switched the
	// lead form to "custom" mode; the standard form has no [data-bspe-step].
	var steps = form ? Array.prototype.slice.call(form.querySelectorAll('[data-bspe-step]')) : [];
	var isMultistep = steps.length > 0;
	var stepIndex = 0;
	var backBtn = form ? form.querySelector('[data-bspe-back]') : null;
	var nextBtn = form ? form.querySelector('[data-bspe-next]') : null;
	var progressWrap = form ? form.querySelector('[data-bspe-progress]') : null;
	var progressLabel = form ? form.querySelector('[data-bspe-progress-label]') : null;
	var progressFill = form ? form.querySelector('[data-bspe-progress-fill]') : null;
	var modalBody = modal ? modal.querySelector('.bspe-connect__modal-body') : null;
	var i18n = (data && data.i18n) || {};

	var modalOpen = false;
	var modalLastFocus = null;
	var modalAutoCloseTimer = null;
	var savedBodyOverflow = '';

	function openModal(source) {
		if (!modal) { return; }
		var src = (source === 'text') ? 'text' : 'email';

		if (sourceField) { sourceField.value = src; }
		if (pageUrlField) { pageUrlField.value = window.location.href; }
		if (modalHeading) {
			var attr = (src === 'text') ? 'data-text-heading' : 'data-email-heading';
			var heading = modal.getAttribute(attr);
			if (heading) { modalHeading.textContent = heading; }
		}
		if (modalSubheading) {
			var subAttr = (src === 'text') ? 'data-text-subheading' : 'data-email-subheading';
			var sub = modal.getAttribute(subAttr);
			if (sub) { modalSubheading.textContent = sub; }
		}

		clearTimeout(modalAutoCloseTimer);
		resetFormState();

		modalLastFocus = document.activeElement;
		modal.removeAttribute('hidden');
		// reflow so transition runs.
		void modal.offsetWidth;
		modal.setAttribute('data-bspe-state', 'visible');
		modalOpen = true;

		savedBodyOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';

		// Focus the first focusable input after the slide-in finishes.
		setTimeout(function () {
			var scope = (isMultistep && steps[stepIndex]) ? steps[stepIndex] : form;
			var first = scope ? scope.querySelector('input:not([type=hidden]):not([tabindex="-1"]), select, textarea') : null;
			if (first && typeof first.focus === 'function') {
				try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
			}
		}, 260);

		fire('form_open', { source: src });
	}

	function closeModal() {
		if (!modal || !modalOpen) { return; }
		modal.setAttribute('data-bspe-state', 'hidden');
		modalOpen = false;
		document.body.style.overflow = savedBodyOverflow;

		setTimeout(function () {
			if (!modalOpen) {
				modal.setAttribute('hidden', '');
				resetFormState();
				if (modal.getAttribute('data-bspe-form-state') === 'success') {
					modal.removeAttribute('data-bspe-form-state');
					if (successWrapper) { successWrapper.classList.remove('is-revealed'); }
				}
			}
		}, 260);

		if (modalLastFocus && typeof modalLastFocus.focus === 'function') {
			try { modalLastFocus.focus({ preventScroll: true }); } catch (e) { modalLastFocus.focus(); }
		}
	}

	function resetFormState() {
		if (!form) { return; }
		clearAllErrors();
		if (submitBtn) {
			submitBtn.removeAttribute('aria-busy');
			submitBtn.disabled = false;
		}
	}

	function clearAllErrors() {
		if (!form) { return; }
		if (formError) {
			formError.textContent = '';
			formError.setAttribute('hidden', '');
		}
		var nodes = form.querySelectorAll('[data-bspe-error]');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].textContent = '';
			nodes[i].setAttribute('hidden', '');
		}
		var inputs = form.querySelectorAll('[aria-invalid]');
		for (var j = 0; j < inputs.length; j++) {
			inputs[j].removeAttribute('aria-invalid');
		}
	}

	function cssEscape(value) {
		return (window.CSS && CSS.escape) ? CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
	}

	function fieldInputs(field) {
		var wrap = form.querySelector('[data-field="' + cssEscape(field) + '"]');
		var list = wrap ? wrap.querySelectorAll('input:not([type=hidden]), select, textarea') : [];
		if (!list.length) {
			list = form.querySelectorAll('[name="' + cssEscape(field) + '"], [name="bspe_f[' + cssEscape(field) + ']"], [name="bspe_f[' + cssEscape(field) + '][]"]');
		}
		return list;
	}

	function setFieldError(field, message) {
		if (!form) { return; }
		var node = form.querySelector('[data-bspe-error="' + cssEscape(field) + '"]');
		if (node) {
			node.textContent = message;
			node.removeAttribute('hidden');
		}
		var inputs = fieldInputs(field);
		for (var i = 0; i < inputs.length; i++) {
			inputs[i].setAttribute('aria-invalid', 'true');
		}
	}

	function clearFieldError(field) {
		if (!form) { return; }
		var node = form.querySelector('[data-bspe-error="' + cssEscape(field) + '"]');
		if (node) {
			node.textContent = '';
			node.setAttribute('hidden', '');
		}
		var inputs = fieldInputs(field);
		for (var i = 0; i < inputs.length; i++) {
			inputs[i].removeAttribute('aria-invalid');
		}
	}

	// ----- Multi-step controller -----
	function focusFirst(scope, selector) {
		var el = scope ? scope.querySelector(selector || 'input:not([type=hidden]):not([tabindex="-1"]), select, textarea') : null;
		if (el && typeof el.focus === 'function') {
			try { el.focus({ preventScroll: false }); } catch (e) { el.focus(); }
		}
	}

	function showStep(index, focus) {
		if (!isMultistep) { return; }
		index = Math.max(0, Math.min(steps.length - 1, index));
		stepIndex = index;
		var last = index === steps.length - 1;
		for (var i = 0; i < steps.length; i++) {
			if (i === index) { steps[i].removeAttribute('hidden'); } else { steps[i].setAttribute('hidden', ''); }
		}
		if (backBtn) { if (index > 0) { backBtn.removeAttribute('hidden'); } else { backBtn.setAttribute('hidden', ''); } }
		if (nextBtn) { if (last) { nextBtn.setAttribute('hidden', ''); } else { nextBtn.removeAttribute('hidden'); } }
		if (submitBtn) { if (last) { submitBtn.removeAttribute('hidden'); } else { submitBtn.setAttribute('hidden', ''); } }
		if (progressLabel && progressWrap) {
			var fmt = progressWrap.getAttribute('data-format') || 'Step {current} of {total}';
			progressLabel.textContent = fmt.replace('{current}', String(index + 1)).replace('{total}', String(steps.length));
		}
		if (progressFill) { progressFill.style.width = ((index + 1) / steps.length * 100) + '%'; }
		if (modalBody) { modalBody.scrollTop = 0; }
		if (focus) { setTimeout(function () { focusFirst(steps[index]); }, 30); }
		// DOM event only (for GTM / site scripts): steps are not stored as
		// analytics rows, so nothing is sent to the REST endpoint.
		try {
			window.dispatchEvent(new CustomEvent('bspe:form_step', { detail: { type: 'form_step', step: index + 1, total: steps.length } }));
		} catch (e) { /* ignore */ }
	}

	function wrapValue(wrap) {
		var type = wrap.getAttribute('data-type');
		if (type === 'radio' || type === 'checkboxes') {
			return wrap.querySelector('input:checked') ? 'x' : '';
		}
		if (type === 'checkbox') {
			var cb = wrap.querySelector('input[type=checkbox]');
			return (cb && cb.checked) ? '1' : '';
		}
		var el = wrap.querySelector('input:not([type=hidden]), select, textarea');
		return el ? String(el.value || '').trim() : '';
	}

	function validateWrap(wrap) {
		var key = wrap.getAttribute('data-field');
		var type = wrap.getAttribute('data-type');
		var value = wrapValue(wrap);
		var err = '';
		if (value === '') {
			if (wrap.hasAttribute('data-required')) {
				if (type === 'select' || type === 'radio' || type === 'checkboxes') {
					err = i18n.choose || 'Please make a selection.';
				} else if (type === 'checkbox') {
					err = i18n.check || 'Please check this box to continue.';
				} else {
					err = i18n.required || 'This field is required.';
				}
			}
		} else if (type === 'email') {
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) { err = i18n.email || 'Please enter a valid email address.'; }
		} else if (type === 'tel') {
			var d = value.replace(/\D/g, '');
			if (wrap.getAttribute('data-phone-format') === 'intl') {
				if (d.length < 7 || d.length > 15) { err = i18n.phoneIntl || 'Please enter a valid phone number.'; }
			} else {
				if (d.length === 11 && d.charAt(0) === '1') { d = d.slice(1); }
				if (d.length !== 10) { err = i18n.phone || 'Please enter a 10-digit US phone number.'; }
			}
		} else if (type === 'number') {
			var n = value.replace(/[^0-9.\-]/g, '');
			if (n === '' || isNaN(Number(n))) {
				err = i18n.number || 'Please enter a number.';
			} else {
				var min = wrap.getAttribute('data-min');
				var max = wrap.getAttribute('data-max');
				if ((min !== null && min !== '' && Number(n) < Number(min)) || (max !== null && max !== '' && Number(n) > Number(max))) {
					err = i18n.range || 'Please enter a number in the allowed range.';
				}
			}
		}
		if (err) { setFieldError(key, err); } else { clearFieldError(key); }
		return !err;
	}

	function validateStep(index) {
		var wraps = steps[index] ? steps[index].querySelectorAll('[data-field]') : [];
		var firstBad = null;
		for (var i = 0; i < wraps.length; i++) {
			if (!validateWrap(wraps[i]) && !firstBad) { firstBad = wraps[i]; }
		}
		if (firstBad) {
			focusFirst(firstBad, 'input:not([type=hidden]), select, textarea');
		}
		return !firstBad;
	}

	function stepOfField(field) {
		var wrap = form.querySelector('[data-field="' + cssEscape(field) + '"]');
		if (!wrap) { return -1; }
		for (var i = 0; i < steps.length; i++) {
			if (steps[i].contains(wrap)) { return i; }
		}
		return -1;
	}

	if (isMultistep) {
		if (nextBtn) {
			nextBtn.addEventListener('click', function () {
				if (validateStep(stepIndex)) { showStep(stepIndex + 1, true); }
			});
		}
		if (backBtn) {
			backBtn.addEventListener('click', function () { showStep(stepIndex - 1, true); });
		}
		// Enter in a single-line input moves to the next step instead of
		// (implicitly) submitting a half-filled form.
		form.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' || stepIndex >= steps.length - 1) { return; }
			var t = event.target;
			if (!t || t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON') { return; }
			event.preventDefault();
			if (validateStep(stepIndex)) { showStep(stepIndex + 1, true); }
		});
		// Re-validate a field as soon as the visitor fixes it.
		form.addEventListener('change', function (event) {
			var wrap = event.target && event.target.closest ? event.target.closest('[data-field]') : null;
			if (wrap && form.querySelector('[data-bspe-error="' + cssEscape(wrap.getAttribute('data-field')) + '"]:not([hidden])')) {
				validateWrap(wrap);
			}
		});
	}

	function setFormError(message) {
		if (!formError) { return; }
		formError.textContent = message;
		formError.removeAttribute('hidden');
	}

	function showSuccess(message) {
		if (!modal) { return; }
		if (successMessage && message) { successMessage.textContent = message; }
		modal.setAttribute('data-bspe-form-state', 'success');
		if (successWrapper) { successWrapper.removeAttribute('hidden'); }
		// A finished multi-step form starts over at step 1 next time.
		if (isMultistep) {
			try { form.reset(); } catch (e) { /* ignore */ }
			showStep(0, false);
		}
		// Allow CSS to animate the checkmark in.
		setTimeout(function () {
			if (successWrapper) { successWrapper.classList.add('is-revealed'); }
		}, 30);
		fire('form_success');

		clearTimeout(modalAutoCloseTimer);
		modalAutoCloseTimer = setTimeout(closeModal, 3000);
	}

	// ----- Phone live mask -----
	function maskPhone(raw) {
		var digits = (raw || '').replace(/\D/g, '');
		// "+1 (555) 555-0123" (autofill, paste): drop the country code
		// instead of cutting the last digit. US area codes never start with 1.
		if (digits.length > 10 && digits.charAt(0) === '1') { digits = digits.slice(1); }
		digits = digits.slice(0, 10);
		if (digits.length === 0) { return ''; }
		if (digits.length < 4)  { return '(' + digits; }
		if (digits.length < 7)  { return '(' + digits.slice(0, 3) + ') ' + digits.slice(3); }
		return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
	}

	Array.prototype.forEach.call(phoneInputs, function (input) {
		input.addEventListener('input', function () {
			input.value = maskPhone(input.value);
		});
	});

	// ----- Mobile keyboard scroll-into-view -----
	// On iOS the soft keyboard slides up over the bottom sheet and can hide
	// the field the user just tapped. Without this, the user has to scroll
	// the modal manually to see what they're typing. We listen for focusin
	// on the form, wait for the keyboard to actually appear (~280ms feels
	// right), then scroll the field to the centre of the visible area.
	// visualViewport is preferred when available because it accounts for
	// the keyboard inset; we fall back to scrollIntoView({block:'center'})
	// otherwise.
	if (form) {
		var scrollTimer = null;
		form.addEventListener('focusin', function (event) {
			var target = event.target;
			if (!target || typeof target.scrollIntoView !== 'function') { return; }
			// Only scroll real form fields — don't disturb the close button.
			if (!target.matches('input, textarea, select')) { return; }
			if (target.type === 'hidden') { return; }

			clearTimeout(scrollTimer);
			scrollTimer = setTimeout(function () {
				try {
					target.scrollIntoView({ behavior: 'smooth', block: 'center' });
				} catch (err) {
					// Older Safari throws on the options bag — fall back.
					target.scrollIntoView();
				}
			}, 280);
		});
	}

	// ----- Form submit -----
	if (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (!submitBtn) { return; }

			if (isMultistep) {
				// Not on the last step yet: this is a "Next", not a submit.
				if (stepIndex < steps.length - 1) {
					if (validateStep(stepIndex)) { showStep(stepIndex + 1, true); }
					return;
				}
				for (var si = 0; si < steps.length; si++) {
					if (!validateStep(si)) {
						showStep(si, false);
						validateStep(si);
						return;
					}
				}
			}

			clearAllErrors();
			submitBtn.setAttribute('aria-busy', 'true');
			submitBtn.disabled = true;

			fire('form_submit');

			var fd = new FormData(form);

			// Use getAttribute('action') and the localized fallback so we
			// don't trip the iOS Safari named-element collision: when a
			// form has an <input name="action"> (which admin-ajax requires),
			// `form.action` returns the input element instead of the URL,
			// and fetch coerces it to "[object HTMLInputElement]" → 404.
			var endpoint = form.getAttribute('action') || (data && data.ajaxUrl) || '';

			console.log('[BSPE Connect] Submitting form to', endpoint);

			fetch(endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			}).then(function (response) {
				if (!response.ok) {
					console.warn('[BSPE Connect] Non-OK response', response.status, response.statusText, response.url);
				}
				return response.text().then(function (text) {
					var payload;
					try {
						payload = JSON.parse(text);
					} catch (e) {
						console.error('[BSPE Connect] Server returned non-JSON. URL:', response.url, ' Status:', response.status, ' Body (first 500):', text.slice(0, 500));
						payload = { success: false, data: { errors: { _form: '' } } };
					}
					return { ok: response.ok, status: response.status, payload: payload };
				});
			}).then(function (result) {
				submitBtn.removeAttribute('aria-busy');
				submitBtn.disabled = false;

				if (result.payload && result.payload.success) {
					var msg = (result.payload.data && result.payload.data.message) || '';
					showSuccess(msg);
					return;
				}

				console.warn('[BSPE Connect] Submission rejected. Status:', result.status, ' Payload:', result.payload);

				var errors = (result.payload && result.payload.data && result.payload.data.errors) || {};
				var hadFieldError = false;
				for (var key in errors) {
					if (!Object.prototype.hasOwnProperty.call(errors, key)) { continue; }
					if (key === '_form') { continue; }
					// Only count errors this markup can actually show; an
					// error for a field the page doesn't have (stale cache)
					// falls through to the form-level message below.
					if (form.querySelector('[data-bspe-error="' + cssEscape(key) + '"]')) {
						setFieldError(key, errors[key]);
						hadFieldError = true;
					}
				}
				if (isMultistep && hadFieldError) {
					var jumpTo = -1;
					for (var ek in errors) {
						if (!Object.prototype.hasOwnProperty.call(errors, ek) || ek === '_form') { continue; }
						var at = stepOfField(ek);
						if (at >= 0 && (jumpTo < 0 || at < jumpTo)) { jumpTo = at; }
					}
					if (jumpTo >= 0 && jumpTo !== stepIndex) { showStep(jumpTo, true); }
				}
				if (errors._form) {
					setFormError(errors._form);
				} else if (!hadFieldError) {
					setFormError((data.i18n && data.i18n.submitError) || 'Something went wrong. Please try again.');
				}
				fire('form_error');
			}).catch(function (err) {
				submitBtn.removeAttribute('aria-busy');
				submitBtn.disabled = false;
				console.error('[BSPE Connect] Network error during form submit. Endpoint:', endpoint, ' Error:', err);
				setFormError((data.i18n && data.i18n.networkError) || 'Network error. Please try again.');
				fire('form_error');
			});
		});
	}

	// ----- Modal listeners (close, esc, backdrop) -----
	if (modalClose) {
		modalClose.addEventListener('click', closeModal);
	}
	if (modalBackdrop) {
		modalBackdrop.addEventListener('click', closeModal);
	}
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modalOpen) {
			closeModal();
		}
	});

	// React to form_request_open events emitted from button clicks above.
	window.addEventListener('bspe:form_request_open', function (event) {
		var src = (event && event.detail && event.detail.source) || 'email';
		openModal(src);
	});

	} // end boot()
})();
