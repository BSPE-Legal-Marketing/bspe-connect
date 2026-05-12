/**
 * BSPE Connect — QR indexer.
 *
 * Renders a QR code as inline SVG inside any #qri-code element on
 * the page. The QR encodes the post permalink so a visitor can scan
 * it to keep the URL on their phone.
 *
 * Self-contained: no external HTTP, no CDN, no iframes. The encoder
 * implements byte-mode QR for versions 1-10 with error-correction
 * level L, which covers any reasonable URL (up to 271 ASCII chars).
 *
 * Reads the URL + visual size from data-* attributes on the
 * container; the WP-side PHP class injects those.
 */
(function () {
	'use strict';

	// ── QR encoder ────────────────────────────────────────────────
	//
	// Standard ISO/IEC 18004 QR Code algorithm, byte mode only.
	// Supports versions 1-10 (21x21 → 57x57). Error correction L.
	//
	// Implementation notes:
	//   - GF(256) tables built once at module load
	//   - Mask 0 is applied unconditionally (skip the penalty-eval
	//     loop). Mask 0 is the simplest "(row + col) % 2 == 0" mask
	//     and produces scannable codes for every input we care about.
	//   - Output is an inline SVG string with one <path> per dark cell
	//     row, so the DOM stays small (~1 path per row vs N rects).

	var GF_EXP = new Uint8Array(512);
	var GF_LOG = new Uint8Array(256);
	(function buildGf() {
		var x = 1;
		for (var i = 0; i < 255; i++) {
			GF_EXP[i] = x;
			GF_LOG[x] = i;
			x <<= 1;
			if (x & 0x100) x ^= 0x11D;
		}
		for (var j = 255; j < 512; j++) GF_EXP[j] = GF_EXP[j - 255];
	})();

	// EC codewords per version, level L (versions 1..10).
	var EC_BYTES_L     = [7, 10, 15, 20, 26, 18, 20, 24, 30, 18];
	// Total codewords per version, level L.
	var TOTAL_BYTES_L  = [26, 44, 70, 100, 134, 172, 196, 242, 292, 346];
	// Number of EC blocks per version, level L.
	var BLOCKS_L       = [1, 1, 1, 1, 1, 2, 2, 2, 2, 4];
	// Capacity in bytes (mode 4 = byte) per version, level L.
	var BYTE_CAPACITY_L = [17, 32, 53, 78, 106, 134, 154, 192, 230, 271];

	// Alignment pattern center positions per version (1-10).
	var ALIGN_POSITIONS = [
		[],
		[6, 18],
		[6, 22],
		[6, 26],
		[6, 30],
		[6, 34],
		[6, 22, 38],
		[6, 24, 42],
		[6, 26, 46],
		[6, 28, 50],
	];

	function rsGeneratorPoly(degree) {
		var poly = [1];
		for (var i = 0; i < degree; i++) {
			var next = new Array(poly.length + 1).fill(0);
			for (var j = 0; j < poly.length; j++) {
				next[j]     ^= poly[j];
				next[j + 1] ^= GF_EXP[(GF_LOG[poly[j]] + i) % 255];
			}
			poly = next;
		}
		return poly;
	}

	function rsEncode(data, ecCount) {
		var poly = rsGeneratorPoly(ecCount);
		var buf = data.slice().concat(new Array(ecCount).fill(0));
		for (var i = 0; i < data.length; i++) {
			var coef = buf[i];
			if (coef !== 0) {
				for (var j = 0; j < poly.length; j++) {
					buf[i + j] ^= GF_EXP[GF_LOG[coef] + GF_LOG[poly[j]]];
				}
			}
		}
		return buf.slice(data.length);
	}

	function selectVersion(byteLen) {
		for (var v = 1; v <= 10; v++) {
			if (byteLen <= BYTE_CAPACITY_L[v - 1]) return v;
		}
		return 0;
	}

	function utf8Bytes(str) {
		var out = [];
		for (var i = 0; i < str.length; i++) {
			var c = str.charCodeAt(i);
			if (c < 0x80) {
				out.push(c);
			} else if (c < 0x800) {
				out.push(0xC0 | (c >> 6), 0x80 | (c & 0x3F));
			} else if (c < 0xD800 || c >= 0xE000) {
				out.push(0xE0 | (c >> 12), 0x80 | ((c >> 6) & 0x3F), 0x80 | (c & 0x3F));
			} else {
				// surrogate pair
				i++;
				c = 0x10000 + (((c & 0x3FF) << 10) | (str.charCodeAt(i) & 0x3FF));
				out.push(0xF0 | (c >> 18), 0x80 | ((c >> 12) & 0x3F), 0x80 | ((c >> 6) & 0x3F), 0x80 | (c & 0x3F));
			}
		}
		return out;
	}

	function encode(text) {
		var bytes = utf8Bytes(text);
		var version = selectVersion(bytes.length);
		if (version === 0) return null;
		var size = 17 + 4 * version;
		var totalBytes = TOTAL_BYTES_L[version - 1];
		var ecBytes    = EC_BYTES_L[version - 1];
		var blocks     = BLOCKS_L[version - 1];
		var dataBytes  = totalBytes - ecBytes * blocks;

		// Build the bit stream.
		var bits = [];
		function pushBits(value, len) {
			for (var k = len - 1; k >= 0; k--) bits.push((value >> k) & 1);
		}
		pushBits(0b0100, 4);                         // byte mode
		pushBits(bytes.length, version <= 9 ? 8 : 16);
		for (var i = 0; i < bytes.length; i++) pushBits(bytes[i], 8);

		// Terminator + pad to byte boundary.
		var maxBits = dataBytes * 8;
		for (var t = 0; t < 4 && bits.length < maxBits; t++) bits.push(0);
		while (bits.length % 8) bits.push(0);

		// Pad bytes — alternate 0xEC / 0x11 starting with 0xEC.
		var padToggle = 0;
		while (bits.length / 8 < dataBytes) {
			var padByte = (padToggle === 0) ? 0xEC : 0x11;
			padToggle = 1 - padToggle;
			for (var b = 7; b >= 0; b--) bits.push((padByte >> b) & 1);
		}

		// Group bits into bytes
		var data = [];
		for (var p = 0; p < bits.length; p += 8) {
			var byte = 0;
			for (var q = 0; q < 8; q++) byte = (byte << 1) | bits[p + q];
			data.push(byte);
		}

		// Reed-Solomon for each block.
		var bytesPerBlock = Math.floor(dataBytes / blocks);
		var remainder     = dataBytes - bytesPerBlock * blocks;
		var dataBlocks = [];
		var ecBlocks   = [];
		var offset = 0;
		for (var bn = 0; bn < blocks; bn++) {
			var blen = bytesPerBlock + (bn >= blocks - remainder ? 1 : 0);
			var block = data.slice(offset, offset + blen);
			dataBlocks.push(block);
			ecBlocks.push(rsEncode(block, ecBytes));
			offset += blen;
		}

		// Interleave data + EC
		var codewords = [];
		var maxDataLen = 0;
		for (var d = 0; d < dataBlocks.length; d++) {
			if (dataBlocks[d].length > maxDataLen) maxDataLen = dataBlocks[d].length;
		}
		for (var col = 0; col < maxDataLen; col++) {
			for (var bk = 0; bk < dataBlocks.length; bk++) {
				if (col < dataBlocks[bk].length) codewords.push(dataBlocks[bk][col]);
			}
		}
		for (var ec = 0; ec < ecBytes; ec++) {
			for (var bk2 = 0; bk2 < ecBlocks.length; bk2++) {
				codewords.push(ecBlocks[bk2][ec]);
			}
		}

		// Build module matrix.
		var m = []; // m[r][c]: 0 = light, 1 = dark, null = unset
		var reserved = []; // function pattern cells (not data)
		for (var r = 0; r < size; r++) {
			m.push(new Array(size).fill(null));
			reserved.push(new Array(size).fill(false));
		}

		function placeFinder(rr, cc) {
			for (var dr = -1; dr <= 7; dr++) for (var dc = -1; dc <= 7; dc++) {
				var rrr = rr + dr, ccc = cc + dc;
				if (rrr < 0 || ccc < 0 || rrr >= size || ccc >= size) continue;
				var v = 0;
				if (dr >= 0 && dr <= 6 && dc >= 0 && dc <= 6) {
					if (dr === 0 || dr === 6 || dc === 0 || dc === 6) v = 1;
					else if (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4) v = 1;
				}
				m[rrr][ccc] = v;
				reserved[rrr][ccc] = true;
			}
		}
		placeFinder(0, 0);
		placeFinder(0, size - 7);
		placeFinder(size - 7, 0);

		// Timing patterns.
		for (var ti = 8; ti < size - 8; ti++) {
			m[6][ti] = (ti % 2 === 0) ? 1 : 0;
			m[ti][6] = (ti % 2 === 0) ? 1 : 0;
			reserved[6][ti] = true;
			reserved[ti][6] = true;
		}

		// Dark module (required by spec).
		m[size - 8][8] = 1;
		reserved[size - 8][8] = true;

		// Format info reserved area (filled later).
		for (var fi = 0; fi < 9; fi++) {
			reserved[8][fi] = true;
			reserved[fi][8] = true;
		}
		for (var fi2 = 0; fi2 < 8; fi2++) {
			reserved[8][size - 1 - fi2] = true;
			reserved[size - 1 - fi2][8] = true;
		}

		// Alignment patterns (v2+).
		var aligns = ALIGN_POSITIONS[version - 1];
		for (var ai = 0; ai < aligns.length; ai++) {
			for (var aj = 0; aj < aligns.length; aj++) {
				var ar = aligns[ai], ac = aligns[aj];
				// Skip alignment patterns that overlap finders.
				if ((ar < 8 && ac < 8) || (ar < 8 && ac >= size - 8) || (ar >= size - 8 && ac < 8)) continue;
				for (var dr2 = -2; dr2 <= 2; dr2++) for (var dc2 = -2; dc2 <= 2; dc2++) {
					var av = 0;
					if (Math.max(Math.abs(dr2), Math.abs(dc2)) === 2) av = 1;
					else if (dr2 === 0 && dc2 === 0) av = 1;
					m[ar + dr2][ac + dc2] = av;
					reserved[ar + dr2][ac + dc2] = true;
				}
			}
		}

		// Place data in zigzag.
		var dataIdx = 0, bitIdx = 7;
		for (var colStart = size - 1; colStart > 0; colStart -= 2) {
			if (colStart === 6) colStart--; // skip timing column
			for (var rowIdx = 0; rowIdx < size; rowIdx++) {
				for (var k = 0; k < 2; k++) {
					var col = colStart - k;
					var row = ((colStart + 1) & 2) === 0 ? size - 1 - rowIdx : rowIdx;
					if (!reserved[row][col]) {
						var bit = 0;
						if (dataIdx < codewords.length) {
							bit = (codewords[dataIdx] >> bitIdx) & 1;
							bitIdx--;
							if (bitIdx < 0) { bitIdx = 7; dataIdx++; }
						}
						// Mask 0: invert when (row + col) % 2 == 0.
						if (((row + col) % 2) === 0) bit ^= 1;
						m[row][col] = bit;
					}
				}
			}
		}

		// Format info — error level L (01) + mask 0 (000) = 01000.
		// BCH-encoded format string for level L mask 0 is 0b111011111000100.
		var formatBits = 0x77C4;
		for (var f = 0; f < 15; f++) {
			var bit = (formatBits >> f) & 1;

			// Primary copy — around top-left finder.
			//   bits 0-5 : row 8, cols 0-5
			//   bit 6    : row 8, col 7  (col 6 is timing)
			//   bit 7    : row 8, col 8
			//   bit 8    : row 7, col 8
			//   bits 9-14: row 5,4,3,2,1,0, col 8 (row 6 is timing)
			if (f < 6)        m[8][f]       = bit;
			else if (f < 8)   m[8][f + 1]   = bit;
			else if (f === 8) m[7][8]       = bit;
			else              m[14 - f][8]  = bit;

			// Secondary copy — split between bottom-left and right of
			// top-right finder.
			//   bits 0-6 : col 8, rows size-1 down to size-7
			//   bits 7-14: row 8, cols size-8 to size-1
			//
			// Note: m[size-8][8] is the dark module (always 1) and is
			// NOT part of the format info. The cutoff is therefore at
			// f === 7, not f === 8.
			if (f < 7) m[size - 1 - f][8]    = bit;
			else       m[8][size - 15 + f]   = bit;
		}

		// Re-affirm the dark module after format info placement —
		// belt-and-suspenders in case any reservation logic missed it.
		m[size - 8][8] = 1;

		return { matrix: m, size: size };
	}

	// ── Render ─────────────────────────────────────────────────────

	function renderSvg(qr, pxSize) {
		var size = qr.size;
		// QR spec requires 4 modules of quiet zone around the symbol.
		// With less, many scanners refuse to detect the code at all.
		var pad = 4;
		var totalModules = size + pad * 2;
		var cell = pxSize / totalModules;

		var rects = '';
		for (var r = 0; r < size; r++) {
			var runStart = -1;
			for (var c = 0; c <= size; c++) {
				var on = c < size && qr.matrix[r][c] === 1;
				if (on && runStart < 0) runStart = c;
				if (!on && runStart >= 0) {
					rects +=
						'<rect x="' + ((runStart + pad) * cell).toFixed(3) +
						'" y="' + ((r + pad) * cell).toFixed(3) +
						'" width="' + ((c - runStart) * cell).toFixed(3) +
						'" height="' + cell.toFixed(3) + '"/>';
					runStart = -1;
				}
			}
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + pxSize + ' ' + pxSize + '" ' +
			'width="' + pxSize + '" height="' + pxSize + '" shape-rendering="crispEdges" ' +
			'role="img" aria-label="QR code for this page">' +
			'<rect width="' + pxSize + '" height="' + pxSize + '" fill="#fff"/>' +
			'<g fill="#000">' + rects + '</g></svg>';
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else { fn(); }
	}

	ready(function () {
		var nodes = document.querySelectorAll('[data-bspe-qri]');
		for (var i = 0; i < nodes.length; i++) {
			var node = nodes[i];
			var url  = node.getAttribute('data-url') || window.location.href;
			var size = parseInt(node.getAttribute('data-size'), 10);
			if (!isFinite(size) || size < 80) size = 150;
			var qr = encode(url);
			if (!qr) {
				// URL too long for our version cap. Silently skip rather
				// than rendering a broken QR.
				continue;
			}
			// Wrap the SVG in an anchor so:
			//   - hovering shows the destination URL (browser tooltip
			//     from title + native href hover)
			//   - clicking on desktop opens the page
			//   - phone camera scanning still hits the SAME URL because
			//     that's what the QR encodes
			var svg = renderSvg(qr, size);
			var anchor =
				'<a href="' + escapeAttr(url) + '" title="' + escapeAttr(url) + '" ' +
				'class="bspe-qri-link" rel="bookmark">' +
				svg +
				'</a>';
			node.innerHTML = anchor;
		}
	});

	function escapeAttr(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
})();
