<?php

/**
 * PHP Server Monitor
 * Monitor your servers and websites.
 *
 * This file is part of PHP Server Monitor.
 * PHP Server Monitor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PHP Server Monitor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP Server Monitor.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     phpservermon
 * @author      Pepijn Over <pep@mailbox.org>
 * @copyright   Copyright (c) 2008-2017 Pepijn Over <pep@mailbox.org>
 * @license     http://www.gnu.org/licenses/gpl.txt GNU GPL v3
 * @version     Release: @package_version@
 * @link        http://www.phpservermonitor.org/
 **/

$maintenanceFile = __DIR__ . '/.psm-update/maintenance.json';
if (is_file($maintenanceFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('Retry-After: 120');
    $maintenance = json_decode((string) file_get_contents($maintenanceFile), true);
    $message = is_array($maintenance) ? (string) ($maintenance['message'] ?? '') : '';
    $requestId = is_array($maintenance) ? (string) ($maintenance['request_id'] ?? '') : '';
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Mantenimiento</title><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;background:#111827;color:#f9fafb">'
        . '<main style="max-width:42rem;padding:2rem"><h1>Actualización en curso</h1><p>'
        . htmlspecialchars($message !== '' ? $message : 'Intenta de nuevo en unos minutos.', ENT_QUOTES, 'UTF-8')
        . '</p><code>' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') . '</code></main></body></html>';
    exit;
}

require __DIR__ . '/src/bootstrap.php';

psm_no_cache();

if (isset($_GET["logout"])) {
    $router->getService('user')->doLogout();
    // logged out, redirect to login
    header('Location: ' . psm_build_url());
    die();
}

$mod = psm_GET('mod', PSM_MODULE_DEFAULT);

try {
    $router->run($mod);
} catch (\InvalidArgumentException $e) {
    // invalid module, try the default one
    // it that somehow also doesnt exist, we have a bit of an issue
    // and we really have no reason catch it
    $router->run(PSM_MODULE_DEFAULT);
}
