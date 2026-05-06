/**
 * BSPE Connect — admin shell JS.
 *
 * Phase 1 only handles sidebar nav keyboard navigation (Up/Down/Left/Right
 * /Home/End) so the tablist is accessible. Phase 4 will add settings-form
 * behaviors (color pickers, Media Library, conditional fields).
 */
(function () {
	'use strict';

	function initSidebarKeyboard() {
		var items = Array.prototype.slice.call(
			document.querySelectorAll('.bspe-nav .bspe-nav__item')
		);
		if (items.length === 0) {
			return;
		}

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
				if (target) {
					target.focus();
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSidebarKeyboard);
	} else {
		initSidebarKeyboard();
	}
})();
