/**
 * BSPE Connect — admin shell JS.
 *
 * Phase 1 only handles tab keyboard navigation (Left/Right/Home/End) so
 * the tablist is accessible. Phase 4 will add settings-form behaviors
 * (color pickers, Media Library, conditional fields).
 */
(function () {
	'use strict';

	function initTablistKeyboard() {
		var tabs = Array.prototype.slice.call(document.querySelectorAll('.bspe-tabs .bspe-tab'));
		if (tabs.length === 0) {
			return;
		}

		tabs.forEach(function (tab, index) {
			tab.addEventListener('keydown', function (event) {
				var target = null;
				switch (event.key) {
					case 'ArrowRight':
						target = tabs[(index + 1) % tabs.length];
						break;
					case 'ArrowLeft':
						target = tabs[(index - 1 + tabs.length) % tabs.length];
						break;
					case 'Home':
						target = tabs[0];
						break;
					case 'End':
						target = tabs[tabs.length - 1];
						break;
					default:
						return;
				}
				event.preventDefault();
				if (target) {
					target.focus();
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTablistKeyboard);
	} else {
		initTablistKeyboard();
	}
})();
