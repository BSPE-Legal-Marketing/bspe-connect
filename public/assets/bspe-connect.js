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
	function fire(eventType, detail) {
		try {
			window.dispatchEvent(new CustomEvent('bspe:' + eventType, {
				detail: Object.assign({ type: eventType }, detail || {})
			}));
		} catch (e) { /* ignore */ }
		// Phase 5 will deliver these to /event REST endpoint here.
	}

	// Initial check — page may already be scrolled past the threshold.
	handleScroll();

	} // end boot()
})();
