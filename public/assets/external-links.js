/**
 * BSPE Connect — external link rewriter.
 *
 * On DOM ready, scan every <a href> on the page, identify links to a
 * different hostname than the current site, and set target="_blank" +
 * rel="noopener noreferrer" so they open in a new tab without leaking
 * the originating URL or letting the new tab access window.opener.
 *
 * Skips:
 *   - anchors with explicit data-bspe-keep-self (escape hatch)
 *   - mailto: / tel: / javascript: / data: protocols
 *   - same-host links (including subdomains? optional — see below)
 *
 * "External" here means the registrable domain differs, so
 * www.example.com and staging.example.com are still treated as
 * internal relative to example.com.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	// Drop subdomain to compare registrable domain. Simplified — handles
	// example.com, .co.uk, .com.au type two-segment TLDs.
	function registrable(host) {
		if (!host) return '';
		host = host.toLowerCase();
		var multi = { 'co.uk': 1, 'co.nz': 1, 'co.za': 1, 'com.au': 1, 'com.br': 1, 'co.jp': 1 };
		var parts = host.split('.');
		if (parts.length <= 2) return parts.join('.');
		var lastTwo = parts.slice(-2).join('.');
		if (multi[lastTwo] && parts.length >= 3) return parts.slice(-3).join('.');
		return lastTwo;
	}

	ready(function () {
		var siteHost = registrable(window.location.hostname);
		var anchors = document.querySelectorAll('a[href]');

		for (var i = 0; i < anchors.length; i++) {
			var a = anchors[i];

			if (a.hasAttribute('data-bspe-keep-self')) continue;

			var href = a.getAttribute('href') || '';
			// Skip non-http(s) schemes.
			if (/^(mailto:|tel:|javascript:|data:|#)/i.test(href)) continue;

			var url;
			try {
				url = new URL(a.href, window.location.href);
			} catch (e) {
				continue;
			}

			if (!/^https?:$/.test(url.protocol)) continue;

			if (registrable(url.hostname) === siteHost) continue;

			// External link — flip target + rel.
			a.target = '_blank';
			var rel = (a.getAttribute('rel') || '').toLowerCase().split(/\s+/).filter(Boolean);
			if (rel.indexOf('noopener') === -1)   rel.push('noopener');
			if (rel.indexOf('noreferrer') === -1) rel.push('noreferrer');
			a.setAttribute('rel', rel.join(' '));
		}
	});
})();
