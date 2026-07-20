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
 * @since       phpservermon 3.0.0
 **/

namespace psm\Util\Server;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Run an update on all servers.
 */
class UpdateManager
{
    private ContainerInterface $container;

    /** @var callable */
    private $updaterFactory;

    /** @var callable */
    private $notifierFactory;

    /** @var callable */
    private $archiveFactory;

    public function __construct(
        ContainerInterface $container,
        ?callable $updaterFactory = null,
        ?callable $notifierFactory = null,
        ?callable $archiveFactory = null
    ) {
        $this->container = $container;
        $this->updaterFactory = $updaterFactory
            ?? static fn ($db) => new Updater\StatusUpdater($db);
        $this->notifierFactory = $notifierFactory
            ?? fn ($db) => new Updater\StatusNotifier($db, $this->container->get('notification.registry'));
        $this->archiveFactory = $archiveFactory
            ?? static fn ($db) => new ArchiveManager($db);
    }

    /**
     * Go :-)
     *
     * @param boolean $skip_perms if TRUE, no user permissions will be taken in account and all servers will be updated
     * @param string|null $status If all servers (null), or just `on` or `off` should be checked.
     * @return UpdateSummary
     */
    public function run($skip_perms = false, $status = null)
    {
        if (false === in_array($status, ['on', 'off'], true)) {
            $status = null;
        }

        // check if we need to restrict the servers to a certain user
        $sql_join = '';

        if (!$skip_perms && $this->container->get('user')->getUserLevel() > PSM_USER_ADMIN) {
            // restrict by user_id
            $sql_join = "JOIN `" . PSM_DB_PREFIX . "users_servers` AS `us` ON (
                        `us`.`user_id`={$this->container->get('user')->getUserId()}
                        AND `us`.`server_id`=`s`.`server_id`
                        )";
        }

        $sql = "SELECT `s`.`server_id`,`s`.`ip`,`s`.`port`,`s`.`label`,`s`.`type`,`s`.`pattern`,`s`.`header_name`,
            `s`.`header_value`,`s`.`status`,`s`.`active`,`s`.`email`,`s`.`sms`,`s`.`pushover`,`s`.`webhook`,`s`.`telegram`
                FROM `" . PSM_DB_PREFIX . "servers` AS `s`
                {$sql_join}
                WHERE `active`='yes' " . ($status !== null ? ' AND `status` = \'' . $status . '\'' : '');

        $servers = $this->container->get('db')->query($sql);

        $database = $this->container->get('db');
        $updater = ($this->updaterFactory)($database);
        $notifier = ($this->notifierFactory)($database);
        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($servers as $server) {
            $serverId = (int) $server['server_id'];
            try {
                $statusOld = $server['status'] === 'on';
                $statusNew = (bool) $updater->update($serverId);
                $notifier->notify($serverId, $statusOld, $statusNew);
                $archive = ($this->archiveFactory)($database);
                $archive->archive($serverId);
                $archive->cleanup($serverId);
                $processed++;
            } catch (\Throwable) {
                $failed++;
                $errors[$serverId] = 'Server update failed.';
            }
        }
        if ($notifier->combine) {
            try {
                $notifier->notifyCombined();
            } catch (\Throwable) {
                $failed++;
                $errors[0] = 'Combined notification delivery failed.';
            }
        }

        return new UpdateSummary($processed, $failed, $errors);
    }
}
