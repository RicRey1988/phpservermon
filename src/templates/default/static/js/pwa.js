(function () {
	'use strict';

	var secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
	if (!secure || !('serviceWorker' in navigator)) { return; }

	var installPrompt = null;
	navigator.serviceWorker.register('service-worker.js', { scope: './' }).then(function (registration) {
		if (document.body.dataset.authenticated !== 'true' && registration.active) {
			registration.active.postMessage({ type: 'CLEAR_PRIVATE_CACHES' });
		}
	}).catch(function () {
		// The regular web application remains fully usable without a service worker.
	});

	window.addEventListener('beforeinstallprompt', function (event) {
		event.preventDefault();
		installPrompt = event;
		document.querySelectorAll('[data-pwa-install]').forEach(function (button) { button.hidden = false; });
	});

	document.querySelectorAll('[data-pwa-install]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (!installPrompt) { return; }
			installPrompt.prompt();
			installPrompt.userChoice.finally(function () {
				installPrompt = null;
				button.hidden = true;
			});
		});
	});
}());
