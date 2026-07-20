<?php

declare(strict_types=1);

namespace psm\Module\Server\Controller;

use DateTimeImmutable;
use psm\Module\AbstractController;
use psm\Service\Database;
use psm\Service\Statistics\DashboardStatistics;
use psm\Service\Statistics\StatisticsRange;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StatisticsController extends AbstractController
{
    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);
        $this->setActions(['index', 'snapshot'], 'index');
    }

    protected function executeIndex(): string
    {
        $this->twig->addGlobal('subtitle', psm_get_lang('menu', 'server_statistics'));
        $this->twig->addGlobal('needs_charts', true);
        $this->twig->addGlobal('needs_dashboard', true);
        $range = $this->range();
        $snapshot = $this->snapshotData($range);

        $this->setHeaderAccessories($this->twig->render('module/server/statistics/header.tpl.html', [
            'range' => $range->value,
        ]));

        return $this->twig->render('module/server/statistics/index.tpl.html', [
            'range' => $range->value,
            'dashboard' => $snapshot,
            'dashboard_json' => json_encode(
                $snapshot,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
            ),
        ]);
    }

    protected function executeSnapshot(): JsonResponse
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return new JsonResponse(['error' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'GET']);
        }
        $this->xhr = true;
        $response = new JsonResponse($this->snapshotData($this->range()));
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }

    private function range(): StatisticsRange
    {
        return StatisticsRange::tryFrom((string) psm_GET('range', StatisticsRange::Day->value))
            ?? StatisticsRange::Day;
    }

    /** @return array<string, mixed> */
    private function snapshotData(StatisticsRange $range): array
    {
        return $this->statistics()->snapshot(
            $range,
            new DateTimeImmutable(),
            $this->getUser()->getUserId(),
            $this->getUser()->getUserLevel() === PSM_USER_ADMIN,
        )->toArray();
    }

    private function statistics(): DashboardStatistics
    {
        $service = $this->container?->get('service.dashboard_statistics');
        assert($service instanceof DashboardStatistics);
        return $service;
    }
}
