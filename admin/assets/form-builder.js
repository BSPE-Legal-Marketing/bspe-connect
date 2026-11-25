/**
 * BSPE Connect — custom (multi-step) form builder for the Form tab.
 *
 * The schema lives in one hidden input (bspe[form][custom_json]); this file
 * renders an editor for it and writes every change back as JSON, firing a
 * `change` event so admin.js dirty-tracking and AJAX save pick it up. The
 * server normalizes and validates on save (Custom_Form::normalize), so the
 * builder only needs to be helpful, not authoritative.
 *
 * DOM is built with createElement + textContent / value — never innerHTML
 * with stored strings.
 */
(function () {
	'use strict';

	var CFG = window.BSPE_CF || {};
	var TYPES = CFG.types || {};
	var ROLES = CFG.roles || {};
	var RESERVED = CFG.reserved || [];
	var CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
	}

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) { return; }
				if (k === 'text') { node.textContent = v; }
				else if (k === 'value') { node.value = v; }
				else if (k === 'checked') { node.checked = !!v; }
				else if (k === 'className') { node.className = v; }
				else { node.setAttribute(k, v === true ? '' : String(v)); }
			});
		}
		(children || []).forEach(function (c) {
			if (c === null || c === undefined) { return; }
			node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return node;
	}

	function keyFromLabel(label) {
		var key = String(label || '').toLowerCase()
			.normalize('NFD').replace(/[̀-ͯ]/g, '')
			.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
		if (key && !/^[a-z]/.test(key)) { key = 'f_' + key; }
		return key.slice(0, 40);
	}

	function optionsToText(options) {
		return (options || []).map(function (o) {
			return (o.label === o.value) ? o.value : (o.label + '|' + o.value);
		}).join('\n');
	}

	function textToOptions(text) {
		var out = [];
		var seen = {};
		String(text || '').split(/\r?\n/).forEach(function (line) {
			line = line.trim();
			if (!line) { return; }
			var label = line, value = line;
			var bar = line.indexOf('|');
			if (bar >= 0) { label = line.slice(0, bar).trim(); value = line.slice(bar + 1).trim(); }
			if (!value || seen[value]) { return; }
			seen[value] = true;
			out.push({ value: value, label: label || value });
		});
		return out;
	}

	function blankField(type) {
		var f = { key: '', type: type || 'text', label: '', placeholder: '', required: false, width: 'full', role: '', help: '', show_label: false, default: '', _auto: true, _open: true };
		if (CHOICE_TYPES.indexOf(f.type) >= 0) { f.options = [{ value: 'Yes', label: 'Yes' }, { value: 'No', label: 'No' }]; }
		return f;
	}

	ready(function () {
		var wrap = document.querySelector('[data-bspe-custom-builder]');
		if (!wrap) { return; }
		var form = wrap.closest('form');
		var hidden = wrap.querySelector('[data-bspe-cf-json]');
		var root = wrap.querySelector('[data-bspe-cf-root]');
		var keysOut = wrap.querySelector('[data-bspe-cf-keys]');
		var jsonEditor = wrap.querySelector('[data-bspe-cf-json-editor]');
		var jsonStatus = wrap.querySelector('[data-bspe-cf-json-status]');
		var details = wrap.querySelector('.bspe-cf__json');

		var state;
		try { state = JSON.parse(hidden.value || '{}'); } catch (e) { state = {}; }
		state = normalizeState(state);

		/* ------------------------------------------------ mode toggle */
		var modeInputs = form ? form.querySelectorAll('input[name="bspe[form][mode]"]') : [];
		function currentMode() {
			for (var i = 0; i < modeInputs.length; i++) { if (modeInputs[i].checked) { return modeInputs[i].value; } }
			return 'standard';
		}
		function syncMode() {
			var mode = currentMode();
			document.querySelectorAll('[data-bspe-form-mode-show]').forEach(function (n) {
				n.style.display = n.getAttribute('data-bspe-form-mode-show') === mode ? '' : 'none';
			});
		}
		Array.prototype.forEach.call(modeInputs, function (i) { i.addEventListener('change', syncMode); });
		syncMode();

		/* ------------------------------------------------ state */
		function normalizeState(s) {
			s = s && typeof s === 'object' ? s : {};
			var out = {
				steps: Array.isArray(s.steps) ? s.steps : [],
				next_label: s.next_label || 'Next',
				back_label: s.back_label || 'Back',
				submit_label: s.submit_label || 'Submit',
				show_progress: s.show_progress !== false,
				progress_format: s.progress_format || 'Step {current} of {total}',
				source: s.source && typeof s.source === 'object' && !Array.isArray(s.source) ? s.source : {}
			};
			out.steps = out.steps.map(function (st) {
				st = st && typeof st === 'object' ? st : {};
				return {
					title: st.title || '',
					subtitle: st.subtitle || '',
					fields: (Array.isArray(st.fields) ? st.fields : []).map(function (f) {
						var n = Object.assign(blankField(f.type || 'text'), f);
						n._auto = !f.key;
						n._open = false;
						if (CHOICE_TYPES.indexOf(n.type) >= 0 && !Array.isArray(n.options)) { n.options = []; }
						return n;
					})
				};
			});
			return out;
		}

		function serializable() {
			return {
				steps: state.steps.map(function (st) {
					return {
						title: st.title,
						subtitle: st.subtitle,
						// A field added but never filled in (no key, no label) is a
						// draft row, not a field — leave it out instead of failing the save.
						fields: st.fields.filter(function (f) {
							return String(f.key || '').trim() !== '' || String(f.label || '').trim() !== '';
						}).map(function (f) {
							var o = {};
							Object.keys(f).forEach(function (k) { if (k.charAt(0) !== '_') { o[k] = f[k]; } });
							if (CHOICE_TYPES.indexOf(o.type) < 0) { delete o.options; }
							return o;
						})
					};
				}),
				next_label: state.next_label,
				back_label: state.back_label,
				submit_label: state.submit_label,
				show_progress: state.show_progress,
				progress_format: state.progress_format,
				source: state.source
			};
		}

		function sync() {
			hidden.value = JSON.stringify(serializable());
			if (form) { form.dispatchEvent(new Event('change', { bubbles: true })); }
			renderKeys();
			if (details && details.open && document.activeElement !== jsonEditor) {
				jsonEditor.value = JSON.stringify(serializable(), null, 2);
			}
		}

		function renderKeys() {
			if (!keysOut) { return; }
			var keys = [];
			state.steps.forEach(function (st) {
				st.fields.forEach(function (f) {
					if (!f.key) { return; }
					// A field keyed like a core key (phone, email, message…)
					// normally fills that key, so it is sent once, as itself.
					keys.push(f.key);
				});
			});
			keysOut.textContent = keys.length ? keys.join(', ') : '—';
		}

		function duplicateKeys() {
			var seen = {}, dup = {};
			state.steps.forEach(function (st) {
				st.fields.forEach(function (f) {
					if (!f.key) { return; }
					if (seen[f.key]) { dup[f.key] = true; }
					seen[f.key] = true;
				});
			});
			return dup;
		}

		/* ------------------------------------------------ render */
		function button(text, act, extra) {
			var attrs = Object.assign({ type: 'button', className: 'bspe-cf__icon-btn', 'data-act': act, title: text, 'aria-label': text }, extra || {});
			return el('button', attrs, [text]);
		}

		function labelled(labelText, control, hint) {
			return el('label', { className: 'bspe-cf__control' }, [
				el('span', { className: 'bspe-cf__control-label', text: labelText }),
				control,
				hint ? el('span', { className: 'bspe-cf__hint', text: hint }) : null
			]);
		}

		function input(bind, value, attrs) {
			return el('input', Object.assign({ type: 'text', className: 'bspe-input', 'data-bind': bind, value: value == null ? '' : value }, attrs || {}));
		}

		function select(bind, value, choices, attrs) {
			var s = el('select', Object.assign({ className: 'bspe-input', 'data-bind': bind }, attrs || {}));
			Object.keys(choices).forEach(function (k) {
				var o = el('option', { value: k, text: choices[k] });
				if (String(value) === k) { o.selected = true; }
				s.appendChild(o);
			});
			return s;
		}

		function checkbox(bind, checked, text) {
			return el('label', { className: 'bspe-cf__check' }, [
				el('input', { type: 'checkbox', 'data-bind': bind, checked: !!checked }),
				el('span', { text: text })
			]);
		}

		function render() {
			root.textContent = '';
			var dup = duplicateKeys();

			// Global labels.
			root.appendChild(el('div', { className: 'bspe-cf__globals' }, [
				labelled('Next button', input('next_label', state.next_label, { maxlength: 60 })),
				labelled('Back button', input('back_label', state.back_label, { maxlength: 60 })),
				labelled('Submit button', input('submit_label', state.submit_label, { maxlength: 60 })),
				labelled('Progress text', input('progress_format', state.progress_format, { maxlength: 80 }), '{current} and {total} are replaced.'),
				checkbox('show_progress', state.show_progress, 'Show step progress')
			]));

			if (state.source && state.source.type === 'elementor') {
				root.appendChild(el('p', { className: 'bspe-cf__source', text: 'Imported from Elementor form "' + (state.source.form_name || state.source.form_id || '') + '" (post #' + (state.source.post_id || '?') + ').' }));
			}

			if (!state.steps.length) {
				root.appendChild(el('p', { className: 'bspe-cf__empty', text: 'No steps yet. Import a website form above, or add a step and its fields. Until the form has a field, visitors keep seeing the standard form.' }));
			}

			state.steps.forEach(function (st, si) {
				var stepNode = el('section', { className: 'bspe-cf__step', 'data-step': si }, [
					el('header', { className: 'bspe-cf__step-head' }, [
						el('span', { className: 'bspe-cf__step-badge', text: 'Step ' + (si + 1) }),
						input('steps.' + si + '.title', st.title, { placeholder: 'Step title (optional)', maxlength: 120, className: 'bspe-input bspe-cf__step-title' }),
						el('span', { className: 'bspe-cf__step-actions' }, [
							button('↑', 'step-up', { 'data-step': si, disabled: si === 0 }),
							button('↓', 'step-down', { 'data-step': si, disabled: si === state.steps.length - 1 }),
							button('✕', 'step-remove', { 'data-step': si, title: 'Remove step', 'aria-label': 'Remove step' })
						])
					]),
					input('steps.' + si + '.subtitle', st.subtitle, { placeholder: 'Subtitle (optional)', maxlength: 240, className: 'bspe-input bspe-cf__step-subtitle' })
				]);

				var list = el('div', { className: 'bspe-cf__fields' });
				st.fields.forEach(function (f, fi) {
					list.appendChild(renderField(f, si, fi, st.fields.length, dup));
				});
				stepNode.appendChild(list);
				stepNode.appendChild(el('div', { className: 'bspe-cf__row-actions' }, [
					el('button', { type: 'button', className: 'bspe-button bspe-button--secondary', 'data-act': 'field-add', 'data-step': si, text: '+ Add field' })
				]));
				root.appendChild(stepNode);
			});

			root.appendChild(el('div', { className: 'bspe-cf__row-actions' }, [
				el('button', { type: 'button', className: 'bspe-button bspe-button--secondary', 'data-act': 'step-add', text: '+ Add step' })
			]));
			renderKeys();
		}

		function renderField(f, si, fi, count, dup) {
			var base = 'steps.' + si + '.fields.' + fi + '.';
			var head = el('div', { className: 'bspe-cf__field-head' }, [
				el('span', { className: 'bspe-cf__drag', text: String(fi + 1) }),
				input(base + 'label', f.label, { placeholder: 'Label', maxlength: 200, className: 'bspe-input bspe-cf__field-label' }),
				select(base + 'type', f.type, TYPES, { 'data-rerender': '1' }),
				checkbox(base + 'required', f.required, 'Required'),
				select(base + 'width', f.width, { full: 'Full width', half: 'Half width' }),
				el('span', { className: 'bspe-cf__step-actions' }, [
					button('⋯', 'field-toggle', { 'data-step': si, 'data-field': fi, title: 'More settings', 'aria-label': 'More settings', 'aria-expanded': f._open ? 'true' : 'false' }),
					button('↑', 'field-up', { 'data-step': si, 'data-field': fi, disabled: si === 0 && fi === 0 }),
					button('↓', 'field-down', { 'data-step': si, 'data-field': fi, disabled: si === state.steps.length - 1 && fi === count - 1 }),
					button('✕', 'field-remove', { 'data-step': si, 'data-field': fi, title: 'Remove field', 'aria-label': 'Remove field' })
				])
			]);

			var keyInput = input(base + 'key', f.key, { maxlength: 40, className: 'bspe-input bspe-cf__mono', 'data-key-input': '1', placeholder: 'auto from label' });
			var keyHint = dup[f.key] ? 'This key is used twice — keys must be unique.' : (RESERVED.indexOf(f.key) >= 0 ? 'Also a core webhook key: when this field is the one that fills "' + f.key + '" it is sent once; otherwise it is sent as field_' + f.key + '.' : 'Webhook key. Lowercase letters, digits, _.');

			var more = el('div', { className: 'bspe-cf__field-more' }, [
				labelled('Key', keyInput, keyHint),
				f.type !== 'hidden' && f.type !== 'checkbox' ? labelled('Placeholder', input(base + 'placeholder', f.placeholder, { maxlength: 200, placeholder: 'Defaults to the label' })) : null,
				labelled('Maps to', select(base + 'role', f.role || '', ROLES), 'Fills the lead’s core Name / Phone / Email / Message columns.'),
				CHOICE_TYPES.indexOf(f.type) >= 0 ? labelled('Options', el('textarea', { className: 'bspe-input bspe-cf__mono', rows: 5, 'data-bind': base + 'options', value: optionsToText(f.options) }), 'One per line. "Label|value" when the value sent differs from what visitors see.') : null,
				labelled(f.type === 'hidden' ? 'Value (always sent)' : 'Default value', input(base + 'default', f.default, { maxlength: 500 })),
				f.type !== 'hidden' ? labelled('Help text', input(base + 'help', f.help, { maxlength: 300 })) : null,
				f.type === 'number' ? labelled('Min', input(base + 'min', f.min == null ? '' : f.min, { type: 'number', step: 'any' })) : null,
				f.type === 'number' ? labelled('Max', input(base + 'max', f.max == null ? '' : f.max, { type: 'number', step: 'any' })) : null,
				f.type === 'tel' ? labelled('Phone format', select(base + 'phone_format', f.phone_format || 'us', { us: 'US — 10 digits', intl: 'International — 7-15 digits' })) : null,
				f.type === 'textarea' ? labelled('Rows', input(base + 'rows', f.rows || 4, { type: 'number', min: 2, max: 12 })) : null,
				['text', 'email', 'tel', 'number', 'textarea', 'select'].indexOf(f.type) >= 0 ? checkbox(base + 'show_label', f.show_label, 'Show label above the input') : null
			]);
			if (!f._open) { more.setAttribute('hidden', ''); }

			return el('div', { className: 'bspe-cf__field' + (dup[f.key] ? ' has-error' : ''), 'data-step': si, 'data-field': fi }, [head, more]);
		}

		/* ------------------------------------------------ binding */
		function resolve(path) {
			var parts = path.split('.');
			var obj = state;
			for (var i = 0; i < parts.length - 1; i++) { obj = obj[parts[i]]; if (!obj) { return null; } }
			return { obj: obj, key: parts[parts.length - 1] };
		}

		function onInput(ev) {
			var t = ev.target;
			var bind = t.getAttribute && t.getAttribute('data-bind');
			if (!bind) { return; }
			var ref = resolve(bind);
			if (!ref) { return; }
			var val;
			if (t.type === 'checkbox') { val = t.checked; }
			else if (ref.key === 'options') { val = textToOptions(t.value); }
			else if (ref.key === 'min' || ref.key === 'max') { val = t.value === '' ? null : Number(t.value); }
			else if (ref.key === 'rows') { val = parseInt(t.value, 10) || 4; }
			else { val = t.value; }

			if (ref.key === 'key') {
				val = String(val).toLowerCase().replace(/[^a-z0-9_]/g, '_').slice(0, 40);
				ref.obj._auto = val === '';
			}
			ref.obj[ref.key] = val;

			// Typing a label fills the key until the key is edited by hand.
			if (ref.key === 'label' && ref.obj._auto) {
				ref.obj.key = keyFromLabel(val);
				var row = t.closest('.bspe-cf__field');
				var keyEl = row && row.querySelector('[data-key-input]');
				if (keyEl) { keyEl.value = ref.obj.key; }
			}
			if (ref.key === 'type') {
				if (CHOICE_TYPES.indexOf(val) >= 0 && (!ref.obj.options || !ref.obj.options.length)) {
					ref.obj.options = [{ value: 'Yes', label: 'Yes' }, { value: 'No', label: 'No' }];
				}
				ref.obj._open = true;
			}
			sync();
			if (t.hasAttribute('data-rerender') || ev.type === 'change' && ref.key === 'key') { render(); }
		}

		root.addEventListener('input', function (ev) {
			if (ev.target.tagName === 'SELECT' || ev.target.type === 'checkbox') { return; }
			onInput(ev);
		});
		root.addEventListener('change', function (ev) {
			if (ev.target.tagName === 'SELECT' || ev.target.type === 'checkbox' || ev.target.getAttribute('data-key-input')) { onInput(ev); }
		});

		root.addEventListener('click', function (ev) {
			var b = ev.target.closest('[data-act]');
			if (!b || !root.contains(b)) { return; }
			ev.preventDefault();
			var act = b.getAttribute('data-act');
			var si = parseInt(b.getAttribute('data-step'), 10);
			var fi = parseInt(b.getAttribute('data-field'), 10);
			var steps = state.steps;

			switch (act) {
				case 'step-add':
					steps.push({ title: '', subtitle: '', fields: [blankField('text')] });
					break;
				case 'step-remove':
					if (steps[si].fields.length && !window.confirm('Remove step ' + (si + 1) + ' and its ' + steps[si].fields.length + ' field(s)?')) { return; }
					steps.splice(si, 1);
					break;
				case 'step-up':
					if (si > 0) { steps.splice(si - 1, 0, steps.splice(si, 1)[0]); }
					break;
				case 'step-down':
					if (si < steps.length - 1) { steps.splice(si + 1, 0, steps.splice(si, 1)[0]); }
					break;
				case 'field-add':
					steps[si].fields.push(blankField('text'));
					break;
				case 'field-remove':
					steps[si].fields.splice(fi, 1);
					break;
				case 'field-toggle':
					steps[si].fields[fi]._open = !steps[si].fields[fi]._open;
					render();
					return;
				case 'field-up':
					if (fi > 0) {
						steps[si].fields.splice(fi - 1, 0, steps[si].fields.splice(fi, 1)[0]);
					} else if (si > 0) {
						steps[si - 1].fields.push(steps[si].fields.splice(fi, 1)[0]);
					}
					break;
				case 'field-down':
					if (fi < steps[si].fields.length - 1) {
						steps[si].fields.splice(fi + 1, 0, steps[si].fields.splice(fi, 1)[0]);
					} else if (si < steps.length - 1) {
						steps[si + 1].fields.unshift(steps[si].fields.splice(fi, 1)[0]);
					}
					break;
				default:
					return;
			}
			sync();
			render();
		});

		/* ------------------------------------------------ JSON editor */
		if (details) {
			details.addEventListener('toggle', function () {
				if (details.open) { jsonEditor.value = JSON.stringify(serializable(), null, 2); }
			});
		}
		var applyBtn = wrap.querySelector('[data-bspe-cf-json-apply]');
		if (applyBtn) {
			applyBtn.addEventListener('click', function () {
				try {
					state = normalizeState(JSON.parse(jsonEditor.value));
					sync();
					render();
					jsonStatus.textContent = 'Applied. Click Save changes to store it.';
				} catch (e) {
					jsonStatus.textContent = 'Not valid JSON: ' + e.message;
				}
			});
		}

		/* ------------------------------------------------ REST helpers */
		function api(method, path, body) {
			return fetch((CFG.restRoot || '') + path, {
				method: method,
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' },
				body: body ? JSON.stringify(body) : undefined
			}).then(function (r) {
				return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; });
			});
		}

		function setStatus(node, text, kind) {
			if (!node) { return; }
			node.textContent = text;
			node.removeAttribute('hidden');
			node.setAttribute('data-kind', kind || '');
		}

		/* ------------------------------------------------ Elementor import */
		var importBtn = wrap.querySelector('[data-bspe-cf-import]');
		var importSel = wrap.querySelector('[data-bspe-cf-import-select]');
		var importStatus = wrap.querySelector('[data-bspe-cf-import-status]');
		if (importBtn && importSel) {
			importBtn.addEventListener('click', function () {
				var parts = String(importSel.value || '').split(':');
				if (!parts[0]) { return; }
				if (state.steps.length && !window.confirm('Replace the fields in the builder with the imported form?')) { return; }
				importBtn.disabled = true;
				setStatus(importStatus, 'Importing…');
				api('POST', 'form/import-elementor', { post_id: parseInt(parts[0], 10), form_id: parts[1] || '', apply: false }).then(function (res) {
					if (!res.ok || !res.json || !res.json.form) {
						var msg = (res.json && res.json.message) || ('Import failed (HTTP ' + res.status + ').');
						var errs = res.json && res.json.data && res.json.data.errors;
						setStatus(importStatus, msg + (errs ? ' ' + errs.join(' ') : ''), 'error');
						return;
					}
					state = normalizeState(res.json.form.custom);
					sync();
					render();
					for (var i = 0; i < modeInputs.length; i++) {
						if (modeInputs[i].value === 'custom') {
							modeInputs[i].checked = true;
							modeInputs[i].dispatchEvent(new Event('change', { bubbles: true }));
						}
					}
					var count = 0;
					state.steps.forEach(function (st) { count += st.fields.length; });
					var warn = (res.json.warnings || []).length ? ' Notes: ' + res.json.warnings.join(' ') : '';
					setStatus(importStatus, 'Imported ' + count + ' fields in ' + state.steps.length + ' step(s). Review them, then click Save changes.' + warn, 'success');
				}).catch(function (e) {
					setStatus(importStatus, 'Import failed: ' + e.message, 'error');
				}).then(function () { importBtn.disabled = false; });
			});
		}

		/* ------------------------------------------------ webhook test */
		var testBtn = wrap.querySelector('[data-bspe-cf-test-webhook]');
		var testStatus = wrap.querySelector('[data-bspe-cf-test-status]');
		if (testBtn) {
			testBtn.addEventListener('click', function () {
				if (form && form.classList.contains('is-dirty') && !window.confirm('You have unsaved changes. The test uses the SAVED form. Send anyway?')) { return; }
				testBtn.disabled = true;
				setStatus(testStatus, 'Sending…');
				api('POST', 'form/test-webhook').then(function (res) {
					var j = res.json || {};
					if (res.ok && j.delivered) {
						setStatus(testStatus, 'Delivered to ' + j.host + ' (HTTP ' + j.http_code + ').', 'success');
					} else {
						setStatus(testStatus, (j.message || j.error || ('Failed (HTTP ' + (j.http_code || res.status) + ')')), 'error');
					}
				}).catch(function (e) {
					setStatus(testStatus, 'Failed: ' + e.message, 'error');
				}).then(function () { testBtn.disabled = false; });
			});
		}

		/* ------------------------------------------------ history */
		var historyBtn = wrap.querySelector('[data-bspe-cf-history]');
		var historyList = wrap.querySelector('[data-bspe-cf-history-list]');
		if (historyBtn && historyList) {
			historyBtn.addEventListener('click', function () {
				historyBtn.disabled = true;
				api('GET', 'form/revisions').then(function (res) {
					historyList.textContent = '';
					var revs = (res.json && res.json.revisions) || [];
					if (!revs.length) {
						historyList.appendChild(el('li', { text: 'No earlier versions yet.' }));
					}
					revs.slice(0, 10).forEach(function (r) {
						var who = (r.actor && (r.actor.name || r.actor.app)) || 'unknown';
						var when = r.at ? new Date(r.at).toLocaleString() : '';
						var restore = el('button', { type: 'button', className: 'bspe-button bspe-button--secondary bspe-cf__restore', text: 'Restore' });
						restore.addEventListener('click', function () {
							restore.disabled = true;
							// Dry run first so the confirm names exactly what changes.
							// Restores the layout (mode + fields) only; mail, webhook,
							// anti-spam and copy stay as they are now.
							api('POST', 'form/restore', { revision_id: r.id, dry_run: true }).then(function (dry) {
								if (!dry.ok) { throw new Error((dry.json && dry.json.message) || 'Restore preview failed.'); }
								var changes = (dry.json && dry.json.changes) || [];
								if (!changes.length) { window.alert('That version has the same fields as the current form. Nothing to restore.'); return null; }
								var list = changes.slice(0, 15).join('\n') + (changes.length > 15 ? '\n…and ' + (changes.length - 15) + ' more' : '');
								if (!window.confirm('Restore the form fields as they were before ' + when + '?\n\n' + list + '\n\nMail, webhook and anti-spam settings are not touched. The current form is kept in history.')) { return null; }
								return api('POST', 'form/restore', { revision_id: r.id });
							}).then(function (rr) {
								if (!rr) { restore.disabled = false; return; }
								if (rr.ok) { window.location.reload(); }
								else { restore.disabled = false; window.alert((rr.json && rr.json.message) || 'Restore failed.'); }
							}).catch(function (e) {
								restore.disabled = false;
								window.alert(e.message);
							});
						});
						historyList.appendChild(el('li', {}, [
							el('span', { text: when + ' — ' + who + ' — ' + (r.note || '') + ' (' + r.mode + ', ' + r.fields + ' fields)' }),
							restore
						]));
					});
					historyList.removeAttribute('hidden');
				}).catch(function (e) {
					historyList.textContent = 'Could not load history: ' + e.message;
					historyList.removeAttribute('hidden');
				}).then(function () { historyBtn.disabled = false; });
			});
		}

		render();
	});
})();
