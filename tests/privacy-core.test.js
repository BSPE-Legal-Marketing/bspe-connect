// Run: node --test tests/*.test.js
// Pure decision logic of public/assets/privacy-guard.js (no browser needed).
const test = require('node:test');
const assert = require('node:assert/strict');
const core = require('../public/assets/privacy-guard.js');

function memStore(initial) {
	const m = new Map(initial ? Object.entries(initial) : []);
	return { getItem: (k) => (m.has(k) ? m.get(k) : null), setItem: (k, v) => m.set(k, String(v)) };
}
const NOW = Date.UTC(2026, 10, 2);

test('state table: who is blocked', () => {
	const rows = [
		// profile, choice, gpc, blocked
		['us', null, false, false],
		['us', 'ok', false, false],
		['us', 'optout', false, true],
		['us', null, true, true],
		['us', 'ok', true, true],
		['us_ca', null, false, false],
		['us_ca', 'optout', false, true],
		['strict', null, false, true],
		['strict', 'accept', false, false],
		['strict', 'reject', false, true],
		['strict', 'accept', true, true],
		['strict', 'gpc_ack', false, true],
		['us', 'gpc_ack', false, false],
	];
	for (const [profile, choice, gpc, blocked] of rows) {
		assert.equal(core.isBlocked(profile, choice, gpc), blocked, `${profile}/${choice}/gpc=${gpc}`);
	}
});

test('stored choice round-trips and expires after 12 months', () => {
	const s = memStore();
	assert.equal(core.readChoice(s, NOW), null);
	core.writeChoice(s, 'optout', NOW);
	assert.equal(core.readChoice(s, NOW + 1000), 'optout');
	assert.equal(core.readChoice(s, NOW + core.YEAR_MS - 1), 'optout');
	assert.equal(core.readChoice(s, NOW + core.YEAR_MS + 1), null);
});

test('garbage or foreign values read as no choice', () => {
	assert.equal(core.readChoice(memStore({ bspe_privacy: '{not json' }), NOW), null);
	assert.equal(core.readChoice(memStore({ bspe_privacy: '{"v":1,"choice":"yes","ts":1}' }), NOW), null);
	assert.equal(core.readChoice(memStore({ bspe_privacy: '{"v":2,"choice":"ok","ts":1}' }), NOW), null);
	// A timestamp from the future is not trusted.
	assert.equal(core.readChoice(memStore({ bspe_privacy: JSON.stringify({ v: 1, choice: 'ok', ts: NOW + 5 * 864e5 }) }), NOW), null);
	const throwing = { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } };
	assert.equal(core.readChoice(throwing, NOW), null);
	assert.equal(core.writeChoice(throwing, 'ok', NOW), false);
});

test('notice: 20 s in, once per session, waits for overlays', () => {
	const base = { choice: null, gpc: false, shownThisSession: false, elapsedMs: 20000, overlayOpen: false };
	assert.equal(core.shouldShowNotice(base), true);
	assert.equal(core.shouldShowNotice({ ...base, elapsedMs: 19999 }), false);
	assert.equal(core.shouldShowNotice({ ...base, elapsedMs: 0 }), false);
	assert.equal(core.shouldShowNotice({ ...base, shownThisSession: true }), false);
	assert.equal(core.shouldShowNotice({ ...base, overlayOpen: true }), false);
	assert.equal(core.shouldShowNotice({ ...base, choice: 'ok' }), false);
	assert.equal(core.shouldShowNotice({ ...base, choice: 'optout' }), false);
});

test('notice with GPC: shown once to acknowledge, then not again', () => {
	const base = { choice: null, gpc: true, shownThisSession: false, elapsedMs: 25000, overlayOpen: false };
	assert.equal(core.shouldShowNotice(base), true);
	assert.equal(core.shouldShowNotice({ ...base, choice: 'gpc_ack' }), false);
	assert.equal(core.shouldShowNotice({ ...base, choice: 'ok' }), true);
	// GPC switched off later: an old GPC acknowledgement is not a choice.
	assert.equal(core.shouldShowNotice({ ...base, gpc: false, choice: 'gpc_ack' }), true);
});

test('tracker matching by src and inline snippet', () => {
	const cases = [
		['https://www.clarity.ms/tag/abc123', '', 'clarity'],
		['https://scripts.clarity.ms/0.8.9/clarity.js', '', 'clarity'],
		['', '(function(c,l,a,r,i,t,y){t.src="https://www.clarity.ms/tag/"+i;})(window,document,"clarity","script","x");', 'clarity'],
		['//cdn.callrail.com/companies/123/abc/12/swap.js', '', 'callrail'],
		['https://connect.facebook.net/en_US/fbevents.js', '', 'pixel'],
		['https://connect.facebook.net/signals/config/123?v=2.9', '', 'pixel'],
		['', "!function(f,b,e,v,n,t,s){t.src=v}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');", 'pixel'],
		['https://www.googletagmanager.com/gtag/js?id=G-XXXX', '', 'gtag'],
		['https://www.googletagmanager.com/gtm.js?id=GTM-XXXX', '', null],
		['', "(function(w,d,s,l,i){j.src='https://www.googletagmanager.com/gtm.js?id='+i})", null],
		['https://example.com/wp-content/themes/x/swap.js', '', null],
		['https://notclarity.msn.com/x.js', '', null],
		['https://site.com/clarity.ms.js', '', null],
	];
	for (const [src, text, want] of cases) {
		assert.equal(core.trackerOf(src, text), want, src || text.slice(0, 40));
	}
});

test('pixel-off paths: globs, trailing slash, language prefix', () => {
	const globs = ['/contact*', '/free-consultation*', '', '  '];
	assert.equal(core.pathMatches(globs, '/contact/'), true);
	assert.equal(core.pathMatches(globs, '/contact-us/'), true);
	assert.equal(core.pathMatches(globs, '/es/contact-us/'), true);
	assert.equal(core.pathMatches(globs, '/free-consultation'), true);
	assert.equal(core.pathMatches(globs, '/'), false);
	assert.equal(core.pathMatches(globs, '/blog/contact-tips/'), false);
	assert.equal(core.pathMatches(['/exact-page'], '/exact-page/'), true);
	assert.equal(core.pathMatches(['/a.b(c)'], '/a.b(c)'), true);
	assert.equal(core.pathMatches(['/a.b'], '/axb'), false);
	assert.equal(core.pathMatches(undefined, '/x'), false);
});

test('what gets held', () => {
	assert.equal(core.shouldBlock(null, true, true), false);
	assert.equal(core.shouldBlock('clarity', false, true), false);
	assert.equal(core.shouldBlock('clarity', true, false), true);
	assert.equal(core.shouldBlock('pixel', false, true), true);
	assert.equal(core.shouldBlock('pixel', false, false), false);
	assert.equal(core.shouldBlock('gtag', true, false), true);
});
