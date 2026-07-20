(function () {
    'use strict';

    function replaceStatus(html) {
        var fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-status-board]');
        var current = document.querySelector('[data-status-board]');
        if (fresh && current) { current.replaceWith(fresh); }
    }

    function refreshStatus() {
        var url = new URL('index.php', window.location.href);
        url.search = '';
        url.searchParams.set('mod', 'server_status');
        url.searchParams.set('action', 'snapshot');
        url.searchParams.set('xhr', '1');
        return fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) { throw new Error('No se pudo actualizar el estado.'); }
                return response.json();
            })
            .then(function (payload) { replaceStatus(payload.html || ''); });
    }

    function runManualUpdate(button) {
        var token = document.querySelector('[data-status-board] input[name="csrf"]');
        var label = button.querySelector('[data-update-label]');
        var original = label ? label.textContent : '';
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (label) { label.textContent = 'Comprobando…'; }
        fetch(button.dataset.updateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ csrf: token ? token.value : '', range: '24h' }).toString(),
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (payload) { return { response: response, payload: payload }; });
        }).then(function (result) {
            if (!result.response.ok && !result.payload.busy) { throw new Error('Algunas comprobaciones fallaron.'); }
            if (result.payload.busy) {
                if (label) { label.textContent = 'Comprobación ocupada'; }
                return;
            }
            return refreshStatus().then(function () {
                if (label) { label.textContent = 'Estado actualizado'; }
            });
        }).catch(function (error) {
            if (label) { label.textContent = error.message; }
        }).finally(function () {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            window.setTimeout(function () { if (label) { label.textContent = original; } }, 2500);
        });
    }

    function initialize() {
        var root = document.querySelector('[data-status-board]');
        if (!root) { return; }
        var button = document.querySelector('[data-run-update]');
        if (button) { button.addEventListener('click', function () { runManualUpdate(button); }); }
        var seconds = Number(root.dataset.autoRefreshSeconds || 0);
        if (seconds > 0) { window.setInterval(function () { refreshStatus().catch(function () {}); }, seconds * 1000); }
    }

    document.addEventListener('DOMContentLoaded', initialize);
}());
