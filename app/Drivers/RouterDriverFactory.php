<?php

namespace App\Drivers;

use App\Helpers\CryptoHelper;
use RuntimeException;

/**
 * Resolves a `routers` row into the driver for its `type`. Adding a new
 * brand later: implement RouterDriverInterface, add one case here.
 */
class RouterDriverFactory
{
    /**
     * Router types with a real, working driver — used by
     * Owner\RouterController to reject creating a router of any other type
     * up front (a clear error at setup time) instead of letting an owner
     * configure something that only fails later, silently, deep inside
     * suspend/provisioning/monitoring. See that controller for the full
     * reasoning.
     *
     * @return string[]
     */
    public static function implementedTypes(): array
    {
        return ['mikrotik'];
    }

    public static function make(array $router): RouterDriverInterface
    {
        $password = CryptoHelper::decrypt($router['password']);

        if ($password === null) {
            throw new RuntimeException('Kredensial router tidak valid atau rusak — konfigurasikan ulang password router.');
        }

        return match ($router['type']) {
            'mikrotik' => new MikroTikDriver(
                $router['ip_address'],
                (int) $router['port'],
                $router['username'],
                $password,
                $router['api_scheme'] ?? 'api'
            ),
            'ubiquiti', 'omada', 'openwrt', 'huawei', 'cisco', 'ruijie' => throw new RuntimeException(
                "Driver untuk router '{$router['type']}' belum diimplementasikan."
            ),
            default => throw new RuntimeException("Jenis router '{$router['type']}' tidak dikenal."),
        };
    }
}
