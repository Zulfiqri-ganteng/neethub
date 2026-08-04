<?php

namespace App\Controllers\Api;

use App\Helpers\CryptoHelper;
use App\Models\PaymentModel;
use App\Models\TenantModel;
use App\PaymentGateways\MidtransGateway;
use App\PaymentGateways\PaymentGatewayInterface;
use App\PaymentGateways\XenditGateway;
use App\Services\PaymentService;
use RuntimeException;

/**
 * Public endpoints (no jwtauth/tenant filter — a gateway's server can't
 * carry our Bearer tokens). Trust is established per-request by verifying
 * the payload/header against the SPECIFIC tenant's own gateway credentials
 * (looked up via the payment the webhook claims to be about), not by any
 * shared secret of NetHub's own.
 */
class WebhookController extends BaseApiController
{
    public function midtrans()
    {
        return $this->process('midtrans', new MidtransGateway(), (string) ($this->request->getJSON(true)['order_id'] ?? ''));
    }

    public function xendit()
    {
        return $this->process('xendit', new XenditGateway(), (string) ($this->request->getJSON(true)['external_id'] ?? ''));
    }

    private function process(string $gatewaySlug, PaymentGatewayInterface $gateway, string $reference)
    {
        if ($reference === '') {
            return $this->fail('Referensi transaksi tidak ditemukan pada payload.', 400);
        }

        $payment = (new PaymentModel())->where('gateway_reference', $reference)->first();

        if (! $payment) {
            return $this->notFound("Pembayaran dengan referensi '{$reference}' tidak ditemukan.");
        }

        $tenant = (new TenantModel())->find($payment['tenant_id']);

        if (! $tenant || $tenant['payment_gateway'] !== $gatewaySlug) {
            return $this->fail('Tenant tidak menggunakan gateway ini.', 400);
        }

        $configJson = CryptoHelper::decrypt($tenant['payment_gateway_config']);
        $config     = $configJson !== null ? json_decode($configJson, true) : null;

        if (! is_array($config)) {
            return $this->fail('Konfigurasi gateway tenant tidak valid.', 400);
        }

        $payload = $this->request->getJSON(true) ?? [];
        $headers = ['x-callback-token' => $this->request->getHeaderLine('X-Callback-Token')];

        try {
            $result = $gateway->handleWebhook($payload, $headers, $config);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 401);
        }

        try {
            (new PaymentService())->confirmViaGateway($result['gateway_reference'], $result['status'], $result['raw']);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        // Gateways expect a plain 200 acknowledgement, not our usual envelope.
        return $this->response->setStatusCode(200)->setJSON(['received' => true]);
    }
}
