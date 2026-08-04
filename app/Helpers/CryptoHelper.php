<?php

namespace App\Helpers;

use Config\Services;

/**
 * At-rest encryption for sensitive-but-needed-in-plaintext-for-use secrets
 * (router admin passwords, customer PPPoE passwords) — unlike user login
 * passwords, these must be reversible since the app needs the real value to
 * authenticate to a router. Uses CI4's Encryption service, keyed by
 * `encryption.key` in .env.
 */
class CryptoHelper
{
    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }

        return base64_encode(Services::encrypter()->encrypt($plain));
    }

    /**
     * Returns null (rather than throwing) on a corrupt/legacy/unencrypted
     * value, so callers can treat "can't use this credential" as a normal,
     * handleable outcome instead of a crash.
     */
    public static function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return $cipher;
        }

        try {
            return Services::encrypter()->decrypt(base64_decode($cipher, true) ?: '');
        } catch (\Throwable) {
            return null;
        }
    }
}
