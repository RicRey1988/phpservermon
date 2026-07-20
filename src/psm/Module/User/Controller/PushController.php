<?php

declare(strict_types=1);

namespace psm\Module\User\Controller;

use InvalidArgumentException;
use JsonException;
use psm\Module\AbstractController;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use psm\Service\Database;
use psm\Service\Push\PushSubscriptionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PushController extends AbstractController
{
    public function __construct(Database $db, \Twig\Environment $twig)
    {
        parent::__construct($db, $twig);
        $this->setCSRFKey('push');
        $this->setActions(['subscribe', 'unsubscribe', 'test'], 'subscribe');
    }

    protected function executeSubscribe(): JsonResponse
    {
        if (!$this->isPost()) {
            return $this->methodNotAllowed();
        }
        try {
            $payload = $this->jsonBody();
            $this->repository()->upsert(
                $this->getUser()->getUserId(),
                $payload,
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            );
        } catch (InvalidArgumentException|JsonException $exception) {
            return new JsonResponse(['ok' => false, 'error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['ok' => true]);
    }

    protected function executeUnsubscribe(): JsonResponse
    {
        if (!$this->isPost()) {
            return $this->methodNotAllowed();
        }
        try {
            $payload = $this->jsonBody();
        } catch (JsonException $exception) {
            return new JsonResponse(['ok' => false, 'error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ($endpoint === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Missing endpoint.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->repository()->deleteOwned($endpoint, $this->getUser()->getUserId());

        return new JsonResponse(['ok' => true]);
    }

    protected function executeTest(): JsonResponse
    {
        if (!$this->isPost()) {
            return $this->methodNotAllowed();
        }
        $registry = $this->container?->get('notification.registry');
        if ($registry === null || !$registry->has('webpush')) {
            return new JsonResponse(['ok' => false, 'error' => 'Web Push is not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $result = $registry->get('webpush')->send(
            new NotificationMessage(
                'PHP Server Monitor',
                'Las notificaciones de este dispositivo funcionan correctamente.',
                psm_build_url(['mod' => 'server_status'], true, false),
            ),
            new Recipient($this->getUser()->getUserId()),
        );

        return new JsonResponse([
            'ok' => $result->isSuccess(),
            'status' => $result->status(),
            'message' => $result->message(),
        ], $result->isSuccess() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode(is_string($raw) ? $raw : '', true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new JsonException('A JSON object is required.');
        }

        return $decoded;
    }

    private function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function methodNotAllowed(): JsonResponse
    {
        return new JsonResponse(['error' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
    }

    private function repository(): PushSubscriptionRepository
    {
        $repository = $this->container?->get('service.push.subscription_repository');
        assert($repository instanceof PushSubscriptionRepository);
        return $repository;
    }
}
