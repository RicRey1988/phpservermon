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

	function initializeDashboard() {
		var root = document.querySelector('[data-dashboard]');
		if (!root) { return; }
		renderCharts(root);

		var seconds = Number(root.dataset.autoRefreshSeconds || 0);
		if (seconds > 0 && !document.body.dataset.dashboardRefreshStarted) {
			document.body.dataset.dashboardRefreshStarted = '1';
			window.setInterval(function () {
				fetch(window.location.href, {
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
					credentials: 'same-origin',
					cache: 'no-store'
				}).then(function (response) {
					if (!response.ok) { throw new Error('Status refresh failed'); }
					return response.text();
				}).then(function (html) {
					var parsed = new DOMParser().parseFromString(html, 'text/html');
					var fresh = parsed.querySelector('[data-dashboard]');
					var current = document.querySelector('[data-dashboard]');
					if (!fresh || !current) { return; }
					current.replaceWith(fresh);
					renderCharts(fresh);
				}).catch(function () {
					// The next scheduled refresh retries while keeping the last snapshot visible.
				});
			}, seconds * 1000);
		}
	}

	document.addEventListener('DOMContentLoaded', initializeDashboard);
}());
