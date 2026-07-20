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
 * @author      Michael Greenhill
 * @author      Pepijn Over <pep@mailbox.org>
 * @copyright   Copyright (c) 2008-2017 Pepijn Over <pep@mailbox.org>
 * @license     http://www.gnu.org/licenses/gpl.txt GNU GPL v3
 * @version     Release: @package_version@
 * @link        http://www.phpservermonitor.org/
 **/

namespace psm\Module\Server\Controller;

use DateTimeImmutable;
use psm\Service\Database;
use psm\Service\ServerImage\ServerImageStorage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Status module
 */
class StatusController extends AbstractServerController
{

    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);

        $this->setCSRFKey('status');
        $this->setActions(array('index', 'saveLayout', 'snapshot'), 'index');
    }

    /**
     * Prepare the template to show a list of all servers
     */
    protected function executeIndex()
    {
        $this->twig->addGlobal('subtitle', psm_get_lang('menu', 'server_status'));
        $this->twig->addGlobal('needs_status', true);
        $layout = $this->getUser()->getUserPref('status_layout', 0);
        $layout_data = array(
            'label_none' => psm_get_lang('system', 'none'),
            'label_last_check' => psm_get_lang('servers', 'last_check'),
            'label_last_online' => psm_get_lang('servers', 'last_online'),
            'label_last_offline' => psm_get_lang('servers', 'last_offline'),
            'label_online' => psm_get_lang('servers', 'online'),
            'label_offline' => psm_get_lang('servers', 'offline'),
            'label_warning' => 'Atención',
            'label_paused' => 'Pausado',
            'label_rtime' => psm_get_lang('servers', 'latency'),
            'block_layout_active' => ($layout == 0) ? 'active' : '',
            'list_layout_active' => ($layout != 0) ? 'active' : '',
            'label_add_server' => psm_get_lang('system', 'add_new'),
            'layout' => $layout,
            'url_save' => psm_build_url(array('mod' => 'server', 'action' => 'edit')),
            'url_update' => psm_build_url(array('mod' => 'server_update', 'action' => 'run')),
        );

        $auto_refresh_seconds = psm_get_conf('auto_refresh_servers');
        if (intval($auto_refresh_seconds) > 0) {
            $layout_data['auto_refresh'] = true;
            $layout_data['auto_refresh_seconds'] = (int) $auto_refresh_seconds;
        }

        $this->setHeaderAccessories($this->twig->render('module/server/status/header.tpl.html', $layout_data));
        $servers = $this->getServers();
        $layout_data['servers'] = array();

        foreach ($servers as $server) {
            $server['last_checked_nice'] = psm_timespan($server['last_check']);
            $server['last_online_nice'] = psm_timespan($server['last_online']);
            $server['last_offline_nice'] = psm_timespan($server['last_offline']);
            $server['last_offline_duration_nice'] = "";
            if ($server['last_offline_nice'] != psm_get_lang('system', 'never')) {
                $server['last_offline_duration_nice'] = "(" . $server['last_offline_duration'] . ")";
            }
            $server['url_view'] = psm_build_url(
                array('mod' => 'server', 'action' => 'view', 'id' => $server['server_id'], 'back_to' => 'server_status')
            );
            $server['image_url'] = $this->serverImageStorage()->urlFor($server['image_file'] ?? null);

            if ($server['active'] === 'no') {
                $server['status_tone'] = 'paused';
                $server['status_label'] = $layout_data['label_paused'];
            } elseif ($server['status'] === 'off') {
                $server['status_tone'] = 'offline';
                $server['status_label'] = $layout_data['label_offline'];
            } elseif ($server['warning_threshold_counter'] > 0) {
                $server['status_tone'] = 'warning';
                $server['status_label'] = $layout_data['label_warning'];
            } elseif ($server['ssl_cert_expired_time'] !== null && $server['ssl_cert_expiry_days'] > 0) {
                $server['status_tone'] = 'warning';
                $server['status_label'] = $layout_data['label_warning'];
            } else {
                $server['status_tone'] = 'online';
                $server['status_label'] = $layout_data['label_online'];
            }
            $layout_data['servers'][] = $server;
        }

        if ($this->isXHR() || isset($_SERVER["HTTP_X_REQUESTED_WITH"])) {
            $this->xhr = true;
            $layout_data['auto_refresh'] = false;
        }

        return $this->twig->render('module/server/status/index.tpl.html', $layout_data);
    }

    protected function executeSaveLayout()
    {
        if ($this->isXHR()) {
            $layout = psm_POST('layout', 0);
            $this->getUser()->setUserPref('status_layout', $layout);

            $response = new \Symfony\Component\HttpFoundation\JsonResponse();
            $response->setData(array(
                'layout' => $layout,
            ));
            return $response;
        }
    }

    protected function executeSnapshot(): JsonResponse
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return new JsonResponse(
                ['error' => 'Method not allowed.'],
                Response::HTTP_METHOD_NOT_ALLOWED,
                ['Allow' => 'GET'],
            );
        }

        $this->xhr = true;
        $response = new JsonResponse([
            'html' => $this->executeIndex(),
            'checked_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function serverImageStorage(): ServerImageStorage
    {
        $service = $this->container?->get('service.server_image.storage');
        assert($service instanceof ServerImageStorage);

        return $service;
    }
}
