/* TR WordPress AI Toolkit — Admin JS */
/* global trAIToolkit, jQuery */

(function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Copy-to-clipboard
	// -------------------------------------------------------------------------
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.tr-copy-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var targetId = btn.getAttribute('data-target');
				var target   = document.getElementById(targetId);
				if (!target) return;
				var text = target.textContent || target.innerText;
				copyText(text, btn);
			});
		});
	});

	// -------------------------------------------------------------------------
	// Quick Connect — generate credentials via AJAX
	// -------------------------------------------------------------------------
	document.addEventListener('DOMContentLoaded', function () {
		var generateBtn   = document.getElementById('tr-generate-config-btn');
		var regenerateBtn = document.getElementById('tr-regenerate-btn');

		if (generateBtn) {
			generateBtn.addEventListener('click', function () {
				doGenerateConfig();
			});
		}

		if (regenerateBtn) {
			regenerateBtn.addEventListener('click', function () {
				doGenerateConfig();
			});
		}
	});

	function doGenerateConfig() {
		var generateBtn = document.getElementById('tr-generate-config-btn');
		var spinner     = document.getElementById('tr-generate-spinner');
		var errorBox    = document.getElementById('tr-generate-error');
		var resultArea  = document.getElementById('tr-connect-result');

		if (!generateBtn) return;

		// Show spinner, hide previous error.
		generateBtn.disabled = true;
		if (spinner)  spinner.style.display  = 'block';
		if (errorBox) errorBox.style.display  = 'none';

		var data = new FormData();
		data.append('action', 'tr_ai_toolkit_generate_app_password');
		data.append('nonce',  trAIToolkit.nonce);

		fetch(trAIToolkit.ajaxUrl, {
			method: 'POST',
			body:   data,
		})
		.then(function (res) { return res.json(); })
		.then(function (response) {
			generateBtn.disabled = false;
			if (spinner) spinner.style.display = 'none';

			if (!response.success) {
				showError(errorBox, response.data ? response.data.message : trAIToolkit.strings.ajaxError);
				return;
			}

			populateResult(response.data);

			if (resultArea) {
				resultArea.style.display = 'block';
				resultArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}

			// Update generate button text.
			generateBtn.textContent = trAIToolkit.strings.regenerate;
		})
		.catch(function () {
			generateBtn.disabled = false;
			if (spinner) spinner.style.display = 'none';
			showError(errorBox, trAIToolkit.strings.ajaxError);
		});
	}

	function populateResult(data) {
		setElText('tr-result-url',          data.mcp_url);
		setElText('tr-result-header-name',  data.auth_header_name);
		setElText('tr-result-header-value', data.auth_header_value);
		setElText('tr-result-json',         data.claude_desktop_config);
		setElText('tr-result-username',     data.username);
		setElText('tr-result-app-password', data.app_password);

		// Re-attach copy listeners for newly populated fields.
		document.querySelectorAll('#tr-connect-result .tr-copy-btn').forEach(function (btn) {
			btn.onclick = function () {
				var targetId = btn.getAttribute('data-target');
				var target   = document.getElementById(targetId);
				if (!target) return;
				copyText(target.textContent || target.innerText, btn);
			};
		});
	}

	function setElText(id, text) {
		var el = document.getElementById(id);
		if (el) el.textContent = text;
	}

	function showError(box, message) {
		if (!box) return;
		box.innerHTML = '<p>' + escapeHtml(message) + '</p>';
		box.style.display = 'block';
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// -------------------------------------------------------------------------
	// Copy helpers
	// -------------------------------------------------------------------------
	function copyText(text, btn) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(function () {
				flashBtn(btn, trAIToolkit.strings.copied);
			}).catch(function () {
				fallbackCopy(text, btn);
			});
		} else {
			fallbackCopy(text, btn);
		}
	}

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

	// -------------------------------------------------------------------------
	// Prompt Library — copy prompt + category filter
	// -------------------------------------------------------------------------
	document.addEventListener('DOMContentLoaded', function () {

		// Copy prompt buttons
		document.querySelectorAll('.tr-copy-prompt-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var text     = btn.getAttribute('data-prompt');
				var feedback = btn.parentElement.querySelector('.tr-copy-feedback');

				copyText(text, btn);

				if (feedback) {
					feedback.style.display = 'inline-flex';
					setTimeout(function () { feedback.style.display = 'none'; }, 2000);
				}
			});
		});

		// Category filter buttons
		var filterBtns = document.querySelectorAll('.tr-filter-btn');
		filterBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				filterBtns.forEach(function (b) { b.classList.remove('tr-filter-active'); });
				btn.classList.add('tr-filter-active');

				var filter = btn.getAttribute('data-filter');
				document.querySelectorAll('.tr-prompt-category').forEach(function (cat) {
					if (filter === 'all' || cat.getAttribute('data-category') === filter) {
						cat.style.display = '';
					} else {
						cat.style.display = 'none';
					}
				});
			});
		});
	});

	// -------------------------------------------------------------------------
	// Manual credentials encoder (pure JS — no server call needed)
	// -------------------------------------------------------------------------
	document.addEventListener('DOMContentLoaded', function () {
		var encodeBtn = document.getElementById('tr-encode-btn');
		if (!encodeBtn) return;

		encodeBtn.addEventListener('click', function () {
			var username  = (document.getElementById('tr-manual-username').value || '').trim();
			var appPass   = (document.getElementById('tr-manual-apppass').value || '').trim();
			var resultDiv = document.getElementById('tr-manual-result');
			var authEl    = document.getElementById('tr-manual-auth-value');
			var jsonEl    = document.getElementById('tr-manual-json');

			if (!username || !appPass) {
				alert('Please enter both username and application password.');
				return;
			}

			var encoded   = btoa(username + ':' + appPass);
			var authValue = 'Basic ' + encoded;
			var mcpUrl    = document.getElementById('tr-endpoint') ? document.getElementById('tr-endpoint').textContent.trim() : '';

			var config = {
				mcpServers: {
					'tr-ai-toolkit': {
						type: 'http',
						url: mcpUrl,
						headers: { Authorization: authValue }
					}
				}
			};

			if (authEl)  authEl.textContent  = authValue;
			if (jsonEl)  jsonEl.textContent   = JSON.stringify(config, null, 2);
			if (resultDiv) {
				resultDiv.style.display = 'block';
				resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });

				// re-attach copy handlers for newly shown elements
				resultDiv.querySelectorAll('.tr-copy-btn').forEach(function (btn) {
					btn.onclick = function () {
						var t = document.getElementById(btn.getAttribute('data-target'));
						if (t) copyText(t.textContent || t.innerText, btn);
					};
				});
			}
		});
	});

	// -------------------------------------------------------------------------
	// Auto-dismiss admin notices after 5 seconds
	// -------------------------------------------------------------------------
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
