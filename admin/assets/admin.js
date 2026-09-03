/**
 * BSPE Connect — admin shell JS.
 *
 * Vanilla JS for shell + form behaviors (sidebar nav keyboard, custom
 * radios, icon picker, toggles, conditional fields, phone mask, submissions
 * row expand). The WordPress color picker requires jQuery and is initialized
 * separately via the small jQuery shim at the bottom — that's the only place
 * jQuery is touched.
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

	/* ---------------------------------------------------------------- */
	/*  Sidebar keyboard nav                                            */
	/* ---------------------------------------------------------------- */
	function initSidebarKeyboard() {
		var items = Array.prototype.slice.call(
			document.querySelectorAll('.bspe-nav .bspe-nav__item')
		);
		if (items.length === 0) { return; }

		items.forEach(function (item, index) {
			item.addEventListener('keydown', function (event) {
				var target = null;
				switch (event.key) {
					case 'ArrowDown':
					case 'ArrowRight':
						target = items[(index + 1) % items.length];
						break;
					case 'ArrowUp':
					case 'ArrowLeft':
						target = items[(index - 1 + items.length) % items.length];
						break;
					case 'Home':
						target = items[0];
						break;
					case 'End':
						target = items[items.length - 1];
						break;
					default:
						return;
				}
				event.preventDefault();
				if (target) { target.focus(); }
			});
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Radio pills (active class on change)                            */
	/* ---------------------------------------------------------------- */
	function initRadioPills() {
		var groups = document.querySelectorAll('.bspe-radio-pills');
		groups.forEach(function (group) {
			var radios = group.querySelectorAll('input[type="radio"]');
			radios.forEach(function (radio) {
				radio.addEventListener('change', function () {
					var labels = group.querySelectorAll('.bspe-radio-pill');
					labels.forEach(function (label) { label.classList.remove('is-active'); });
					if (radio.checked && radio.parentElement) {
						radio.parentElement.classList.add('is-active');
					}
				});
			});
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Icon radio (active class)                                       */
	/* ---------------------------------------------------------------- */
	function initIconRadios() {
		var groups = document.querySelectorAll('.bspe-icon-radio');
		groups.forEach(function (group) {
			var radios = group.querySelectorAll('input[type="radio"]');
			radios.forEach(function (radio) {
				radio.addEventListener('change', function () {
					var items = group.querySelectorAll('.bspe-icon-radio__item');
					items.forEach(function (item) { item.classList.remove('is-active'); });
					if (radio.checked && radio.parentElement) {
						radio.parentElement.classList.add('is-active');
					}
				});
			});
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Conditional required-when-visible (Form tab)                    */
	/* ---------------------------------------------------------------- */
	function initConditionalRequired() {
		var toggles = document.querySelectorAll('[data-bspe-controls-required]');
		toggles.forEach(function (toggle) {
			var targetId = toggle.getAttribute('data-bspe-controls-required');
			if (!targetId) { return; }
			var target = document.getElementById(targetId);
			if (!target) { return; }

			function syncDisabled() {
				if (toggle.checked) {
					target.disabled = false;
					target.closest('.bspe-check').classList.remove('is-disabled');
				} else {
					target.disabled = true;
					target.checked = false;
					target.closest('.bspe-check').classList.add('is-disabled');
				}
			}
			syncDisabled();
			toggle.addEventListener('change', syncDisabled);
		});
	}

	/* ---------------------------------------------------------------- */
	/*  data-bspe-show-when — show child block when controlling toggle on */
	/* ---------------------------------------------------------------- */
	function initShowWhen() {
		var nodes = document.querySelectorAll('[data-bspe-show-when]');
		nodes.forEach(function (node) {
			var controllerId = node.getAttribute('data-bspe-show-when');
			if (!controllerId) { return; }
			var controller = document.getElementById(controllerId);
			if (!controller) { return; }

			function sync() {
				node.style.display = controller.checked ? '' : 'none';
			}
			sync();
			controller.addEventListener('change', sync);
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Phone mask in admin Buttons tab                                 */
	/* ---------------------------------------------------------------- */
	function maskPhone(raw) {
		var digits = (raw || '').replace(/\D/g, '').slice(0, 10);
		if (digits.length === 0) { return ''; }
		if (digits.length < 4)  { return '(' + digits; }
		if (digits.length < 7)  { return '(' + digits.slice(0, 3) + ') ' + digits.slice(3); }
		return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
	}

	function initPhoneMask() {
		var inputs = document.querySelectorAll('[data-bspe-phone-mask]');
		inputs.forEach(function (input) {
			input.addEventListener('input', function () {
				input.value = maskPhone(input.value);
			});
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Media library picker                                            */
	/* ---------------------------------------------------------------- */
	function initMediaPickers() {
		if (typeof window.wp === 'undefined' || !window.wp.media) { return; }

		var wrappers = document.querySelectorAll('[data-bspe-media]');
		wrappers.forEach(function (wrap) {
			var idInput = wrap.querySelector('[data-bspe-media-id]');
			var preview = wrap.querySelector('[data-bspe-media-preview]');
			var pickBtn = wrap.querySelector('[data-bspe-media-pick]');
			var removeBtn = wrap.querySelector('[data-bspe-media-remove]');
			if (!idInput || !preview || !pickBtn) { return; }

			var modalTitle = wrap.getAttribute('data-modal-title') || 'Select image';
			var frame = null;

			pickBtn.addEventListener('click', function (event) {
				event.preventDefault();
				if (!frame) {
					frame = window.wp.media({
						title: modalTitle,
						multiple: false,
						library: { type: 'image' },
						button: { text: 'Use this image' }
					});
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						if (!attachment || !attachment.id) { return; }
						idInput.value = String(attachment.id);
						var url = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) || attachment.url;
						preview.innerHTML = '';
						var img = document.createElement('img');
						img.src = url;
						img.alt = '';
						preview.appendChild(img);
						if (removeBtn) { removeBtn.removeAttribute('hidden'); }
					});
				}
				frame.open();
			});

			if (removeBtn) {
				removeBtn.addEventListener('click', function (event) {
					event.preventDefault();
					idInput.value = '0';
					preview.innerHTML = '<span class="bspe-media__placeholder" aria-hidden="true">' +
						'<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="1.5"/><path d="M3 16l5-5 5 5 4-4 4 4"/></svg>' +
						'</span>';
					removeBtn.setAttribute('hidden', '');
				});
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Submissions table — expand/collapse rows                        */
	/* ---------------------------------------------------------------- */
	function initSubmissionRows() {
		var buttons = document.querySelectorAll('.bspe-table__expand');
		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var row = btn.closest('tr');
				if (!row) { return; }
				var detail = row.nextElementSibling;
				if (!detail || !detail.classList.contains('bspe-table__detail')) { return; }
				var isOpen = btn.getAttribute('aria-expanded') === 'true';
				btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
				if (isOpen) {
					detail.setAttribute('hidden', '');
				} else {
					detail.removeAttribute('hidden');
				}
			});
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Submissions bulk delete — checkbox tracking + confirm dialog    */
	/* ---------------------------------------------------------------- */
	function initSubmissionsBulkDelete() {
		var form = document.querySelector('[data-bspe-bulk-form]');
		if (!form) { return; }

		var bar         = form.querySelector('[data-bspe-bulk-bar]');
		var countEl     = form.querySelector('[data-bspe-bulk-count]');
		var checkAll    = form.querySelector('[data-bspe-check-all]');
		var rowChecks   = form.querySelectorAll('[data-bspe-row-check]');
		var deleteBtn   = form.querySelector('[data-bspe-bulk-delete]');
		var clearBtn    = form.querySelector('[data-bspe-bulk-clear]');

		function refresh() {
			var checked = form.querySelectorAll('[data-bspe-row-check]:checked');
			var n = checked.length;
			if (countEl) { countEl.textContent = String(n); }
			if (bar) {
				if (n > 0) { bar.removeAttribute('hidden'); }
				else       { bar.setAttribute('hidden', ''); }
			}
			if (deleteBtn) { deleteBtn.disabled = n === 0; }
			if (checkAll) {
				var total = rowChecks.length;
				checkAll.checked       = n > 0 && n === total;
				checkAll.indeterminate = n > 0 && n < total;
			}
		}

		rowChecks.forEach(function (cb) {
			cb.addEventListener('change', refresh);
		});

		if (checkAll) {
			checkAll.addEventListener('change', function () {
				rowChecks.forEach(function (cb) { cb.checked = checkAll.checked; });
				refresh();
			});
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				rowChecks.forEach(function (cb) { cb.checked = false; });
				if (checkAll) { checkAll.checked = false; }
				refresh();
			});
		}

		form.addEventListener('submit', function (ev) {
			var checked = form.querySelectorAll('[data-bspe-row-check]:checked');
			if (checked.length === 0) { ev.preventDefault(); return; }
			var msg = 'Delete ' + checked.length + ' submission' + (checked.length === 1 ? '' : 's') + '?\n\nThis cannot be undone. Already-sent emails are not affected.';
			if (!window.confirm(msg)) { ev.preventDefault(); }
		});

		refresh();
	}

	/* ---------------------------------------------------------------- */
	/*  Reset all settings — type-the-phrase confirmation               */
	/*                                                                  */
	/*  Click "Reset all settings" → reveals an inline panel with a     */
	/*  text input. The submit button stays disabled until the typed    */
	/*  value (trimmed) matches the literal RESET phrase. Server-side   */
	/*  re-verifies the same phrase so the JS gate isn't the only       */
	/*  defense.                                                        */
	/* ---------------------------------------------------------------- */
	function initResetSettings() {
		var card    = document.querySelector('[data-bspe-reset-card]');
		if (!card) { return; }
		var trigger = card.querySelector('[data-bspe-reset-trigger]');
		var form    = card.querySelector('[data-bspe-reset-form]');
		var input   = card.querySelector('[data-bspe-reset-input]');
		var submit  = card.querySelector('[data-bspe-reset-submit]');
		var cancel  = card.querySelector('[data-bspe-reset-cancel]');

		if (!trigger || !form || !input || !submit) { return; }
		var phrase = form.getAttribute('data-bspe-reset-phrase') || 'RESET';

		function showPanel() {
			form.removeAttribute('hidden');
			trigger.setAttribute('hidden', '');
			input.value = '';
			submit.disabled = true;
			setTimeout(function () { try { input.focus(); } catch (e) {} }, 30);
		}

		function hidePanel() {
			form.setAttribute('hidden', '');
			trigger.removeAttribute('hidden');
			input.value = '';
			submit.disabled = true;
		}

		trigger.addEventListener('click', function (ev) {
			ev.preventDefault();
			showPanel();
		});

		if (cancel) {
			cancel.addEventListener('click', function (ev) {
				ev.preventDefault();
				hidePanel();
			});
		}

		input.addEventListener('input', function () {
			submit.disabled = (input.value.trim() !== phrase);
		});

		// Block submit if somehow the value still doesn't match (paranoia
		// safety net; the disabled state already prevents click-submit).
		form.addEventListener('submit', function (ev) {
			if (input.value.trim() !== phrase) {
				ev.preventDefault();
				submit.disabled = true;
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Submissions "Delete all matching filters" — confirm dialog      */
	/* ---------------------------------------------------------------- */
	function initSubmissionsDeleteAll() {
		var form = document.querySelector('[data-bspe-delete-all-form]');
		if (!form) { return; }

		form.addEventListener('submit', function (ev) {
			var count   = parseInt(form.getAttribute('data-bspe-delete-count'), 10);
			var scoped  = form.getAttribute('data-bspe-delete-scoped') === '1';
			if (!isFinite(count) || count <= 0) { ev.preventDefault(); return; }

			var msg;
			if (scoped) {
				msg = 'Delete all ' + count + ' submission' + (count === 1 ? '' : 's') + ' matching the current filters?\n\nThis cannot be undone. Already-sent emails are not affected.';
			} else {
				msg = 'Delete ALL ' + count + ' submission' + (count === 1 ? '' : 's') + '?\n\nThere are no filters active — this will empty the entire submissions table. This cannot be undone. Already-sent emails are not affected.';
			}
			if (!window.confirm(msg)) { ev.preventDefault(); }
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Per-button icon-library live swap                               */
	/*                                                                  */
	/*  When the Icon library <select> changes for a button, hide /     */
	/*  show the matching picker pane (brand 4-radio vs custom text     */
	/*  input vs none) without requiring a full save+reload.            */
	/* ---------------------------------------------------------------- */
	function initIconLibrarySwap() {
		var selects = document.querySelectorAll('[data-bspe-icon-library-select]');
		selects.forEach(function (select) {
			var btnKey = select.getAttribute('data-bspe-icon-library-select');
			if (!btnKey) { return; }

			var panes = document.querySelectorAll('[data-bspe-icon-pane][data-bspe-button="' + btnKey + '"]');

			function sync() {
				var v = select.value;
				panes.forEach(function (pane) {
					var paneKey = pane.getAttribute('data-bspe-icon-pane');
					pane.style.display = (paneKey === v) ? '' : 'none';
				});
			}

			sync();
			select.addEventListener('change', sync);
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Settings form: dirty-state tracking                             */
	/*                                                                  */
	/*  The "Save changes" button starts at 50% opacity. As soon as the */
	/*  user touches any field, the form gets `.is-dirty` and the CSS   */
	/*  bumps the button to full opacity. Snapshots the form's          */
	/*  serialized state at boot and compares on every input/change.    */
	/* ---------------------------------------------------------------- */
	function initFormDirtyTracking() {
		var forms = document.querySelectorAll('form.bspe-form');
		forms.forEach(function (form) {
			var snapshot = serializeForm(form);
			var saveBtn = form.querySelector('[data-bspe-save-button]');

			function setDirty(isDirty) {
				if (isDirty) {
					form.classList.add('is-dirty');
					if (saveBtn) {
						saveBtn.setAttribute('title', 'You have unsaved changes');
					}
				} else {
					form.classList.remove('is-dirty');
					if (saveBtn) {
						saveBtn.setAttribute('title', 'No unsaved changes');
					}
				}
			}

			function check() {
				setDirty(serializeForm(form) !== snapshot);
			}

			form.addEventListener('input', check);
			form.addEventListener('change', check);

			// AJAX submit. Posts the form via fetch, expects JSON back,
			// shows an inline notice instead of reloading the page. The
			// PHP handler detects X-Requested-With and short-circuits
			// its usual redirect-with-transient flow. If JS breaks for
			// any reason we let the browser do its normal POST + reload
			// — the form's action / method are unchanged.
			form.addEventListener('submit', function (ev) {
				if (!window.fetch || !window.FormData) { return; }
				ev.preventDefault();

				if (saveBtn) {
					saveBtn.disabled = true;
					saveBtn.setAttribute('data-bspe-saving', '1');
				}
				clearInlineNotices(form);

				var fd  = new FormData(form);
				var url = form.getAttribute('action') || window.location.href;

				fetch(url, {
					method: 'POST',
					body: fd,
					credentials: 'same-origin',
					headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
				}).then(function (resp) {
					return resp.json().then(function (json) { return { ok: resp.ok, status: resp.status, json: json }; });
				}).then(function (result) {
					if (!result.ok || !result.json || result.json.success === false) {
						var msg = (result.json && result.json.data && result.json.data.message)
							|| ('Save failed (HTTP ' + result.status + ').');
						showInlineNotice(form, 'error', msg);
						return;
					}
					var okMsg = (result.json && result.json.data && result.json.data.message) || 'Settings saved.';
					showInlineNotice(form, 'success', okMsg);
					// Update the dirty-tracking snapshot so the save
					// button shrinks back to the "no unsaved changes"
					// state without a reload.
					snapshot = serializeForm(form);
					setDirty(false);
				}).catch(function (err) {
					showInlineNotice(form, 'error', (err && err.message) || 'Network error while saving.');
				}).then(function () {
					if (saveBtn) {
						saveBtn.disabled = false;
						saveBtn.removeAttribute('data-bspe-saving');
					}
				});
			});
		});
	}

	function clearInlineNotices(form) {
		var existing = form.parentNode && form.parentNode.querySelectorAll('.bspe-notice.bspe-notice--inline');
		if (!existing) { return; }
		existing.forEach(function (n) { n.parentNode.removeChild(n); });
	}

	function showInlineNotice(form, kind, message) {
		clearInlineNotices(form);
		var div = document.createElement('div');
		div.className = 'bspe-notice bspe-notice--inline bspe-notice--' + (kind === 'error' ? 'error' : 'success');
		div.setAttribute('role', kind === 'error' ? 'alert' : 'status');
		div.textContent = message;
		form.parentNode.insertBefore(div, form);
		// Fade the notice out after a few seconds for success messages.
		if (kind !== 'error') {
			setTimeout(function () {
				div.classList.add('is-fading');
				setTimeout(function () {
					if (div.parentNode) { div.parentNode.removeChild(div); }
				}, 600);
			}, 2400);
		}
	}

	function serializeForm(form) {
		var fd = new FormData(form);
		var pairs = [];
		fd.forEach(function (value, key) {
			pairs.push(key + '=' + (typeof value === 'string' ? value : ''));
		});
		pairs.sort();
		return pairs.join('&');
	}

	/* ---------------------------------------------------------------- */
	/*  WP color picker (jQuery)                                        */
	/* ---------------------------------------------------------------- */
	function initColorPickers() {
		if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.wpColorPicker) { return; }
		window.jQuery('[data-bspe-color]').wpColorPicker({
			palettes: [ '#351E28', '#0D1B2A', '#FAF7F2', '#3AAFB9', '#D4AF37', '#ffffff', '#000000' ]
		});
	}

	/* ---------------------------------------------------------------- */
	/*  Palette presets — "Use Plugin Default Colors" / "Use Default    */
	/*  Website Colors" on the Design tab.                              */
	/* ---------------------------------------------------------------- */
	function initPalettePresets() {
		var defaultsBtn = document.querySelector('[data-bspe-preset-defaults]');
		var themeBtn    = document.querySelector('[data-bspe-preset-theme]');
		var panel       = document.querySelector('[data-bspe-palette-panel]');

		if (defaultsBtn) {
			defaultsBtn.addEventListener('click', function (ev) {
				ev.preventDefault();
				var raw = defaultsBtn.getAttribute('data-bspe-defaults') || '{}';
				var defaults;
				try { defaults = JSON.parse(raw); }
				catch (err) { defaults = {}; }
				Object.keys(defaults).forEach(function (key) {
					setColorPickerByKey(key, defaults[key]);
				});
			});
		}

		if (themeBtn && panel && !themeBtn.hasAttribute('disabled')) {
			themeBtn.addEventListener('click', function (ev) {
				ev.preventDefault();
				var open = !panel.hasAttribute('hidden');
				if (open) {
					panel.setAttribute('hidden', '');
					themeBtn.setAttribute('aria-expanded', 'false');
				} else {
					panel.removeAttribute('hidden');
					themeBtn.setAttribute('aria-expanded', 'true');
					// Reset every row to the "no chip selected" state each
					// time the panel opens, so a re-open always forces an
					// explicit choice (Option A from the spec).
					resetPaletteRows(panel);
					if (typeof panel.scrollIntoView === 'function') {
						try { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
						catch (e) { panel.scrollIntoView(); }
					}
				}
			});
		}

		if (panel) {
			// Color chip click — mark this chip as the selected one for
			// its row, deselect its siblings, write the value into the
			// hidden input that Apply reads from, and update the row's
			// caption to "Primary — #6EC1E4" (or whatever).
			panel.addEventListener('click', function (ev) {
				var chip = ev.target.closest && ev.target.closest('[data-bspe-palette-chip]');
				if (!chip || !panel.contains(chip)) { return; }
				ev.preventDefault();

				var key   = chip.getAttribute('data-bspe-palette-chip') || '';
				var value = chip.getAttribute('data-value') || '';
				var name  = chip.getAttribute('data-label') || '';
				if (!key || !value) { return; }

				// Toggle: clicking the already-selected chip clears the row.
				var wasSelected = chip.getAttribute('aria-checked') === 'true';
				selectChipForRow(panel, key, wasSelected ? null : chip, value, name, wasSelected);
			});

			var applyBtn = panel.querySelector('[data-bspe-palette-apply]');
			if (applyBtn) {
				applyBtn.addEventListener('click', function (ev) {
					ev.preventDefault();
					var hiddens = panel.querySelectorAll('[data-bspe-palette-select]');
					hiddens.forEach(function (hidden) {
						var key = hidden.getAttribute('data-bspe-palette-select') || '';
						var val = hidden.value || '';
						if (key && val) {
							setColorPickerByKey(key, val);
						}
					});
					panel.setAttribute('hidden', '');
					if (themeBtn) { themeBtn.setAttribute('aria-expanded', 'false'); }
				});
			}

			var cancelBtn = panel.querySelector('[data-bspe-palette-cancel]');
			if (cancelBtn) {
				cancelBtn.addEventListener('click', function (ev) {
					ev.preventDefault();
					panel.setAttribute('hidden', '');
					if (themeBtn) { themeBtn.setAttribute('aria-expanded', 'false'); }
				});
			}
		}
	}

	// Reset every row inside the mapping panel to a fresh unpicked state.
	function resetPaletteRows(panel) {
		if (!panel) { return; }
		var rows = panel.querySelectorAll('[data-bspe-palette-row]');
		rows.forEach(function (row) {
			var key = row.getAttribute('data-bspe-palette-row');
			selectChipForRow(panel, key, null, '', '', true);
		});
	}

	// Update one row's selected chip + hidden input + caption.
	// Pass chip = null with clear=true to deselect everything in that row.
	function selectChipForRow(panel, key, chip, value, name, clear) {
		if (!panel || !key) { return; }
		var chips   = panel.querySelectorAll('[data-bspe-palette-chip="' + key + '"]');
		var hidden  = panel.querySelector('[data-bspe-palette-select="' + key + '"]');
		var caption = panel.querySelector('[data-bspe-palette-caption="' + key + '"]');

		chips.forEach(function (c) {
			var on = !clear && c === chip;
			c.setAttribute('aria-checked', on ? 'true' : 'false');
			c.classList.toggle('is-selected', on);
		});

		if (hidden) { hidden.value = clear ? '' : (value || ''); }

		if (caption) {
			if (clear || !value) {
				caption.textContent = '— Pick a color —';
				caption.classList.remove('is-set');
			} else {
				caption.textContent = (name ? name + ' — ' : '') + value.toUpperCase();
				caption.classList.add('is-set');
			}
		}
	}

	// Programmatically update one of the design.colors[*] pickers so the
	// WP color picker UI, the underlying input value, and the dirty-tracker
	// all see the new value. Using wpColorPicker('color', ...) is the
	// blessed API — it sets the value AND fires the change event.
	function setColorPickerByKey(key, hex) {
		if (!key || !hex) { return; }
		var input = document.querySelector('input[name="bspe[design][colors][' + key + ']"]');
		if (!input) { return; }
		if (window.jQuery && window.jQuery.fn && window.jQuery.fn.wpColorPicker) {
			try {
				window.jQuery(input).wpColorPicker('color', hex);
				return;
			} catch (err) { /* fall through to manual */ }
		}
		input.value = hex;
		input.dispatchEvent(new Event('input',  { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));
	}


	/* ---------------------------------------------------------------- */
	/*  Boot                                                            */
	/* ---------------------------------------------------------------- */
	ready(function () {
		initSidebarKeyboard();
		initRadioPills();
		initIconRadios();
		initConditionalRequired();
		initShowWhen();
		initPhoneMask();
		initMediaPickers();
		initSubmissionRows();
		initIconLibrarySwap();
		initColorPickers();
		initPalettePresets();
		initFormDirtyTracking();
		initSubmissionsBulkDelete();
		initSubmissionsDeleteAll();
		initResetSettings();
	});
})();
