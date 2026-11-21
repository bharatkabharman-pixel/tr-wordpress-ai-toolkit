/* TR WordPress AI Toolkit — Admin JS */
/* global trAIToolkit */

(function () {
	'use strict';

	/**
	 * Copy-to-clipboard for endpoints and WP-CLI commands.
	 */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.tr-copy-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var targetId = btn.getAttribute('data-target');
				var target   = document.getElementById(targetId);

				if (!target) return;

				var text = target.textContent || target.innerText;

				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(text).then(function () {
						flashBtn(btn, trAIToolkit.strings.copied);
					}).catch(function () {
						fallbackCopy(text, btn);
					});
				} else {
					fallbackCopy(text, btn);
				}
			});
		});
	});

	function flashBtn(btn, message) {
		var original = btn.textContent;
		btn.textContent = message;
		btn.disabled    = true;
		setTimeout(function () {
			btn.textContent = original;
			btn.disabled    = false;
		}, 2000);
	}

	function fallbackCopy(text, btn) {
		var ta       = document.createElement('textarea');
		ta.value     = text;
		ta.style.position = 'fixed';
		ta.style.opacity  = '0';
		document.body.appendChild(ta);
		ta.focus();
		ta.select();

		try {
			document.execCommand('copy');
			flashBtn(btn, trAIToolkit.strings.copied);
		} catch (e) {
			flashBtn(btn, trAIToolkit.strings.copyFail);
		}

		document.body.removeChild(ta);
	}

	/**
	 * Auto-dismiss admin notices after 5 seconds.
	 */
	document.addEventListener('DOMContentLoaded', function () {
		var notices = document.querySelectorAll('.tr-ai-toolkit-wrap .notice.is-dismissible');
		if (notices.length) {
			setTimeout(function () {
				notices.forEach(function (notice) {
					notice.style.transition = 'opacity 0.5s';
					notice.style.opacity    = '0';
					setTimeout(function () {
						if (notice.parentNode) {
							notice.parentNode.removeChild(notice);
						}
					}, 500);
				});
			}, 5000);
		}
	});
})();
