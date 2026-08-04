<?php

namespace App\PaymentGateways;

use App\Helpers\CryptoHelper;
use RuntimeException;

class PaymentGatewayFactory
{
    /**
     * @return array{0: PaymentGatewayInterface, 1: array} [gateway, decoded config]
     */
    public static function make(array $tenant): array
    {
        if (empty($tenant['payment_gateway'])) {
            throw new RuntimeException('Tenant belum mengatur payment gateway di Pengaturan.');
        }

        $configJson = CryptoHelper::decrypt($tenant['payment_gateway_config']);

        if ($configJson === null) {
            throw new RuntimeException('Konfigurasi payment gateway tidak valid atau rusak — atur ulang di Pengaturan.');
        }

        $config = json_decode($configJson, true);

        if (! is_array($config)) {
            throw new RuntimeException('Konfigurasi payment gateway tidak valid.');
        }

        $gateway = match ($tenant['payment_gateway']) {
            'midtrans' => new MidtransGateway(),
            'xendit'   => new XenditGateway(),
            default    => throw new RuntimeException("Gateway '{$tenant['payment_gateway']}' tidak didukung."),
        };

        return [$gateway, $config];
    }
}
