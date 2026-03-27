<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;

final class JwtTokenExtractor
{
    public function extract(Request $request): ?string
    {
        $token = $request->headers->get('X-Auth-Token');

        if ($token !== null) {
            return $token;
        }

        return $request->cookies->get('accessToken');
    }
}
