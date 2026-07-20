<?php

declare(strict_types=1);

namespace psm\Module\User\Controller;

use psm\Module\AbstractController;
use psm\Service\Database;
use psm\Service\Notification\UserNotificationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController extends AbstractController
{
    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);
        $this->setCSRFKey('notifications');
        $this->setActions(['index', 'markRead', 'markAllRead'], 'index');
    }

    protected function executeIndex(): string
    {
        $this->twig->addGlobal('subtitle', 'Notificaciones');
        $userId = $this->getUser()->getUserId();

        return $this->twig->render('module/user/notification/index.tpl.html', [
            'notifications' => $this->repository()->allForUser(
                $userId,
                $this->getUser()->getUserLevel() === PSM_USER_ADMIN,
            ),
            'url_mark_read' => psm_build_url(['mod' => 'user_notification', 'action' => 'markRead']),
            'url_mark_all_read' => psm_build_url(['mod' => 'user_notification', 'action' => 'markAllRead']),
        ]);
    }

    protected function executeMarkRead(): JsonResponse
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $this->methodNotAllowed();
        }
        $this->repository()->markRead((int) psm_POST('notification_id', 0), $this->getUser()->getUserId());

        return new JsonResponse(['ok' => true, 'unread' => $this->repository()->unreadCount($this->getUser()->getUserId())]);
    }

    protected function executeMarkAllRead(): JsonResponse
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $this->methodNotAllowed();
        }
        $this->repository()->markAllRead($this->getUser()->getUserId());

        return new JsonResponse(['ok' => true, 'unread' => 0]);
    }

    private function methodNotAllowed(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Method not allowed.'],
            Response::HTTP_METHOD_NOT_ALLOWED,
            ['Allow' => 'POST'],
        );
    }

    private function repository(): UserNotificationRepository
    {
        $repository = $this->container?->get('service.notification.user_repository');
        assert($repository instanceof UserNotificationRepository);

        return $repository;
    }
}
