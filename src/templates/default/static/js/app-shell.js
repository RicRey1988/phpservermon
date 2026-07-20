(function () {
	'use strict';

	function each(selector, callback) {
		document.querySelectorAll(selector).forEach(callback);
	}

	function toggleGroup(selector, visible) {
		each(selector, function (element) { element.hidden = !visible; });
	}

	function initializeTheme() {
		var root = document.documentElement;
		var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
		function apply() {
			var scheme = root.dataset.uiScheme;
			var dark = scheme === 'dark' || (scheme === 'auto' && media && media.matches);
			root.dataset.bsTheme = dark ? 'dark' : 'light';
			root.classList.toggle('dark', !!dark);
			document.body.classList.toggle('dark', !!dark);
		}
		apply();
		if (media && typeof media.addEventListener === 'function') {
			media.addEventListener('change', apply);
		}
	}

	function initializeSidebar() {
		each('[data-sidebar-toggle]', function (button) {
			button.addEventListener('click', function () {
				if (window.matchMedia('(max-width: 991.98px)').matches) {
					document.body.classList.toggle('sidebar-open');
				} else {
					document.body.classList.toggle('sidebar-collapsed');
				}
				button.setAttribute('aria-expanded', String(
					document.body.classList.contains('sidebar-open') || !document.body.classList.contains('sidebar-collapsed')
				));
			});
		});
	}

	function initializeModals() {
		each('.show-modal', function (origin) {
			origin.addEventListener('click', function (event) {
				event.preventDefault();
				var modalId = origin.dataset.modalId || 'main';
				var modalElement = document.getElementById(modalId + 'Modal');
				if (!modalElement || typeof window.bootstrap === 'undefined') {
					return;
				}

				var values = (origin.dataset.modalParam || '').split(',');
				values.forEach(function (value, index) {
					var target = modalElement.querySelector('.modalP' + (index + 1));
					if (target) { target.textContent = value; }
				});

				var ok = modalElement.querySelector('.modalOKButton');
				if (ok) { ok._psmOrigin = origin; }
				window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
			});
		});

		each('.modalOKButton', function (button) {
			button.addEventListener('click', function () {
				var origin = button._psmOrigin;
				if (!origin) { return; }
				if (origin.tagName === 'A') {
					window.location.assign(origin.href);
					return;
				}
				var hidden = origin.nextElementSibling;
				if (hidden && hidden.matches('input[type="hidden"]')) { hidden.value = '1'; }
				if (origin.form) { origin.form.requestSubmit(); }
			});
		});
	}

	function initializeConditionalForms() {
		var type = document.getElementById('type');
		var requestMethod = document.getElementById('popular_request_methods');
		var popularPort = document.getElementById('popular_ports');
		var username = document.getElementById('user_name');

		function updateType() {
			if (!type) { return; }
			toggleGroup('.typeWebsite', type.value === 'website');
			toggleGroup('.typeService', type.value === 'service');
			if (requestMethod) { requestMethod.dispatchEvent(new Event('change')); }
			if (popularPort) { popularPort.dispatchEvent(new Event('change')); }
		}
		function updateRequestMethod() {
			if (!requestMethod || !type || type.value !== 'website') { return; }
			var custom = requestMethod.value === 'custom';
			toggleGroup('.requestMethod', custom);
			var target = document.getElementById('request_method');
			if (target && !custom) { target.value = requestMethod.value; }
		}
		function updatePort() {
			if (!popularPort || !type || type.value !== 'service') { return; }
			var custom = popularPort.value === 'custom';
			toggleGroup('.port', custom);
			var target = document.getElementById('port');
			if (target && !custom) { target.value = popularPort.value; }
		}
		function updatePublicUser() {
			if (!username) { return; }
			var publicUser = username.value === '__PUBLIC__';
			['password', 'password_repeat'].forEach(function (id) {
				var input = document.getElementById(id);
				if (input && input.parentElement) { input.parentElement.hidden = publicUser; }
			});
			if (publicUser) {
				var level = document.getElementById('level');
				var name = document.getElementById('name');
				if (level) { level.value = '30'; }
				if (name) { name.value = 'Public page'; }
			}
		}

		if (type) { type.addEventListener('change', updateType); updateType(); }
		if (requestMethod) { requestMethod.addEventListener('change', updateRequestMethod); updateRequestMethod(); }
		if (popularPort) { popularPort.addEventListener('change', updatePort); updatePort(); }
		if (username) { username.addEventListener('change', updatePublicUser); updatePublicUser(); }
	}

	window.psm_setLayout = function (layout) {
		var list = document.getElementById('list-layout');
		var flow = document.getElementById('flow-layout');
		if (list) { list.hidden = !layout; }
		if (flow) { flow.hidden = !!layout; }
		each('#block-layout', function (button) { button.classList.toggle('active', !layout); });
		each('#table-layout', function (button) { button.classList.toggle('active', !!layout); });
	};

	window.psm_saveLayout = function (layout) {
		window.psm_setLayout(layout);
		var token = document.querySelector('input[name="csrf"]');
		var body = new URLSearchParams({ action: 'saveLayout', csrf: token ? token.value : '', layout: String(layout) });
		fetch('index.php?xhr=1&mod=server_status', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
			body: body.toString(),
			credentials: 'same-origin'
		});
	};

	document.addEventListener('DOMContentLoaded', function () {
		initializeTheme();
		initializeSidebar();
		initializeModals();
		initializeConditionalForms();
		var label = document.getElementById('label');
		if (label && !document.querySelector('[autofocus]')) { label.focus(); }
	});
}());
