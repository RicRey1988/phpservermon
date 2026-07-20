(function () {
	'use strict';

	function each(selector, callback) {
		document.querySelectorAll(selector).forEach(callback);
	}

	function toggleGroup(selector, visible) {
		each(selector, function (element) { element.hidden = !visible; });
	}


	function syncThemeIcons(resolved) {
		each('[data-theme-quick-toggle]', function (button) {
			var lightAction = resolved === 'dark';
			var darkIcon = button.querySelector('[data-theme-icon="dark"]');
			var lightIcon = button.querySelector('[data-theme-icon="light"]');
			if (darkIcon) {
				darkIcon.classList.toggle('d-none', lightAction);
				darkIcon.classList.toggle('d-inline-flex', !lightAction);
			}
			if (lightIcon) {
				lightIcon.classList.toggle('d-none', !lightAction);
				lightIcon.classList.toggle('d-inline-flex', lightAction);
			}
			var label = lightAction ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
			button.setAttribute('aria-label', label);
			button.setAttribute('title', label);
		});
	}

	function initializeSidebarAccessibility() {
		var sidebar = document.querySelector('.sidebar-default');
		var toggles = Array.from(document.querySelectorAll('[data-toggle="sidebar"]'));
		var mobile = window.matchMedia ? window.matchMedia('(max-width: 1199.98px)') : null;
		if (!sidebar || toggles.length === 0) { return; }

		function isMobile() { return mobile ? mobile.matches : window.innerWidth < 1200; }
		function isOpen() { return !sidebar.classList.contains('sidebar-mini'); }
		function sync() {
			var open = isOpen();
			toggles.forEach(function (toggle) {
				toggle.setAttribute('aria-expanded', String(open));
				toggle.setAttribute('aria-label', open ? 'Contraer menú' : 'Abrir menú');
			});
		}
		function closeMobile() {
			if (!isMobile()) { return; }
			sidebar.classList.add('sidebar-mini');
			sync();
		}
		function applyViewportState() {
			if (isMobile()) {
				sidebar.classList.add('sidebar-mini');
			} else {
				sidebar.classList.toggle('sidebar-mini', sidebar.dataset.sidebarPreferredMini === 'true');
			}
			sync();
		}

		toggles.forEach(function (toggle) {
			toggle.addEventListener('click', function () { window.requestAnimationFrame(sync); });
		});
		sidebar.querySelectorAll('#sidebar-menu a').forEach(function (link) { link.addEventListener('click', closeMobile); });
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && isMobile() && isOpen()) { closeMobile(); }
		});
		document.addEventListener('click', function (event) {
			if (!isMobile() || !isOpen()) { return; }
			if (sidebar.contains(event.target) || toggles.some(function (toggle) { return toggle.contains(event.target); })) { return; }
			closeMobile();
		});
		if (mobile && typeof mobile.addEventListener === 'function') {
			mobile.addEventListener('change', applyViewportState);
		} else {
			window.addEventListener('resize', applyViewportState);
		}
		applyViewportState();
	}

	function initializeTheme() {
		var root = document.documentElement;
		var body = document.body;
		var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
		var sidebar = document.querySelector('.sidebar-default');
		var navbar = document.querySelector('.iq-navbar');
		var navbarHeader = document.querySelector('.iq-navbar-header');
		var allowed = {
			scheme: ['auto', 'dark', 'light'], accent: ['default', 'blue', 'gray', 'red', 'yellow', 'pink', 'orange', 'purple'],
			direction: ['ltr', 'rtl'], sidebar: ['default', 'dark', 'color', 'transparent'], sidebar_types: ['mini', 'hover', 'boxed'],
			sidebar_active: ['rounded-one-side', 'rounded-all', 'pill-one-side', 'pill-all'], navbar: ['default', 'glass', 'color', 'sticky', 'transparent']
		};
		function activeValue(preference, fallback) {
			var control = document.querySelector('[data-preference="' + preference + '"].active');
			return control ? control.dataset.value : fallback;
		}
		var state = {
			scheme: root.dataset.uiScheme || 'auto', accent: root.dataset.colorRoot || 'blue', direction: root.dir || 'ltr',
			sidebar: activeValue('sidebar', 'default'), sidebar_types: [], sidebar_active: activeValue('sidebar_active', 'rounded-one-side'), navbar: activeValue('navbar', 'default')
		};
		each('[data-preference="sidebar_types"].active', function (control) { state.sidebar_types.push(control.dataset.value); });
		if (!body.dataset.appearanceUrl) {
			try {
				var localState = JSON.parse(window.localStorage.getItem('psmAppearance') || 'null');
				if (localState && typeof localState === 'object') {
					Object.keys(allowed).forEach(function (key) {
						if (key === 'sidebar_types' && Array.isArray(localState[key])) { state[key] = localState[key].filter(function (value) { return allowed[key].indexOf(value) !== -1; }); }
						else if (allowed[key].indexOf(localState[key]) !== -1) { state[key] = localState[key]; }
					});
				}
			} catch (error) { /* Storage may be disabled. */ }
		}
		var acknowledged = JSON.parse(JSON.stringify(state));

		function resolvedScheme() { return state.scheme === 'auto' ? (media && media.matches ? 'dark' : 'light') : state.scheme; }
		function replaceClasses(element, classes, selected) {
			if (!element) { return; }
			classes.forEach(function (name) { element.classList.remove(name); });
			if (selected) { element.classList.add(selected); }
		}
		function apply() {
			var resolved = resolvedScheme();
			root.dataset.uiScheme = state.scheme;
			root.dataset.bsTheme = resolved;
			root.dataset.colorRoot = state.accent;
			root.dir = state.direction;
			root.classList.toggle('dark', resolved === 'dark');
			body.classList.toggle('dark', resolved === 'dark');
			replaceClasses(body, allowed.accent.map(function (v) { return 'theme-color-' + v; }), 'theme-color-' + state.accent);
			replaceClasses(sidebar, ['sidebar-white', 'sidebar-dark', 'sidebar-color', 'sidebar-transparent'], 'sidebar-' + (state.sidebar === 'default' ? 'white' : state.sidebar));
			allowed.sidebar_types.forEach(function (v) { if (sidebar) { sidebar.classList.toggle('sidebar-' + v, state.sidebar_types.indexOf(v) !== -1); } });
			var activeClasses = { 'rounded-one-side': 'navs-rounded', 'rounded-all': 'navs-rounded-all', 'pill-one-side': 'navs-pill', 'pill-all': 'navs-pill-all' };
			replaceClasses(sidebar, Object.keys(activeClasses).map(function (v) { return activeClasses[v]; }), activeClasses[state.sidebar_active]);
			var navbarClasses = { glass: 'nav-glass', sticky: 'navs-sticky', transparent: 'navs-transparent' };
			replaceClasses(navbar, ['nav-glass', 'navs-sticky', 'navs-transparent'], navbarClasses[state.navbar] || '');
			if (navbarHeader) { navbarHeader.classList.toggle('navs-bg-color', state.navbar === 'color'); }
			each('[data-preference]', function (control) {
				var preference = control.dataset.preference;
				var selected = preference === 'sidebar_types' ? state.sidebar_types.indexOf(control.dataset.value) !== -1 : state[preference] === control.dataset.value;
				control.classList.toggle('active', selected);
				control.setAttribute('aria-pressed', String(selected));
			});
			each('[data-theme-quick-toggle]', function (button) {
				var nextDark = resolved !== 'dark';
				button.dataset.nextScheme = nextDark ? 'dark' : 'light';
			});
			syncThemeIcons(resolved);
			document.dispatchEvent(new CustomEvent('psm:theme-changed', { detail: { scheme: state.scheme, resolved: resolved } }));
		}
		function toast(message) {
			var element = document.querySelector('[data-appearance-toast]');
			if (!element) { return; }
			var bodyElement = element.querySelector('.toast-body');
			if (bodyElement) { bodyElement.textContent = message; }
			if (window.bootstrap) { window.bootstrap.Toast.getOrCreateInstance(element).show(); }
		}
		function save() {
			var url = body.dataset.appearanceUrl;
			if (!url) { try { window.localStorage.setItem('psmAppearance', JSON.stringify(state)); } catch (error) { /* Storage may be disabled. */ } acknowledged = JSON.parse(JSON.stringify(state)); return Promise.resolve(); }
			var params = new URLSearchParams({
				csrf: body.dataset.appearanceCsrf || '', ui_scheme: state.scheme, ui_accent: state.accent, ui_direction: state.direction,
				ui_sidebar: state.sidebar, ui_sidebar_active: state.sidebar_active, ui_navbar: state.navbar
			});
			state.sidebar_types.forEach(function (value) { params.append('ui_sidebar_types[]', value); });
			return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: params.toString() })
				.then(function (response) { if (!response.ok) { throw new Error('No se pudo guardar la apariencia.'); } return response.json(); })
				.then(function (payload) { if (!payload.ok) { throw new Error(payload.error || 'No se pudo guardar la apariencia.'); } acknowledged = JSON.parse(JSON.stringify(state)); })
				.catch(function (error) { state = JSON.parse(JSON.stringify(acknowledged)); apply(); toast(error.message); });
		}
		document.addEventListener('hope:setting-selected', function (event) {
			var key = event.detail.preference;
			var value = event.detail.value;
			if (!allowed[key] || allowed[key].indexOf(value) === -1) { return; }
			if (event.detail.multiple) {
				var index = state[key].indexOf(value);
				if (index === -1) { state[key].push(value); } else { state[key].splice(index, 1); }
			} else { state[key] = value; }
			apply(); save();
		});
		each('[data-theme-quick-toggle]', function (button) { button.addEventListener('click', function () { state.scheme = button.dataset.nextScheme || 'dark'; apply(); save(); }); });
		apply();
		if (media && typeof media.addEventListener === 'function') { media.addEventListener('change', function () { if (state.scheme === 'auto') { apply(); } }); }
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

	function initializeCardSearch() {
		each('[data-card-search]', function (input) {
			input.addEventListener('input', function () {
				var query = input.value.trim().toLocaleLowerCase();
				each('[data-search-text]', function (item) {
					var haystack = (item.dataset.searchText || '').toLocaleLowerCase();
					item.hidden = query !== '' && !haystack.includes(query);
				});
			});
		});
	}

	function initializePasswordToggles() {
		each('[data-password-toggle]', function (button) {
			button.addEventListener('click', function () {
				var input = document.getElementById(button.dataset.passwordToggle || '');
				if (!input) { return; }
				var reveal = input.type === 'password';
				input.type = reveal ? 'text' : 'password';
				button.setAttribute('aria-pressed', String(reveal));
				button.setAttribute('aria-label', reveal ? 'Ocultar contraseña' : 'Mostrar contraseña');
			});
		});
	}

	function initializeDropzones() {
		each('[data-dropzone]', function (dropzone) {
			var input = dropzone.querySelector('[data-image-input]');
			var preview = dropzone.querySelector('[data-image-preview]');
			if (!input) { return; }

			function showPreview(file) {
				if (!preview || !file || !file.type || !file.type.startsWith('image/')) { return; }
				var objectUrl = URL.createObjectURL(file);
				preview.addEventListener('load', function () { URL.revokeObjectURL(objectUrl); }, { once: true });
				preview.src = objectUrl;
			}

			['dragenter', 'dragover'].forEach(function (eventName) {
				dropzone.addEventListener(eventName, function (event) {
					event.preventDefault();
					dropzone.classList.add('is-dragging');
				});
			});

			['dragleave', 'drop'].forEach(function (eventName) {
				dropzone.addEventListener(eventName, function (event) {
					event.preventDefault();
					dropzone.classList.remove('is-dragging');
				});
			});

			dropzone.addEventListener('drop', function (event) {
				var files = event.dataTransfer ? event.dataTransfer.files : null;
				if (!files || files.length === 0) { return; }
				try { input.files = files; } catch (error) { /* FileList can be read-only. */ }
				showPreview(files[0]);
			});

			dropzone.addEventListener('keydown', function (event) {
				if (event.key !== 'Enter' && event.key !== ' ') { return; }
				event.preventDefault();
				input.click();
			});

			input.addEventListener('change', function () {
				showPreview(input.files && input.files[0]);
			});
		});
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
		initializeSidebarAccessibility();
		initializeModals();
		initializeConditionalForms();
		initializeCardSearch();
		initializePasswordToggles();
		initializeDropzones();
		var label = document.getElementById('label');
		if (label && !document.querySelector('[autofocus]')) { label.focus(); }
	});
}());
