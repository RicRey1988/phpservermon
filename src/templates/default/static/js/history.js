(function () {
	'use strict';

	function selected(name) {
		return document.querySelector('input[name="' + name + '"]:checked');
	}

	function updateScale(chart, input) {
		if (!chart || !input) { return; }
		chart.options.scales.xAxes[0].time.min = Number.parseInt(input.value, 10);
		chart.options.scales.xAxes[0].time.unit = input.id;
		chart.update(0);
	}

	function bind(name, chart) {
		document.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
			input.addEventListener('change', function () { updateScale(chart, selected(name)); });
		});
		updateScale(chart, selected(name));
	}

	if (typeof historyShort !== 'undefined') { bind('timeframe_short', historyShort); }
	if (typeof historyLong !== 'undefined') { bind('timeframe_long', historyLong); }
}());
