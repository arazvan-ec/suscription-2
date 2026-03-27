<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\JwtTokenDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JwtTokenDecoderTest extends TestCase
{
    private const string SECRET = 'test-secret-key';

    private JwtTokenDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new JwtTokenDecoder(self::SECRET, new NullLogger());
    }

    public function testDecodesValidToken(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'email' => 'user@test.com',
            'exp' => time() + 3600,
        ]);

        $payload = $this->decoder->decode($token);

        $this->assertNotNull($payload);
        $this->assertSame('user-123', $payload['sub']);
        $this->assertSame('user@test.com', $payload['email']);
    }

    public function testRejectsExpiredToken(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'email' => 'user@test.com',
            'exp' => time() - 3600,
        ]);

        $this->assertNull($this->decoder->decode($token));
    }

    public function testRejectsInvalidSignature(): void
    {
        $token = $this->createToken(
            ['sub' => 'user-123', 'email' => 'user@test.com', 'exp' => time() + 3600],
            'wrong-secret',
        );

        $this->assertNull($this->decoder->decode($token));
    }

    public function testRejectsTokenWithMissingClaims(): void
    {
        $token = $this->createToken(['sub' => 'user-123', 'exp' => time() + 3600]);

        $this->assertNull($this->decoder->decode($token));
    }

    public function testRejectsMalformedToken(): void
    {
        $this->assertNull($this->decoder->decode('not.a.valid.token'));
        $this->assertNull($this->decoder->decode('single-segment'));
    }

    private function createToken(array $payload, string $secret = self::SECRET): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$signature}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
