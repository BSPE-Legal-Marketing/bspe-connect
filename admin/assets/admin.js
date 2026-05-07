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
	/*  WP color picker (jQuery)                                        */
	/* ---------------------------------------------------------------- */
	function initColorPickers() {
		if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.wpColorPicker) { return; }
		window.jQuery('[data-bspe-color]').wpColorPicker({
			palettes: [ '#351E28', '#0D1B2A', '#FAF7F2', '#3AAFB9', '#D4AF37', '#ffffff', '#000000' ]
		});
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
	});
})();
