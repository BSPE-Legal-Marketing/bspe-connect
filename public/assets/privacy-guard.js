/*
 * BSPE Connect privacy guard. Printed INLINE as the first thing in <head>
 * (class-privacy.php) so it runs before any tracker. Every decision is made
 * here in the browser, so the cached HTML is identical for every visitor
 * (NitroPack / LiteSpeed safe).
 *
 * Blocked state (opt-out, GPC, or Strict profile before Accept):
 *   - Consent Mode v2 default = denied, Clarity consent = false;
 *   - Clarity, CallRail, Meta Pixel and direct gtag.js scripts are turned
 *     into type="text/plain" before they run, whether the parser or GTM
 *     inserts them. GTM itself keeps loading; its GA4 tags obey Consent Mode.
 * Always (whatever the choice): Clarity masks form + chat input, Meta Pixel
 * is held on the configured form paths and its autoConfig is switched off.
 *
 * Lines starting with // are stripped before printing; keep comments on
 * their own lines.
 */
var bspePrivacyCore = (function () {
	'use strict';

	var KEY = 'bspe_privacy';
	var YEAR_MS = 365 * 24 * 60 * 60 * 1000;
	var NOTICE_DELAY_MS = 20000;
	var CHOICES = ['ok', 'optout', 'accept', 'reject', 'gpc_ack'];

	// name -> [src pattern, inline-snippet pattern]
	var TRACKERS = {
		clarity: [/(^|\/\/|\.)clarity\.ms\//i, /clarity\.ms\/tag/i],
		callrail: [/(^|\/\/|\.)callrail\.com\//i, /cdn\.callrail\.com/i],
		pixel: [/connect\.facebook\.net\/[^?]*(fbevents\.js|signals\/)/i, /connect\.facebook\.net\/[^'"]*fbevents\.js/i],
		gtag: [/googletagmanager\.com\/gtag\/js/i, null]
	};

	function readChoice(storage, now) {
		var o;
		try { o = JSON.parse(storage.getItem(KEY) || 'null'); } catch (e) { return null; }
		if (!o || o.v !== 1 || CHOICES.indexOf(o.choice) < 0 || !(o.ts > 0)) { return null; }
		if (now - o.ts > YEAR_MS || o.ts > now + 864e5) { return null; }
		return o.choice;
	}

	function writeChoice(storage, choice, now) {
		try { storage.setItem(KEY, JSON.stringify({ v: 1, choice: choice, ts: now })); return true; } catch (e) { return false; }
	}

	// GPC always wins; an explicit opt-out/reject blocks; Strict blocks until accept.
	function isBlocked(profile, choice, gpc) {
		if (gpc || choice === 'optout' || choice === 'reject') { return true; }
		if (profile === 'strict') { return choice !== 'accept'; }
		return false;
	}

	// Does the visitor still owe us a choice (so the notice should show)?
	function needsNotice(choice, gpc) {
		if (gpc) { return choice !== 'gpc_ack' && choice !== 'optout' && choice !== 'reject'; }
		return choice === null || choice === 'gpc_ack';
	}

	function shouldShowNotice(s) {
		return needsNotice(s.choice, s.gpc) && !s.shownThisSession && s.elapsedMs >= NOTICE_DELAY_MS && !s.overlayOpen;
	}

	// Which tracker (if any) a script belongs to, by src or inline text.
	function trackerOf(src, text) {
		for (var name in TRACKERS) {
			var t = TRACKERS[name];
			if (src && t[0].test(src)) { return name; }
			if (!src && text && t[1] && t[1].test(text)) { return name; }
		}
		return null;
	}

	// "/contact*" style globs, matched with and without a leading language
	// segment (/es/contact-us/ matches /contact*).
	function pathMatches(globs, path) {
		var paths = [path], m = /^\/[a-z]{2}(-[a-z]{2})?(\/.*)$/i.exec(path);
		if (m) { paths.push(m[2]); }
		for (var i = 0; i < (globs || []).length; i++) {
			var g = String(globs[i]).trim();
			if (!g) { continue; }
			var re = new RegExp('^' + g.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '/?$', 'i');
			for (var j = 0; j < paths.length; j++) {
				if (re.test(paths[j])) { return true; }
			}
		}
		return false;
	}

	// Should this tracker be held on this page?
	function shouldBlock(name, blocked, pixelOffHere) {
		if (!name) { return false; }
		if (name === 'pixel' && pixelOffHere) { return true; }
		return blocked;
	}

	return {
		KEY: KEY, YEAR_MS: YEAR_MS, NOTICE_DELAY_MS: NOTICE_DELAY_MS,
		readChoice: readChoice, writeChoice: writeChoice, isBlocked: isBlocked,
		needsNotice: needsNotice, shouldShowNotice: shouldShowNotice,
		trackerOf: trackerOf, pathMatches: pathMatches, shouldBlock: shouldBlock
	};
})();

(function (w, d, core) {
	'use strict';
	var cfg = w && w.BSPE_PRIVACY;
	if (!cfg || !d || w.bspePrivacy) { return; }

	var store = null;
	try { store = w.localStorage; } catch (e) { /* storage blocked: no stored choice */ }
	var gpc = w.navigator && w.navigator.globalPrivacyControl === true;
	var choice = store ? core.readChoice(store, Date.now()) : null;
	var blocked = core.isBlocked(cfg.profile, choice, gpc);
	var pixelOffHere = core.pathMatches(cfg.pixelOff, w.location.pathname);
	var held = [];

	// ----- Consent Mode v2 + Clarity consent ----------------------------
	w.dataLayer = w.dataLayer || [];
	function gtagPush() { w.dataLayer.push(arguments); }
	function consentValues(v) {
		return { ad_storage: v, analytics_storage: v, ad_user_data: v, ad_personalization: v };
	}
	gtagPush('consent', 'default', consentValues(blocked ? 'denied' : 'granted'));
	if (blocked) { gtagPush('set', 'ads_data_redaction', true); }

	function clarityQueue() {
		w.clarity = w.clarity || function () { (w.clarity.q = w.clarity.q || []).push(arguments); };
		return w.clarity;
	}
	function clarityDeny() {
		var c = clarityQueue();
		c('consentv2', { ad_Storage: 'denied', analytics_Storage: 'denied' });
		c('consent', false);
	}
	if (blocked) { clarityDeny(); }

	// Meta Pixel. Site code may call fbq('track', …) while the Pixel is held,
	// so a no-op placeholder answers then (marked, and dropped again on
	// release so the real snippet's `if (f.fbq) return;` does not skip).
	// When the Pixel runs, fbq('set','autoConfig',false,id) is put ahead of
	// every queued fbq('init',id) before fbevents.js (async) reads the
	// queue, so Meta's automatic events never read button or form text.
	var fbqFn = w.fbq;
	function fbqPlaceholder() {
		if (!fbqFn) {
			fbqFn = function () {};
			fbqFn.bspePlaceholder = true;
		}
	}
	if (pixelOffHere) {
		w.fbq = w.fbq || function () {};
	} else {
		try {
			Object.defineProperty(w, 'fbq', {
				configurable: true,
				get: function () { return fbqFn; },
				set: function (fn) {
					fbqFn = fn;
					var patch = function () {
						var q = fn && fn.queue;
						if (!q || !q.length) { return; }
						for (var i = 0; i < q.length; i++) {
							var a = q[i];
							if (a && a[0] === 'init' && a[1] && !(i > 0 && q[i - 1][0] === 'set' && q[i - 1][1] === 'autoConfig')) {
								q.splice(i, 0, ['set', 'autoConfig', false, a[1]]);
								i++;
							}
						}
					};
					Promise.resolve().then(patch);
					setTimeout(patch, 0);
				}
			});
		} catch (e) { /* non-configurable fbq: leave it */ }
		if (blocked) { fbqPlaceholder(); }
	}

	// ----- Script interception ------------------------------------------
	function hold(el) {
		if (el.nodeName !== 'SCRIPT' || el.hasAttribute('data-bspe-privacy')) { return false; }
		var type = el.getAttribute('type');
		if (type && !/^(text|application)\/(java|ecma)script$|^module$/i.test(type)) { return false; }
		var name = core.trackerOf(el.src || el.getAttribute('src'), el.text);
		if (!core.shouldBlock(name, blocked, pixelOffHere)) { return false; }
		el.setAttribute('data-bspe-privacy', name);
		el.setAttribute('data-bspe-type', type || '');
		el.type = 'text/plain';
		if (name !== 'pixel' || !pixelOffHere) { held.push(el); }
		return true;
	}

	function wrap(proto, method) {
		var orig = proto && proto[method];
		if (!orig) { return; }
		proto[method] = function () {
			for (var i = 0; i < arguments.length; i++) {
				var n = arguments[i];
				if (n && n.nodeName === 'SCRIPT') { hold(n); }
			}
			return orig.apply(this, arguments);
		};
	}
	wrap(w.Node && w.Node.prototype, 'appendChild');
	wrap(w.Node && w.Node.prototype, 'insertBefore');
	wrap(w.Element && w.Element.prototype, 'append');
	wrap(w.Element && w.Element.prototype, 'prepend');

	// ----- Clarity masking -----------------------------------------------
	var MASK = 'form, input, textarea, select, [contenteditable], #icw, #icw--call--content, .bspe-connect__modal';
	function mask(el) {
		if (el.nodeType !== 1) { return; }
		if (el.matches && el.matches(MASK)) { el.setAttribute('data-clarity-mask', 'true'); }
		if (el.querySelectorAll && el.firstElementChild) {
			var inner = el.querySelectorAll(MASK);
			for (var i = 0; i < inner.length; i++) { inner[i].setAttribute('data-clarity-mask', 'true'); }
		}
	}

	try {
		new w.MutationObserver(function (records) {
			for (var r = 0; r < records.length; r++) {
				var added = records[r].addedNodes;
				for (var i = 0; i < added.length; i++) {
					if (added[i].nodeName === 'SCRIPT') { hold(added[i]); } else { mask(added[i]); }
				}
			}
		}).observe(d.documentElement, { childList: true, subtree: true });
	} catch (e) { /* no MutationObserver: dynamic-insert wrapper still holds GTM-injected tags */ }
	// Firefox fires this before a parser-inserted script runs.
	d.addEventListener('beforescriptexecute', function (e) {
		if (e.target && e.target.getAttribute('data-bspe-privacy')) { e.preventDefault(); }
	}, true);
	d.addEventListener('DOMContentLoaded', function () { mask(d.documentElement); });

	// ----- "Your Privacy Choices" links -----------------------------------
	d.addEventListener('click', function (e) {
		var a = e.target && e.target.closest && e.target.closest('a[href$="#privacy-choices"]');
		if (!a) { return; }
		e.preventDefault();
		if (api.open) { api.open(); } else { api.openRequested = true; }
	}, true);

	// ----- API used by privacy.js ------------------------------------------
	function release() {
		if (fbqFn && fbqFn.bspePlaceholder) { fbqFn = undefined; }
		for (var i = 0; i < held.length; i++) {
			var old = held[i], s = d.createElement('script');
			for (var j = 0; j < old.attributes.length; j++) {
				var at = old.attributes[j];
				if (!/^(type|data-bspe-privacy|data-bspe-type)$/.test(at.name)) { s.setAttribute(at.name, at.value); }
			}
			if (old.getAttribute('data-bspe-type')) { s.setAttribute('type', old.getAttribute('data-bspe-type')); }
			s.setAttribute('data-bspe-privacy', 'released');
			s.text = old.text;
			if (old.parentNode) { old.parentNode.replaceChild(s, old); }
		}
		held = [];
	}

	var api = w.bspePrivacy = {
		core: core,
		cfg: cfg,
		openRequested: false,
		open: null,
		state: function () {
			return { profile: cfg.profile, choice: choice, gpc: gpc, blocked: blocked };
		},
		// Store a choice and apply what can be applied without a reload.
		setChoice: function (c) {
			if (store) { core.writeChoice(store, c, Date.now()); }
			choice = c;
			var nowBlocked = core.isBlocked(cfg.profile, c, gpc);
			if (nowBlocked) {
				gtagPush('consent', 'update', consentValues('denied'));
				clarityDeny();
				blocked = true;
			} else if (blocked) {
				gtagPush('consent', 'update', consentValues('granted'));
				clarityQueue()('consentv2', { ad_Storage: 'granted', analytics_Storage: 'granted' });
				clarityQueue()('consent');
				blocked = false;
				release();
			}
		}
	};
})(typeof window !== 'undefined' ? window : null, typeof document !== 'undefined' ? document : null, bspePrivacyCore);

if (typeof module === 'object' && module.exports) { module.exports = bspePrivacyCore; }
