(function () {
	'use strict';

	var charts = [];

	function chartTheme() {
		return document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light';
	}

	function destroyCharts() {
		charts.forEach(function (chart) { chart.destroy(); });
		charts = [];
	}

	function renderCharts(root) {
		var source = root.querySelector('#dashboard-data') || document.getElementById('dashboard-data');
		if (!source || typeof window.ApexCharts === 'undefined') { return; }

		var data;
		try {
			data = JSON.parse(source.textContent || '{}');
		} catch (error) {
			return;
		}

		destroyCharts();
		var common = {
			chart: { toolbar: { show: false }, animations: { enabled: true } },
			theme: { mode: chartTheme() },
			dataLabels: { enabled: false },
			noData: { text: 'Sin datos para este periodo' },
			xaxis: { type: 'datetime' }
		};

		var uptimeElement = root.querySelector('#uptime-chart');
		if (uptimeElement) {
			var uptime = new ApexCharts(uptimeElement, Object.assign({}, common, {
				chart: { type: 'area', height: 304, toolbar: { show: false } },
				series: [{ name: 'Disponibilidad', data: (data.uptime || []).map(function (point) { return { x: point.x, y: point.y }; }) }],
				yaxis: { min: 0, max: 100, labels: { formatter: function (value) { return value.toFixed(0) + '%'; } } },
				stroke: { curve: 'smooth', width: 3 },
				colors: ['#3a57e8']
			}));
			uptime.render();
			charts.push(uptime);
		}

		var latencyElement = root.querySelector('#latency-chart');
		if (latencyElement) {
			var latency = new ApexCharts(latencyElement, Object.assign({}, common, {
				chart: { type: 'line', height: 304, toolbar: { show: false } },
				series: [{ name: 'Latencia media', data: (data.latency || []).map(function (point) { return { x: point.x, y: point.avg }; }) }],
				yaxis: { min: 0, labels: { formatter: function (value) { return value.toFixed(1) + ' ms'; } } },
				stroke: { curve: 'smooth', width: 3 },
				colors: ['#079aa2']
			}));
			latency.render();
			charts.push(latency);
		}
	}

	function formatSummary(key, value) {
		if (value === null || typeof value === 'undefined') { return '—'; }
		if (key === 'uptime_percentage') { return value + '%'; }
		if (key === 'latency_avg') { return value + ' ms'; }
		return String(value);
	}

	function applyStatusCards(payload) {
		(payload.cards || []).forEach(function (server) {
			document.querySelectorAll('[data-server-id="' + server.server_id + '"]').forEach(function (card) {
				['online', 'warning', 'offline', 'paused'].forEach(function (tone) {
					card.classList.remove('status-' + tone);
				});
				card.classList.add('status-' + server.status_tone);
				var badge = card.querySelector('.status-badge');
				if (badge) {
					['online', 'warning', 'offline', 'paused'].forEach(function (tone) {
						badge.classList.remove('status-badge--' + tone);
					});
					badge.classList.add('status-badge--' + server.status_tone);
					badge.textContent = server.status_label;
					badge.setAttribute('aria-label', server.status_label);
				}
				var lastCheck = card.querySelector('[data-server-last-check]');
				var lastOnline = card.querySelector('[data-server-last-online]');
				var latency = card.querySelector('[data-server-latency]');
				if (lastCheck) { lastCheck.textContent = server.last_check; }
				if (lastOnline) { lastOnline.textContent = server.last_online; }
				if (latency && server.latency !== null) { latency.textContent = server.latency + ' ms'; }
			});
		});

		Object.keys(payload.summary || {}).forEach(function (key) {
			var element = document.querySelector('[data-summary-key="' + key + '"] [data-summary-value]');
			if (element) { element.textContent = formatSummary(key, payload.summary[key]); }
		});
	}

	function replaceDashboard(html) {
		var parsed = new DOMParser().parseFromString(html, 'text/html');
		var fresh = parsed.querySelector('[data-dashboard]');
		var current = document.querySelector('[data-dashboard]');
		if (!fresh || !current) { return; }
		current.replaceWith(fresh);
		renderCharts(fresh);
	}

	function refreshSnapshot() {
		var range = document.getElementById('dashboard-range');
		var url = new URL('index.php', window.location.href);
		url.search = '';
		url.searchParams.set('mod', 'server_status');
		url.searchParams.set('action', 'snapshot');
		url.searchParams.set('xhr', '1');
		url.searchParams.set('range', range ? range.value : '24h');

		return fetch(url.toString(), {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin',
			cache: 'no-store'
		}).then(function (response) {
			if (!response.ok) { throw new Error('Snapshot refresh failed'); }
			return response.json();
		}).then(function (payload) {
			replaceDashboard(payload.html || '');
		});
	}

	function runManualUpdate(button) {
		var token = document.querySelector('[data-dashboard] input[name="csrf"]');
		var range = document.getElementById('dashboard-range');
		var label = button.querySelector('[data-update-label]');
		var originalLabel = label ? label.textContent : '';
		var body = new URLSearchParams({
			csrf: token ? token.value : '',
			range: range ? range.value : '24h'
		});

		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		if (label) { label.textContent = 'Comprobando…'; }
		fetch(button.dataset.updateUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
			body: body.toString(),
			credentials: 'same-origin',
			cache: 'no-store'
		}).then(function (response) {
			return response.json().then(function (payload) { return { response: response, payload: payload }; });
		}).then(function (result) {
			applyStatusCards(result.payload);
			if (label) {
				label.textContent = result.payload.busy ? 'Comprobación ocupada' : 'Estado actualizado';
			}
			return refreshSnapshot();
		}).catch(function () {
			if (label) { label.textContent = 'No se pudo actualizar'; }
		}).finally(function () {
			button.disabled = false;
			button.removeAttribute('aria-busy');
			window.setTimeout(function () { if (label) { label.textContent = originalLabel; } }, 2500);
		});
	}

	function initializeDashboard() {
		var root = document.querySelector('[data-dashboard]');
		if (!root) { return; }
		renderCharts(root);
		var updateButton = document.querySelector('[data-run-update]');
		if (updateButton && !updateButton.dataset.updateBound) {
			updateButton.dataset.updateBound = '1';
			updateButton.addEventListener('click', function () { runManualUpdate(updateButton); });
		}

		var seconds = Number(root.dataset.autoRefreshSeconds || 0);
		if (seconds > 0 && !document.body.dataset.dashboardRefreshStarted) {
			document.body.dataset.dashboardRefreshStarted = '1';
			window.setInterval(function () {
				refreshSnapshot().catch(function () {
					// The next scheduled refresh retries while keeping the last snapshot visible.
				});
			}, seconds * 1000);
		}
	}

	document.addEventListener('DOMContentLoaded', initializeDashboard);
}());
