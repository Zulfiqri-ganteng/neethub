<?php

namespace App\PaymentGateways;

use Config\Services;
use RuntimeException;

/**
 * Xendit Invoices API (https://developers.xendit.co/api-reference/#invoices).
 *
 * NOTE: same caveat as MidtransGateway — `createTransaction`/`checkStatus`
 * implemented from documented API shape, not verified against a live
 * sandbox account. `handleWebhook`'s verification (a plain shared-token
 * comparison, simpler than Midtrans's HMAC) was exercised directly in
 * testing.
 */
class XenditGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://api.xendit.co';

    public function createTransaction(array $invoice, array $tenant, array $config): array
    {
        $client   = Services::curlrequest();
        $response = $client->post(self::API_BASE . '/v2/invoices', [
            'auth'        => [$config['secret_key'], '', 'basic'],
            'headers'     => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'json'        => [
                'external_id'  => $invoice['gateway_order_id'],
                'amount'       => (int) round((float) $invoice['total_amount']),
                'payer_email'  => $invoice['customer_email'] ?? null,
                'description'  => "Tagihan {$invoice['invoice_number']} - {$tenant['name']}",
            ],
            'http_errors' => false,
        ]);

        $body = json_decode($response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException('Xendit gagal membuat invoice: ' . ($body['message'] ?? $response->getBody()));
        }

        return [
            'gateway_reference' => $invoice['gateway_order_id'],
            'checkout_url'      => $body['invoice_url'] ?? null,
            'raw'               => $body,
        ];
    }

    public function handleWebhook(array $payload, array $headers, array $config): array
    {
        $token = $headers['x-callback-token'] ?? '';

        if (! hash_equals((string) $config['callback_token'], (string) $token)) {
            throw new RuntimeException('Callback token Xendit tidak valid.');
        }

        return [
            'gateway_reference' => (string) ($payload['external_id'] ?? ''),
            'status'            => $this->mapStatus($payload['status'] ?? ''),
            'raw'               => $payload,
        ];
    }

    public function checkStatus(string $gatewayReference, array $config): array
    {
        $client   = Services::curlrequest();
        $response = $client->get(self::API_BASE . '/v2/invoices', [
            'auth'        => [$config['secret_key'], '', 'basic'],
            'headers'     => ['Accept' => 'application/json'],
            'query'       => ['external_id' => $gatewayReference],
            'http_errors' => false,
        ]);

        $body = json_decode($response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException('Xendit gagal mengambil status: ' . ($body['message'] ?? $response->getBody()));
        }

        $invoice = $body[0] ?? null;

        if ($invoice === null) {
            throw new RuntimeException("Invoice Xendit dengan external_id '{$gatewayReference}' tidak ditemukan.");
        }

        return [
            'status' => $this->mapStatus($invoice['status'] ?? ''),
            'raw'    => $invoice,
        ];
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => 'paid',
            'PENDING' => 'pending',
            default => 'failed', // EXPIRED, etc.
        };
    }
}
