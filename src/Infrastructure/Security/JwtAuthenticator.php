<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class JwtAuthenticator
{
    public function __construct(
        private JwtTokenExtractor $tokenExtractor,
        private JwtTokenDecoder $tokenDecoder,
    ) {}

    public function authenticate(Request $request): AuthenticatedUser|JsonResponse
    {
        $token = $this->tokenExtractor->extract($request);

        if ($token === null) {
            return new JsonResponse([
                'type' => 'https://httpstatuses.com/401',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Missing authentication token',
            ], Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'application/problem+json']);
        }

        $payload = $this->tokenDecoder->decode($token);

        if ($payload === null) {
            return new JsonResponse([
                'type' => 'https://httpstatuses.com/401',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Invalid or expired token',
            ], Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'application/problem+json']);
        }

        return new AuthenticatedUser(
            id: $payload['sub'],
            email: $payload['email'],
        );
    }
}
