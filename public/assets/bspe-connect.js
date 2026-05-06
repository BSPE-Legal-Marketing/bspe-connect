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

	var threshold = parseInt(data.scrollThreshold, 10);
	if (!isFinite(threshold) || threshold < 0) { threshold = 200; }

	var hideOnUp = data.hideOnScrollUp !== false;
	var bubbleEnabled = bubbleData.enabled !== false;
	var bubbleTrigger = bubbleData.trigger || 'auto';
	var bubbleRepeat = bubbleData.repeat || 'session';
	var bubbleDelay = parseInt(bubbleData.delay, 10);
	if (!isFinite(bubbleDelay) || bubbleDelay < 0) { bubbleDelay = 3; }
	bubbleDelay *= 1000;

	var lastY = window.pageYOffset || 0;
	var ticking = false;
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

	function handleScroll() {
		var y = window.pageYOffset || 0;
		var delta = y - lastY;
		var direction = delta > 0 ? 'down' : 'up';

		if (y > threshold && direction === 'down') {
			setBarState(true);
		} else if (hideOnUp && direction === 'up' && Math.abs(delta) > 4 && y > 0) {
			setBarState(false);
		}

		lastY = y;
	}

	window.addEventListener('scroll', function () {
		if (ticking) { return; }
		window.requestAnimationFrame(function () {
			handleScroll();
			ticking = false;
		});
		ticking = true;
	}, { passive: true });

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
		if (!restEndpoint) { return; }
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
			}).catch(function () { /* swallow — analytics is best-effort */ });
		} catch (e) { /* swallow */ }
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

			fetch(form.action, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			}).then(function (response) {
				return response.json().catch(function () { return { success: false, data: { errors: { _form: '' } } }; })
					.then(function (payload) {
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
			}).catch(function () {
				submitBtn.removeAttribute('aria-busy');
				submitBtn.disabled = false;
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

	// Initial check — page may already be scrolled past the threshold.
	handleScroll();

	} // end boot()
})();
