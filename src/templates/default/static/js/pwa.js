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

	function decodeVapidKey(value) {
		var padding = '='.repeat((4 - value.length % 4) % 4);
		var raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
		return Uint8Array.from(raw, function (character) { return character.charCodeAt(0); });
	}

	function postJson(url, csrf, payload) {
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
			body: JSON.stringify(payload)
		}).then(function (response) {
			return response.json().catch(function () { return {}; }).then(function (body) {
				if (!response.ok || body.ok === false) { throw new Error(body.error || body.message || 'No se pudo completar la solicitud.'); }
				return body;
			});
		});
	}

	document.querySelectorAll('[data-push-settings]').forEach(function (panel) {
		var feedback = panel.querySelector('[data-push-feedback]');
		function show(message, failed) {
			feedback.hidden = false;
			feedback.className = 'alert ' + (failed ? 'alert-danger' : 'alert-success') + ' mb-4';
			feedback.textContent = message;
		}
		panel.querySelector('[data-push-enable]').addEventListener('click', function () {
			if (panel.dataset.enabled !== 'true' || !('PushManager' in window) || !('Notification' in window)) {
				show('Este navegador o la configuración del servidor no admite Web Push.', true); return;
			}
			window.Notification['requestPermission']().then(function (permission) {
				if (permission !== 'granted') { throw new Error('El permiso de notificaciones no fue concedido.'); }
				return navigator.serviceWorker.ready;
			}).then(function (registration) {
				return registration.pushManager.subscribe({
					userVisibleOnly: true,
					applicationServerKey: decodeVapidKey(panel.dataset.publicKey)
				});
			}).then(function (subscription) {
				var json = subscription.toJSON();
				return postJson(panel.dataset.subscribeUrl, panel.dataset.csrf, {
					endpoint: json.endpoint,
					keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
					contentEncoding: (window.PushManager.supportedContentEncodings || ['aes128gcm'])[0],
					deviceName: navigator.platform || 'Navegador'
				});
			}).then(function () { show('Notificaciones activadas para este dispositivo.', false); })
				.catch(function (error) { show(error.message, true); });
		});
		panel.querySelector('[data-push-disable]').addEventListener('click', function () {
			navigator.serviceWorker.ready.then(function (registration) { return registration.pushManager.getSubscription(); })
				.then(function (subscription) {
					if (!subscription) { return null; }
					return postJson(panel.dataset.unsubscribeUrl, panel.dataset.csrf, { endpoint: subscription.endpoint })
						.then(function () { return subscription.unsubscribe(); });
				}).then(function () { show('Notificaciones desactivadas para este dispositivo.', false); })
				.catch(function (error) { show(error.message, true); });
		});
		panel.querySelector('[data-push-test]').addEventListener('click', function () {
			postJson(panel.dataset.testUrl, panel.dataset.csrf, {})
				.then(function () { show('Notificación de prueba enviada.', false); })
				.catch(function (error) { show(error.message, true); });
		});
	});
}());
