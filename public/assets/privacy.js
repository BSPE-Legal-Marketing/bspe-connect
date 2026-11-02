/*
 * BSPE Connect privacy notice card + "Your Privacy Choices" panel.
 *
 * Needs the inline privacy guard (window.bspePrivacy), which owns the
 * visitor's state and applies choices. This file only decides WHEN to ask
 * and draws the card:
 *   - never on arrival: after 20 s of visible time, counted across pages;
 *   - at most once per browser session until the visitor chooses;
 *   - never over an open chat, menu, popup or the bar's form sheet;
 *   - non-blocking: no overlay, no scroll lock, no focus theft.
 */
(function () {
	'use strict';

	var api = window.bspePrivacy;
	if (!api || !api.cfg) { return; }
	var core = api.core;
	var cfg = api.cfg;
	var t = cfg.i18n || {};
	var strict = cfg.profile === 'strict';

	var T_KEY = 'bspe_privacy_t';
	var SHOWN_KEY = 'bspe_privacy_shown';
	var TICK_MS = 1000;

	function sessionGet(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } }
	function sessionSet(k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* private mode */ } }

	// ----- Open overlays we must not cover ------------------------------
	var OVERLAY_BODY_CLASSES = /(^|\s)(dialog-prevent-scroll|pum-open|menu-open|nav-open|mobile-menu-open|mobile-nav-open|off-canvas-open|slideout-open)(\s|$)/;
	var OVERLAY_ELEMENTS = '.elementor-popup-modal, .pum-overlay, .off-canvas.is-open, dialog[open], [role="dialog"][aria-modal="true"]';
	function isShown(el) {
		var cs = getComputedStyle(el);
		if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity || '1') < 0.05) { return false; }
		var r = el.getBoundingClientRect();
		return r.width > 0 && r.height > 0;
	}
	function overlayOpen() {
		var de = document.documentElement, b = document.body;
		if (de.classList.contains('bspe-menu-open') || de.classList.contains('bspe-chat-open')) { return true; }
		var icw = document.getElementById('icw');
		if (icw && icw.classList.contains('icw--open')) { return true; }
		if (document.querySelector('[data-bspe-modal]:not([hidden])')) { return true; }
		if (b && OVERLAY_BODY_CLASSES.test(b.className)) { return true; }
		var els = document.querySelectorAll(OVERLAY_ELEMENTS);
		for (var i = 0; i < els.length; i++) {
			if (!card || !card.contains(els[i])) {
				if (isShown(els[i])) { return true; }
			}
		}
		return false;
	}

	// ----- Placement: above the bar and any bottom-corner launcher -------
	var FLOATERS = '#icw:not(.icw--open), #icw--call--content, .userway_buttons_wrapper, #bspe-connect [data-bspe-state]:not([data-bspe-state="hidden"]):not([data-bspe-modal])';
	function place() {
		if (!card) { return; }
		var vh = window.innerHeight;
		var mobile = window.innerWidth < 768;
		var bar = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--bspe-bar-h')) || 0;
		var bottom = mobile ? bar : 0;
		var cardRect = card.getBoundingClientRect();
		var els = document.querySelectorAll(FLOATERS);
		for (var i = 0; i < els.length; i++) {
			if (!isShown(els[i])) { continue; }
			var r = els[i].getBoundingClientRect();
			var overlapsX = r.left < cardRect.right && r.right > cardRect.left;
			if (overlapsX && r.top > vh * 0.45 && r.top < vh) { bottom = Math.max(bottom, vh - r.top); }
		}
		card.style.bottom = (bottom + (mobile ? 12 : 16)) + 'px';
	}

	// ----- Card ----------------------------------------------------------
	var card = null;
	var hideTimer = 0;

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (text) { e.textContent = text; }
		return e;
	}
	function button(label, kind, onClick) {
		var b = el('button', 'bspe-privacy__btn bspe-privacy__btn--' + kind, label);
		b.type = 'button';
		b.addEventListener('click', onClick);
		return b;
	}
	function policyLink() {
		if (!cfg.policyUrl) { return null; }
		var a = el('a', 'bspe-privacy__link', t.policy || 'Privacy Policy');
		a.href = cfg.policyUrl;
		a.rel = 'nofollow';
		return a;
	}

	function close() {
		clearTimeout(hideTimer);
		if (card && card.parentNode) { card.parentNode.removeChild(card); }
		card = null;
	}

	function choose(c, after) {
		api.setChoice(c);
		if (after) { render(after); } else { close(); }
	}

	// mode: 'notice' (first ask), 'choices' (reopened from a link), 'done' (confirmation)
	function render(mode) {
		var s = api.state();
		close();
		card = el('section', 'bspe-privacy' + (strict ? ' bspe-privacy--strict' : ''));
		card.setAttribute('role', 'region');
		card.setAttribute('aria-label', t.regionLabel || 'Privacy choices');
		card.setAttribute('data-mode', mode);

		var text = el('p', 'bspe-privacy__text');
		var links = el('p', 'bspe-privacy__links');
		var actions = el('div', 'bspe-privacy__actions');
		var dismiss = null; // what ✕ / Esc means; null = no ✕

		if (mode === 'done') {
			text.textContent = t.optedOut || "You've opted out. Analytics and ad tracking stop from your next page.";
			actions.appendChild(button(t.close || 'Close', 'secondary', close));
			hideTimer = setTimeout(close, 8000);
		} else if (s.gpc) {
			text.textContent = t.gpc || "Your browser's Global Privacy Control signal is on, so analytics and ad tracking are off on this site.";
			dismiss = function () { choose('gpc_ack'); };
			actions.appendChild(button(t.ok || 'OK', 'primary', dismiss));
		} else if (strict) {
			text.textContent = mode === 'choices' ? statusText(s) : (t.noticeStrict || "We'd like to use cookies and analytics to improve this site.");
			actions.appendChild(button(t.accept || 'Accept', 'equal', function () { choose('accept'); }));
			actions.appendChild(button(t.reject || 'Reject', 'equal', function () { choose('reject'); }));
			if (mode === 'choices') { dismiss = close; }
		} else {
			text.textContent = mode === 'choices' ? statusText(s) : (t.notice || 'We use cookies and analytics to improve this site.');
			if (mode === 'choices') {
				actions.appendChild(button(t.allow || 'Allow', s.blocked ? 'secondary' : 'primary', function () { choose('ok'); }));
				dismiss = close;
			} else {
				dismiss = function () { choose('ok'); };
				actions.appendChild(button(t.ok || 'OK', 'primary', dismiss));
			}
			actions.appendChild(button(t.optOut || 'Opt out', s.blocked ? 'primary' : 'secondary', function () { choose('optout', 'done'); }));
		}

		var p = policyLink();
		if (p && mode !== 'done') { links.appendChild(p); }
		if (cfg.profile === 'us_ca' && !s.gpc && mode !== 'done') {
			if (links.firstChild) { links.appendChild(document.createTextNode(' · ')); }
			var dns = el('a', 'bspe-privacy__link', t.dns || 'Do Not Sell or Share My Personal Information');
			dns.href = '#';
			dns.addEventListener('click', function (e) { e.preventDefault(); choose('optout', 'done'); });
			links.appendChild(dns);
		}

		if (dismiss || mode === 'done') {
			var x = button('×', 'close', dismiss || close);
			x.setAttribute('aria-label', t.closeLabel || 'Close');
			card.appendChild(x);
		}
		card.appendChild(text);
		if (links.firstChild) { card.appendChild(links); }
		card.appendChild(actions);
		card.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { (dismiss || close)(); }
		});
		document.body.appendChild(card);
		place();
		if (mode === 'choices') {
			var first = actions.querySelector('button');
			if (first) { first.focus(); }
		}
	}

	function statusText(s) {
		if (s.blocked) { return t.statusOff || 'Analytics and ad tracking are off for you on this site.'; }
		return t.statusOn || 'Analytics and ad tracking are on. You can opt out at any time.';
	}

	// ----- Open from links ------------------------------------------------
	api.open = function () { render('choices'); };
	if (api.openRequested || window.location.hash === '#privacy-choices') { api.open(); }

	// ----- Timer: 20 s of visible time across pages ----------------------
	var elapsed = parseInt(sessionGet(T_KEY) || '0', 10) || 0;
	var last = Date.now();
	var shownHere = false;
	var timer = setInterval(function () {
		var now = Date.now();
		if (document.visibilityState === 'visible') {
			elapsed += Math.min(now - last, 2 * TICK_MS);
			sessionSet(T_KEY, String(elapsed));
		}
		last = now;
		var s = api.state();

		if (card && shownHere) {
			// Step aside while a chat / menu / popup is open; come back after.
			var hide = overlayOpen();
			card.style.display = hide ? 'none' : '';
			if (!hide) { place(); }
			return;
		}
		if (shownHere && !card) { clearInterval(timer); return; }
		if (card) { return; } // a reopened panel is up
		if (!core.needsNotice(s.choice, s.gpc) || sessionGet(SHOWN_KEY) === '1') { clearInterval(timer); return; }
		if (core.shouldShowNotice({
			choice: s.choice,
			gpc: s.gpc,
			shownThisSession: sessionGet(SHOWN_KEY) === '1',
			elapsedMs: elapsed,
			overlayOpen: overlayOpen()
		})) {
			sessionSet(SHOWN_KEY, '1');
			shownHere = true;
			render('notice');
		}
	}, TICK_MS);

	window.addEventListener('resize', place, { passive: true });
})();
