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
	function openProviderChat() {
		var cfg = (data && data.chat) || {};
		var selectors = cfg.openSelectors || [];
		if (!selectors.length) { return; }

		var attempts = 0;
		var maxAttempts = 10;
		(function tryOpen() {
			for (var i = 0; i < selectors.length; i++) {
				var el = document.querySelector(selectors[i]);
				if (el) {
					// Prefer a native click (fires the provider's bound
					// handler); fall back to a dispatched MouseEvent.
					if (typeof el.click === 'function') {
						el.click();
					} else {
						el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
					}
					return;
				}
			}
			attempts++;
			if (attempts < maxAttempts) {
				setTimeout(tryOpen, 250);
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
	var phoneInput = form ? form.querySelector('[data-bspe-phone-mask]') : null;

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
			var first = form ? form.querySelector('input:not([type=hidden]):not([tabindex="-1"]), select, textarea') : null;
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

	function setFieldError(field, message) {
		if (!form) { return; }
		var node = form.querySelector('[data-bspe-error="' + field + '"]');
		if (node) {
			node.textContent = message;
			node.removeAttribute('hidden');
		}
		var input = form.querySelector('[name="' + field + '"]');
		if (input) {
			input.setAttribute('aria-invalid', 'true');
		}
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
		var digits = (raw || '').replace(/\D/g, '').slice(0, 10);
		if (digits.length === 0) { return ''; }
		if (digits.length < 4)  { return '(' + digits; }
		if (digits.length < 7)  { return '(' + digits.slice(0, 3) + ') ' + digits.slice(3); }
		return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
	}

	if (phoneInput) {
		phoneInput.addEventListener('input', function () {
			phoneInput.value = maskPhone(phoneInput.value);
		});
	}

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
					setFieldError(key, errors[key]);
					hadFieldError = true;
				}
				if (errors._form) {
					setFormError(errors._form);
				} else if (!hadFieldError) {
					setFormError('Something went wrong. Please try again.');
				}
				fire('form_error');
			}).catch(function (err) {
				submitBtn.removeAttribute('aria-busy');
				submitBtn.disabled = false;
				console.error('[BSPE Connect] Network error during form submit. Endpoint:', endpoint, ' Error:', err);
				setFormError('Network error. Please try again.');
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
