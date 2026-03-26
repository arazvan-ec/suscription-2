---
name: symfony-api
description: REST API patterns for Symfony 8 following El Confidencial conventions
triggers:
  - controller
  - endpoint
  - API
  - REST
  - route
---

# Symfony API Skill

## Purpose

Guide Claude to generate REST API endpoints following El Confidencial's
conventions: three-layer architecture, RFC 7807 errors, JWT auth, and
CDN-friendly responses.

## Architecture Layers

Always structure API code in three layers:

```
src/
├── Domain/           # Entities, Value Objects, Domain Events, Repository Interfaces
├── Application/      # Use Cases (Command/Query handlers), DTOs, Ports
└── Infrastructure/   # Controllers, Doctrine repos, HTTP clients, Messenger handlers
```

## Controller Pattern

```php
#[Route('/api/v1')]
final class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {}

    #[Route('/subscriptions/{entityType}/{entityId}', methods: ['GET'])]
    public function getSubscriptionStatus(
        string $entityType,
        string $entityId,
        #[CurrentUser] UserInterface $user,
    ): JsonResponse {
        $result = $this->queryBus->ask(
            new GetSubscriptionStatusQuery($user->getId(), $entityType, $entityId)
        );

        return $this->json($result, Response::HTTP_OK, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('/subscriptions', methods: ['POST'])]
    public function subscribe(
        #[MapRequestPayload] SubscribeRequest $request,
        #[CurrentUser] UserInterface $user,
    ): JsonResponse {
        $this->commandBus->dispatch(
            new SubscribeCommand($user->getId(), $request->entityType, $request->entityId)
        );

        return $this->json(['subscribed' => true], Response::HTTP_OK);
    }

    #[Route('/subscriptions/{entityType}/{entityId}', methods: ['DELETE'])]
    public function unsubscribe(
        string $entityType,
        string $entityId,
        #[CurrentUser] UserInterface $user,
    ): JsonResponse {
        $this->commandBus->dispatch(
            new UnsubscribeCommand($user->getId(), $entityType, $entityId)
        );

        return $this->json(['subscribed' => false], Response::HTTP_OK);
    }
}
```

## Error Handling — RFC 7807

All errors must follow RFC 7807 Problem Details:

```php
final class ApiProblemResponse extends JsonResponse
{
    public function __construct(
        string $type,
        string $title,
        int $status,
        ?string $detail = null,
        array $extra = [],
    ) {
        parent::__construct(
            array_filter([
                'type' => $type,
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
                ...$extra,
            ]),
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
```

## Authentication

- JWT via `web-token/jwt-framework`
- Token read from header `X-Auth-Token` or cookie `accessToken`
- Use `#[CurrentUser]` attribute in controller signatures
- Unauthenticated requests return 401 with RFC 7807 body

## Response Headers

- Personalized responses: `Cache-Control: private, no-store`
- Public list responses: `Cache-Control: public, max-age=60` + `Vary: X-User-Level`
- Always include `Content-Type: application/json`

## Request Validation

- Use `#[MapRequestPayload]` with Symfony Validator constraints
- Validation errors automatically return 422 with RFC 7807

## Naming Conventions

- Controllers: `{Resource}Controller`
- DTOs: `{Action}Request`, `{Resource}Response`
- Commands: `{Verb}{Noun}Command`
- Queries: `Get{Noun}Query`
- Handlers: `{Command/Query}Handler`
