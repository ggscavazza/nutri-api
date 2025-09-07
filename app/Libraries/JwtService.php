<?php

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $alg = 'HS256';
    private string $issuer;

    public function __construct()
    {
        $this->issuer = rtrim((string) env('app.baseURL'), '/').'/';
    }

    private function getSecret(): string
    {
        $secret = env('app.jwtSecret');
        if ($secret) return $secret;

        // fallback: usar encryption.key (hex2bin:...)
        $enc = env('encryption.key');
        if (str_starts_with((string)$enc, 'hex2bin:')) {
            $hex = substr((string)$enc, 8);
            return hex2bin($hex) ?: 'fallback-secret';
        }
        return (string)$enc ?: 'fallback-secret';
    }

    public function generateAccessToken(array $user, int $ttlSeconds = 900): string
    {
        $now = time();
        $payload = [
            'iss'  => $this->issuer,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $ttlSeconds,
            'sub'  => (string) $user['id'],
            'role' => $user['role'],
            'usr'  => [
                'id'    => (int)$user['id'],
                'name'  => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
            ],
        ];
        return JWT::encode($payload, $this->getSecret(), $this->alg);
    }

    public function validateAccessToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->getSecret(), $this->alg));
        return (array) $decoded;
    }

    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(64)); // 128 chars
    }

    public function hashToken(string $token): string
    {
        // hash com pepper opcional
        $pepper = env('app.jwtPepper') ?? '';
        return hash('sha256', $token . $pepper);
    }
}
