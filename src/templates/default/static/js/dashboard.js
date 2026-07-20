(function () {
    'use strict';
    var charts = [];
    function destroy() { charts.forEach(function (chart) { chart.destroy(); }); charts = []; }
    function theme() { return document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light'; }
    function render() {
        var root = document.querySelector('[data-statistics-dashboard]');
        var source = document.getElementById('dashboard-data');
        if (!root || !source || typeof window.ApexCharts === 'undefined') { return; }
        var data;
        try { data = JSON.parse(source.textContent || '{}'); } catch (error) { return; }
        destroy();
        var common = { theme: { mode: theme() }, dataLabels: { enabled: false }, noData: { text: 'Sin datos para este periodo' }, xaxis: { type: 'datetime' }, grid: { borderColor: theme() === 'dark' ? '#343946' : '#e9ecef' } };
        var uptimeElement = document.getElementById('uptime-chart');
        if (uptimeElement) {
            var uptime = new ApexCharts(uptimeElement, Object.assign({}, common, { chart: { type: 'area', height: 304, toolbar: { show: false } }, series: [{ name: 'Disponibilidad', data: (data.uptime || []).map(function (point) { return { x: point.x, y: point.y }; }) }], yaxis: { min: 0, max: 100, labels: { formatter: function (value) { return value.toFixed(0) + '%'; } } }, stroke: { curve: 'smooth', width: 3 }, colors: ['#3a57e8'], fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: .04 } } }));
            uptime.render(); charts.push(uptime);
        }
        var latencyElement = document.getElementById('latency-chart');
        if (latencyElement) {
            var latency = new ApexCharts(latencyElement, Object.assign({}, common, { chart: { type: 'line', height: 304, toolbar: { show: false } }, series: [{ name: 'Latencia media', data: (data.latency || []).map(function (point) { return { x: point.x, y: point.avg }; }) }], yaxis: { min: 0, labels: { formatter: function (value) { return value.toFixed(1) + ' ms'; } } }, stroke: { curve: 'smooth', width: 3 }, colors: ['#3cc3d5'] }));
            latency.render(); charts.push(latency);
        }
    }
    document.addEventListener('DOMContentLoaded', render);
    document.addEventListener('psm:theme-changed', render);
}());
