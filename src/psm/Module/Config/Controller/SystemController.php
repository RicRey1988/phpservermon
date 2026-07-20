<?php

declare(strict_types=1);

namespace psm\Module\Config\Controller;

use psm\Module\AbstractController;
use psm\Service\Database;
use psm\Service\System\SystemHealthService;
use psm\Service\SystemUpdate\GitHubReleaseClient;
use psm\Service\SystemUpdate\SystemUpdater;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SystemController extends AbstractController
{
    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);
        $this->setMinUserLevelRequired(PSM_USER_ADMIN);
        $this->setCSRFKey('system_update');
        $this->setActions(['index', 'install'], 'index');
    }

    protected function executeIndex(): string
    {
        $this->twig->addGlobal('subtitle', 'Sistema y actualizaciones');
        $release = null;
        $releaseError = null;
        try {
            $release = $this->releaseClient()->latest();
        } catch (Throwable $exception) {
            $releaseError = $exception->getMessage();
        }

        return $this->twig->render('module/config/system.tpl.html', [
            'health' => $this->health()->collect(),
            'release' => $release,
            'release_error' => $releaseError,
            'current_version' => PSM_VERSION,
            'update_available' => $release?->isNewerThan(PSM_VERSION) ?? false,
            'install_url' => psm_build_url(['mod' => 'config_system', 'action' => 'install']),
            'database_upgrade_url' => 'install.php',
        ]);
    }

    protected function executeInstall(): Response|string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return new Response('Method not allowed.', Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
        }
        if ((string) psm_POST('confirm_update', '') !== 'yes') {
            $this->addMessage('Confirma la actualización antes de continuar.', 'warning');
            return $this->executeIndex();
        }
        $targetVersion = trim((string) psm_POST('target_version', ''));
        if (preg_match('/^\d+\.\d+\.\d+-hs$/', $targetVersion) !== 1) {
            $this->addMessage('Escribe exactamente la versión HS mostrada para confirmar.', 'warning');
            return $this->executeIndex();
        }
        set_time_limit(300);
        try {
            $result = $this->updater()->update(PSM_VERSION, $targetVersion);
            return $this->twig->render('module/config/system-updated.tpl.html', [
                'target_version' => $result->version,
                'changed_files' => $result->changedFiles,
                'database_upgrade_required' => $result->databaseUpgradeRequired,
                'database_upgrade_url' => 'install.php',
            ]);
        } catch (Throwable $exception) {
            $this->addMessage($exception->getMessage(), 'error');
            return $this->executeIndex();
        }
    }

    private function health(): SystemHealthService
    {
        $service = $this->container->get('service.system.health');
        assert($service instanceof SystemHealthService);
        return $service;
    }

    private function releaseClient(): GitHubReleaseClient
    {
        $service = $this->container->get('service.system.release_client');
        assert($service instanceof GitHubReleaseClient);
        return $service;
    }

    private function updater(): SystemUpdater
    {
        $service = $this->container->get('service.system.updater');
        assert($service instanceof SystemUpdater);
        return $service;
    }
}
