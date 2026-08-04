<?php

namespace App\PaymentGateways;

/**
 * Strategy pattern per gateway provider — same shape as
 * App\Drivers\RouterDriverInterface. Each tenant configures their OWN
 * merchant credentials (NetHub itself is not the merchant of record), same
 * as router credentials or a QRIS static code.
 */
interface PaymentGatewayInterface
{
    /**
     * @param array $invoice tenant-scoped invoice row, enriched by
     *                       PaymentService with `gateway_order_id` (unique
     *                       per payment attempt), `customer_name`,
     *                       `customer_email` before being passed in here
     * @param array $tenant  the tenant row (for gateway config + branding)
     * @param array $config  decrypted, decoded gateway-specific config
     *
     * @return array{gateway_reference: string, checkout_url: ?string, raw: array}
     */
    public function createTransaction(array $invoice, array $tenant, array $config): array;

    /**
     * @param array<string, string> $headers request headers, lowercase keys
     *
     * @return array{gateway_reference: string, status: string, raw: array} status: paid|pending|failed
     *
     * @throws \RuntimeException if the signature/token doesn't verify.
     */
    public function handleWebhook(array $payload, array $headers, array $config): array;

    /**
     * Direct status lookup — the "retry mechanism" the spec calls for, used
     * to reconcile a payment whose webhook may have been missed/delayed.
     *
     * @return array{status: string, raw: array} status: paid|pending|failed
     */
    public function checkStatus(string $gatewayReference, array $config): array;
}
