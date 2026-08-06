<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class JWT extends BaseConfig
{
    public string $secretKey;
    public string $algo = 'HS256';
    public int $accessExpiry;
    public int $refreshExpiry;
    public string $issuer = 'nethub';

    public function __construct()
    {
        parent::__construct();

        $this->secretKey     = (string) getenv('JWT_SECRET');
        $this->accessExpiry  = (int) (getenv('JWT_ACCESS_EXPIRY') ?: 900);
        // 24 hours, not 7 days (2026-08-06 — a stolen/leaked refresh token
        // was valid for up to 7 days since rotation only kills it once
        // EITHER side uses it; shortening this narrows that exposure
        // window without forcing daily re-logins, which is still the
        // dominant real-world usage pattern for this app).
        $this->refreshExpiry = (int) (getenv('JWT_REFRESH_EXPIRY') ?: 86400);
    }
}
