<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Command\SubscribeCommand;
use App\Application\Command\SubscribeHandler;
use App\Application\Command\UnsubscribeCommand;
use App\Application\Command\UnsubscribeHandler;
use App\Application\DTO\SubscribeRequest;
use App\Application\Query\GetSubscriptionStatusHandler;
use App\Application\Query\GetSubscriptionStatusQuery;
use App\Application\Query\GetUserSubscriptionsHandler;
use App\Application\Query\GetUserSubscriptionsQuery;
use App\Domain\ValueObject\EntityType;
use App\Infrastructure\Http\ApiProblemResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use App\Infrastructure\Security\JwtAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final readonly class SubscriptionController
{
    public function __construct(
        private JwtAuthenticator $authenticator,
        private SubscribeHandler $subscribeHandler,
        private UnsubscribeHandler $unsubscribeHandler,
        private GetSubscriptionStatusHandler $statusHandler,
        private GetUserSubscriptionsHandler $listHandler,
    ) {}

    #[Route('/subscriptions/{entityType}/{entityId}', methods: ['GET'])]
    public function getStatus(
        Request $request,
        string $entityType,
        string $entityId,
    ): JsonResponse {
        $user = $this->authenticator->authenticate($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $type = EntityType::tryFrom($entityType);

        if ($type === null) {
            return new ApiProblemResponse(
                'https://httpstatuses.com/400',
                'Bad Request',
                400,
                "Invalid entity type: {$entityType}. Valid types: journalist, tag, section",
            );
        }

        $subscribed = ($this->statusHandler)(
            new GetSubscriptionStatusQuery($user->id, $type, $entityId)
        );

        return new JsonResponse(
            ['subscribed' => $subscribed],
            Response::HTTP_OK,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    #[Route('/subscriptions', methods: ['POST'])]
    public function subscribe(
        Request $request,
        #[MapRequestPayload] SubscribeRequest $payload,
    ): JsonResponse {
        $user = $this->authenticator->authenticate($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $type = EntityType::from($payload->entityType);

        ($this->subscribeHandler)(
            new SubscribeCommand($user->id, $user->email, $type, $payload->entityId)
        );

        return new JsonResponse(
            ['subscribed' => true],
            Response::HTTP_OK,
        );
    }

    #[Route('/subscriptions/{entityType}/{entityId}', methods: ['DELETE'])]
    public function unsubscribe(
        Request $request,
        string $entityType,
        string $entityId,
    ): JsonResponse {
        $user = $this->authenticator->authenticate($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $type = EntityType::tryFrom($entityType);

        if ($type === null) {
            return new ApiProblemResponse(
                'https://httpstatuses.com/400',
                'Bad Request',
                400,
                "Invalid entity type: {$entityType}",
            );
        }

        ($this->unsubscribeHandler)(
            new UnsubscribeCommand($user->id, $type, $entityId)
        );

        return new JsonResponse(
            ['subscribed' => false],
            Response::HTTP_OK,
        );
    }

    #[Route('/subscriptions', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->authenticator->authenticate($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $subscriptions = ($this->listHandler)(
            new GetUserSubscriptionsQuery($user->id)
        );

        return new JsonResponse(
            ['subscriptions' => $subscriptions],
            Response::HTTP_OK,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
