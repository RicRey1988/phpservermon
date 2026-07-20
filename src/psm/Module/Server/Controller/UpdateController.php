<?php

declare(strict_types=1);

namespace psm\Module\Server\Controller;

use DateTimeImmutable;
use psm\Module\AbstractController;
use psm\Service\Database;
use psm\Service\Statistics\DashboardStatistics;
use psm\Service\Statistics\StatisticsRange;
use psm\Util\Cron\CronLock;
use psm\Util\Server\ManualUpdateCoordinator;
use psm\Util\Server\UpdateSummary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UpdateController extends AbstractController
{
    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);

        $this->setCSRFKey('status');
        $this->setActions(array('index', 'run'), 'index');
    }

    protected function executeIndex(): RedirectResponse
    {
        return new RedirectResponse(psm_build_url(array('mod' => 'server_status'), true, false));
    }

    protected function executeRun(): JsonResponse
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return new JsonResponse(
                ['error' => 'Method not allowed.'],
                Response::HTTP_METHOD_NOT_ALLOWED,
                ['Allow' => 'POST'],
            );
        }

        set_time_limit(65);
        $manager = $this->container?->get('util.server.updatemanager');
        $summary = null;
        try {
            $coordinator = new ManualUpdateCoordinator(
                new CronLock(PSM_PATH_LOGS . 'status.cron.lock'),
                static function () use ($manager, &$summary): void {
                    $result = $manager->run();
                    if ($result instanceof UpdateSummary) {
                        $summary = $result;
                    }
                },
            );
            $coordinatorResult = $coordinator->run();
        } catch (Throwable) {
            $coordinatorResult = ManualUpdateCoordinator::UPDATED;
            $summary = new UpdateSummary(0, 1, [0 => 'Status update failed.']);
        }

        $busy = $coordinatorResult === ManualUpdateCoordinator::BUSY;
        $now = new DateTimeImmutable();
        $range = StatisticsRange::tryFrom((string) psm_POST('range', StatisticsRange::Day->value))
            ?? StatisticsRange::Day;
        $statistics = $this->dashboardStatistics()->snapshot(
            $range,
            $now,
            $this->getUser()->getUserId(),
            $this->getUser()->getUserLevel() === PSM_USER_ADMIN,
        )->toArray();
        $payload = [
            'processed' => $summary?->processed() ?? 0,
            'failed' => $summary?->failed() ?? 0,
            'busy' => $busy,
            'checked_at' => $now->format(DATE_ATOM),
            'cards' => $this->currentCards(),
            'summary' => $statistics['summary'],
            'errors' => $summary?->errors() ?? [],
        ];

        $status = $busy
            ? Response::HTTP_CONFLICT
            : (($summary?->failed() ?? 0) > 0 ? 207 : Response::HTTP_OK);
        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** @return list<array<string, int|float|string|null>> */
    private function currentCards(): array
    {
        $join = '';
        $where = '';
        $parameters = [];
        if ($this->getUser()->getUserLevel() > PSM_USER_ADMIN) {
            $join = 'INNER JOIN `' . PSM_DB_PREFIX . 'users_servers` AS us ON us.server_id = s.server_id ';
            $where = 'WHERE us.user_id = :user_id ';
            $parameters['user_id'] = $this->getUser()->getUserId();
        }
        $rows = $this->db->execute(
            'SELECT s.server_id, s.status, s.active, s.warning_threshold_counter, '
            . 's.ssl_cert_expired_time, s.ssl_cert_expiry_days, s.rtime, s.last_check, s.last_online '
            . 'FROM `' . PSM_DB_PREFIX . 'servers` AS s ' . $join . $where . 'ORDER BY s.server_id',
            $parameters,
        );
        $cards = [];
        foreach ($rows as $row) {
            [$tone, $label] = $this->statusPresentation($row);
            $cards[] = [
                'server_id' => (int) $row['server_id'],
                'status_tone' => $tone,
                'status_label' => $label,
                'latency' => $row['rtime'] === null ? null : round((float) $row['rtime'] * 1000, 2),
                'last_check' => psm_timespan($row['last_check']),
                'last_online' => psm_timespan($row['last_online']),
            ];
        }

        return $cards;
    }

    /** @param array<string, mixed> $server @return array{string, string} */
    private function statusPresentation(array $server): array
    {
        if (($server['active'] ?? 'no') === 'no') {
            return ['paused', 'Pausado'];
        }
        if (($server['status'] ?? 'off') === 'off') {
            return ['offline', psm_get_lang('servers', 'offline')];
        }
        if ((int) ($server['warning_threshold_counter'] ?? 0) > 0
            || (($server['ssl_cert_expired_time'] ?? null) !== null
                && (int) ($server['ssl_cert_expiry_days'] ?? 0) > 0)) {
            return ['warning', 'Atención'];
        }

        return ['online', psm_get_lang('servers', 'online')];
    }

    private function dashboardStatistics(): DashboardStatistics
    {
        $service = $this->container?->get('service.dashboard_statistics');
        assert($service instanceof DashboardStatistics);

        return $service;
    }
}
