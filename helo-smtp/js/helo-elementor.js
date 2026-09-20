/**
 * Injection of the Helo Turnstile widget into Elementor Pro form widgets.
 *
 * Elementor Pro forms render client-side, so the widget has to be inserted from
 * JS. This finds every .elementor-form container that does not yet carry a
 * widget, inserts a captcha div before or after the submit button, and renders
 * it via Cloudflare's API (loaded through Helo_Turnstile's render queue).
 */
(function($) {
	'use strict';

	var SETTINGS = window.heloElementorSettings || {};
	var state = false;

	function setSubmit(btn, enabled) {
		if (!btn) { return; }
		btn.style.pointerEvents = enabled ? 'auto' : 'none';
		btn.style.opacity = enabled ? '1' : '0.5';
	}

	function init() {
		if (!SETTINGS.sitekey || !SETTINGS.enabled) { return; }
		if (state) { return; }
		state = true;

		var forms = document.querySelectorAll('.elementor-form');
		Array.prototype.forEach.call(forms, function(form) {
			if (form.querySelector('.cf-turnstile')) { return; } // Already injected.

			var submitButton = form.querySelector('button[type="submit"]');
			if (!submitButton) { return; }

			var index = Math.random ? Math.random().toString(36).slice(2, 8) : 'e';
			var div = document.createElement('div');
			div.className = 'elementor-turnstile-field cf-turnstile';
			div.id = 'cf-turnstile-elementor-' + index;
			div.style.cssText = 'display:block;margin:10px 0 15px 0;width:100%;';
			div.setAttribute('data-sitekey', SETTINGS.sitekey);
			div.setAttribute('data-theme', SETTINGS.theme || 'auto');
			div.setAttribute('data-appearance', SETTINGS.appearance || 'always');
			div.setAttribute('data-action', 'form-elementor');

			if (SETTINGS.position === 'after') {
				submitButton.parentNode.insertBefore(div, submitButton.nextSibling);
			} else if (SETTINGS.position === 'afterform') {
				form.appendChild(div);
			} else {
				submitButton.parentNode.insertBefore(div, submitButton);
			}

			if (window.turnstile) {
				try {
					turnstile.render(div, {
						sitekey: SETTINGS.sitekey,
						theme: SETTINGS.theme || 'auto',
						appearance: SETTINGS.appearance || 'always',
						callback: function() { setSubmit(submitButton, true); },
						'error-callback': function() { setSubmit(submitButton, !SETTINGS.disableSubmit); }
					});
					return;
				} catch (e) {}
			}

			// Queue for the bootstrap to render once the API is ready.
			window.heloTurnstileQueue = window.heloTurnstileQueue || [];
			window.heloTurnstileQueue.push('elementor-' + index);
			if (window.heloTurnstileRender) { window.heloTurnstileRender(); }
		});
	}

	// Run once the API/queue are present, and again when Elementor fires
	// frontend/init (covers forms added via popups / AJAX).
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
	$(window).on('elementor/frontend/init', init);
	$(document).on('elementor/popup/show', init);
})(window.jQuery);