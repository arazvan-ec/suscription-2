<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Psr\Log\LoggerInterface;

final readonly class JwtTokenDecoder
{
    public function __construct(
        private string $jwtSecret,
        private LoggerInterface $logger,
    ) {}

    /**
     * Decodes a JWT token and returns the payload.
     * Returns null if the token is invalid or expired.
     *
     * @return array{sub: string, email: string, exp: int}|null
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            $this->logger->warning('JWT: invalid token format');
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('JWT: invalid signature');
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        if ($decoded === null) {
            $this->logger->warning('JWT: failed to decode payload');
            return null;
        }

        // Check expiration
        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            $this->logger->info('JWT: token expired');
            return null;
        }

        // Validate required claims
        if (!isset($decoded['sub'], $decoded['email'])) {
            $this->logger->warning('JWT: missing required claims (sub, email)');
            return null;
        }

        return $decoded;
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
