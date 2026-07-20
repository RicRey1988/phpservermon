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
		var badge = document.querySelector('[data-notification-count]');
		if (!badge) { return; }
		if (unread < 1) {
			badge.remove();
			return;
		}
		badge.textContent = unread > 99 ? '99+' : String(unread);
	}

	function markItemRead(item, button) {
		item.classList.remove('border-primary');
		item.classList.remove('border-start');
		item.classList.remove('border-4');
		item.querySelectorAll('[data-unread-badge]').forEach(function (element) { element.remove(); });
		if (button) { button.remove(); }
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-notification-mark-read]').forEach(function (button) {
			button.addEventListener('click', function () {
				var item = button.closest('[data-notification-item]');
				if (!item) { return; }
				button.disabled = true;
				post(button.dataset.action, { notification_id: item.dataset.notificationId }).then(function (payload) {
					markItemRead(item, button);
					updateBadge(payload.unread || 0);
				}).catch(function () { button.disabled = false; });
			});
		});

		var readAll = document.querySelector('[data-notifications-read-all]');
		if (readAll) {
			readAll.addEventListener('click', function () {
				readAll.disabled = true;
				post(readAll.dataset.action).then(function () {
					document.querySelectorAll('[data-notification-item]').forEach(function (item) {
						markItemRead(item, item.querySelector('[data-notification-mark-read]'));
					});
					updateBadge(0);
				}).finally(function () { readAll.disabled = false; });
			});
		}
	});
}());
