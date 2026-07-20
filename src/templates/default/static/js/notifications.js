(function () {
	'use strict';

	function csrf() {
		var input = document.querySelector('input[name="csrf"]');
		return input ? input.value : '';
	}

	function post(action, parameters) {
		var body = new URLSearchParams(Object.assign({ csrf: csrf() }, parameters || {}));
		return fetch(action, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin',
			body: body.toString()
		}).then(function (response) {
			if (!response.ok) { throw new Error('Notification update failed'); }
			return response.json();
		});
	}

	function updateBadge(unread) {
		var badge = document.querySelector('.notification-count');
		if (!badge) { return; }
		if (unread < 1) {
			badge.remove();
			return;
		}
		badge.textContent = unread > 99 ? '99+' : String(unread);
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-notification-read]').forEach(function (button) {
			button.addEventListener('click', function () {
				var card = button.closest('[data-notification-id]');
				if (!card) { return; }
				button.disabled = true;
				post(button.dataset.action, { notification_id: card.dataset.notificationId }).then(function (payload) {
					card.classList.remove('notification-unread');
					card.querySelectorAll('.badge.bg-primary').forEach(function (element) { element.remove(); });
					button.remove();
					updateBadge(payload.unread || 0);
				}).catch(function () { button.disabled = false; });
			});
		});

		var readAll = document.querySelector('[data-notifications-read-all]');
		if (readAll) {
			readAll.addEventListener('click', function () {
				readAll.disabled = true;
				post(readAll.dataset.action).then(function () {
					document.querySelectorAll('.notification-unread').forEach(function (card) { card.classList.remove('notification-unread'); });
					document.querySelectorAll('[data-notification-read]').forEach(function (button) { button.remove(); });
					updateBadge(0);
				}).finally(function () { readAll.disabled = false; });
			});
		}
	});
}());
