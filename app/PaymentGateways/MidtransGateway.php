<?php

namespace App\PaymentGateways;

use Config\Services;
use RuntimeException;

/**
 * Midtrans Snap API (https://docs.midtrans.com/docs/snap-overview).
 *
 * NOTE: `createTransaction`/`checkStatus` are implemented against Midtrans's
 * documented API from memory, not verified against a live sandbox account
 * in this environment (no real Midtrans credentials available here) — same
 * caveat as MikroTik/Fonnte elsewhere in this codebase. `handleWebhook`'s
 * signature check, however, is pure local computation (SHA512 over fields
 * from the payload + the tenant's own server key) and WAS independently
 * verified during testing by computing a matching signature by hand and
 * confirming this class accepts it.
 */
class MidtransGateway implements PaymentGatewayInterface
{
    public function createTransaction(array $invoice, array $tenant, array $config): array
    {
        $baseUrl = $this->isProduction($config)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $client   = Services::curlrequest();
        $response = $client->post($baseUrl, [
            'auth'        => [$config['server_key'], '', 'basic'],
            'headers'     => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'json'        => [
                'transaction_details' => [
                    'order_id'     => $invoice['gateway_order_id'],
                    'gross_amount' => (int) round((float) $invoice['total_amount']),
                ],
                'customer_details' => [
                    'first_name' => $invoice['customer_name'] ?? 'Pelanggan',
                    'email'      => $invoice['customer_email'] ?? null,
                ],
            ],
            'http_errors' => false,
        ]);

        $body = json_decode($response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException('Midtrans gagal membuat transaksi: ' . ($body['status_message'] ?? $response->getBody()));
        }

        return [
            'gateway_reference' => $invoice['gateway_order_id'],
            'checkout_url'      => $body['redirect_url'] ?? null,
            'raw'               => $body,
        ];
    }

    public function handleWebhook(array $payload, array $headers, array $config): array
    {
        $orderId     = (string) ($payload['order_id'] ?? '');
        $statusCode  = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature   = (string) ($payload['signature_key'] ?? '');

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $config['server_key']);

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature Midtrans tidak valid.');
        }

        return [
            'gateway_reference' => $orderId,
            'status'            => $this->mapStatus($payload['transaction_status'] ?? '', $payload['fraud_status'] ?? null),
            'raw'               => $payload,
        ];
    }

    public function checkStatus(string $gatewayReference, array $config): array
    {
        $baseUrl = $this->isProduction($config)
            ? "https://api.midtrans.com/v2/{$gatewayReference}/status"
            : "https://api.sandbox.midtrans.com/v2/{$gatewayReference}/status";

        $client   = Services::curlrequest();
        $response = $client->get($baseUrl, [
            'auth'        => [$config['server_key'], '', 'basic'],
            'headers'     => ['Accept' => 'application/json'],
            'http_errors' => false,
        ]);

        $body = json_decode($response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException('Midtrans gagal mengambil status: ' . ($body['status_message'] ?? $response->getBody()));
        }

        return [
            'status' => $this->mapStatus($body['transaction_status'] ?? '', $body['fraud_status'] ?? null),
            'raw'    => $body,
        ];
    }

    private function mapStatus(string $transactionStatus, ?string $fraudStatus): string
    {
        return match (true) {
            $transactionStatus === 'settlement' => 'paid',
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => 'paid',
            $transactionStatus === 'capture' => 'pending',
            $transactionStatus === 'pending' => 'pending',
            default => 'failed', // deny, cancel, expire, refund, etc.
        };
    }

    private function isProduction(array $config): bool
    {
        return (bool) ($config['is_production'] ?? false);
    }
}
