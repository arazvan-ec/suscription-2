<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

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
            ], fn ($v) => $v !== null),
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
