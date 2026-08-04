<?php

namespace App\Helpers;

use Config\JWT as JWTConfig;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

/**
 * JWT issuing/verification, password hashing, and the resolved auth context
 * for the current request (populated by JwtAuthFilter, read by controllers/
 * models/repositories via TenantHelper without needing to thread it through
 * every method signature).
 */
class AuthHelper
{
    private static ?array $context = null;

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * @param array{userId:int,tenantId:?int,roleSlug:string,email:string} $claims
     */
    public static function generateAccessToken(array $claims): string
    {
        $config = new JWTConfig();
        $now    = time();

        $payload = [
            'iss'       => $config->issuer,
            'iat'       => $now,
            'exp'       => $now + $config->accessExpiry,
            'type'      => 'access',
            'sub'       => $claims['userId'],
            'tenant_id' => $claims['tenantId'],
            'role'      => $claims['roleSlug'],
            'email'     => $claims['email'],
        ];

        return JWT::encode($payload, $config->secretKey, $config->algo);
    }

    public static function generateRefreshToken(int $userId): string
    {
        $config = new JWTConfig();
        $now    = time();

        $payload = [
            'iss'  => $config->issuer,
            'iat'  => $now,
            'exp'  => $now + $config->refreshExpiry,
            'type' => 'refresh',
            'sub'  => $userId,
            'jti'  => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $config->secretKey, $config->algo);
    }

    /**
     * @return array|null Decoded claims as array, or null if invalid/expired.
     */
    public static function decodeToken(string $token): ?array
    {
        $config = new JWTConfig();

        try {
            $decoded = JWT::decode($token, new Key($config->secretKey, $config->algo));

            return (array) $decoded;
        } catch (ExpiredException|SignatureInvalidException|UnexpectedValueException) {
            return null;
        }
    }

    public static function accessExpirySeconds(): int
    {
        return (new JWTConfig())->accessExpiry;
    }

    public static function refreshExpirySeconds(): int
    {
        return (new JWTConfig())->refreshExpiry;
    }

    /**
     * @param array{userId:int,tenantId:?int,roleSlug:string,email:string} $ctx
     */
    public static function setContext(array $ctx): void
    {
        self::$context = $ctx;
    }

    public static function check(): bool
    {
        return self::$context !== null;
    }

    public static function id(): ?int
    {
        return self::$context['userId'] ?? null;
    }

    public static function tenantId(): ?int
    {
        return self::$context['tenantId'] ?? null;
    }

    public static function roleId(): ?int
    {
        return self::$context['roleId'] ?? null;
    }

    public static function role(): ?string
    {
        return self::$context['roleSlug'] ?? null;
    }

    public static function email(): ?string
    {
        return self::$context['email'] ?? null;
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === 'super_admin';
    }
}
