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
        $this->refreshExpiry = (int) (getenv('JWT_REFRESH_EXPIRY') ?: 604800);
    }
}
