/*
 * Hope UI 2.0 settings contract, adapted for PHP Server Monitor.
 * The original data-setting/data-name/data-value interface is retained;
 * persistence and DOM updates are handled by the vanilla app-shell adapter.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var control = event.target.closest('[data-setting][data-preference]');
        if (!control) { return; }
        document.dispatchEvent(new CustomEvent('hope:setting-selected', {
            detail: {
                preference: control.dataset.preference,
                value: control.dataset.value,
                multiple: control.dataset.multiple === 'true'
            }
        }));
    });
}());
