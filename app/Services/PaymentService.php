<?php

namespace App\Services;

use App\Helpers\AuthHelper;
use App\Helpers\TenantHelper;
use App\Models\ActivityModel;
use App\Models\InvoiceModel;
use App\Models\NotificationModel;
use App\Models\PaymentModel;
use App\Models\TenantModel;
use App\PaymentGateways\PaymentGatewayFactory;
use App\Repositories\PaymentRepository;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private PaymentRepository $payments = new PaymentRepository(),
        private QrisService $qris = new QrisService()
    ) {
    }

    /**
     * @return array{payload: string, image_data_uri: string}
     */
    public function generateQrisForInvoice(array $invoice): array
    {
        if ($invoice['status'] === 'paid') {
            throw new RuntimeException('Tagihan ini sudah lunas.');
        }

        $tenant = (new TenantModel())->find($invoice['tenant_id']);

        if (empty($tenant['qris_static_code'])) {
            throw new RuntimeException('Tenant belum mengatur kode QRIS statis di Pengaturan.');
        }

        $payload = $this->qris->generateDynamicPayload($tenant['qris_static_code'], (float) $invoice['total_amount']);

        $qrCode = new QrCode($payload);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return [
            'payload'        => $payload,
            'image_data_uri' => $result->getDataUri(),
        ];
    }

    /**
     * Records a customer's payment claim (QRIS "I've paid" or a bank
     * transfer with proof) as `pending`. Confirmation is always manual for
     * these two methods — unlike `initiateGatewayPayment`, which confirms
     * automatically via webhook.
     */
    public function submitPayment(array $customer, array $invoice, string $method, ?string $proofPath): array
    {
        if ($invoice['status'] === 'paid') {
            throw new RuntimeException('Tagihan ini sudah lunas.');
        }

        if ((int) $invoice['customer_id'] !== (int) $customer['id']) {
            throw new RuntimeException('Tagihan tidak ditemukan.');
        }

        if ($method === 'transfer' && ! $proofPath) {
            throw new RuntimeException('Bukti transfer wajib diunggah.');
        }

        $paymentId = $this->payments->create([
            'invoice_id'     => $invoice['id'],
            'customer_id'    => $customer['id'],
            'payment_method' => $method,
            'amount'         => $invoice['total_amount'],
            'proof_image'    => $proofPath,
            'status'         => 'pending',
        ]);

        (new ActivityModel())->log(
            'payment_submitted',
            TenantHelper::id(),
            null,
            "Pembayaran diajukan oleh {$customer['name']} untuk tagihan {$invoice['invoice_number']}"
        );

        return $this->payments->find($paymentId);
    }

    /**
     * Starts a gateway (Midtrans/Xendit) transaction — creates a `pending`
     * payment row first (so its ID can seed a unique gateway order/external
     * ID for this specific attempt, letting a customer retry a failed
     * attempt on the same invoice without an ID collision), then asks the
     * tenant's configured gateway to create the actual transaction.
     *
     * @return array{payment: array, checkout_url: ?string}
     */
    public function initiateGatewayPayment(array $customer, array $invoice, array $tenant): array
    {
        if ($invoice['status'] === 'paid') {
            throw new RuntimeException('Tagihan ini sudah lunas.');
        }

        if ((int) $invoice['customer_id'] !== (int) $customer['id']) {
            throw new RuntimeException('Tagihan tidak ditemukan.');
        }

        [$gateway, $config] = PaymentGatewayFactory::make($tenant);

        $paymentId = $this->payments->create([
            'invoice_id'     => $invoice['id'],
            'customer_id'    => $customer['id'],
            'payment_method' => 'gateway',
            'amount'         => $invoice['total_amount'],
            'status'         => 'pending',
        ]);

        $orderId = "{$invoice['invoice_number']}-{$paymentId}";

        try {
            $result = $gateway->createTransaction([
                ...$invoice,
                'gateway_order_id' => $orderId,
                'customer_name'    => $customer['name'],
                'customer_email'   => $customer['email'] ?? null,
            ], $tenant, $config);
        } catch (RuntimeException $e) {
            // Don't leave an orphaned `pending` row with no
            // gateway_reference — payments:reconcile would pick it up
            // forever with nothing to check against.
            $this->payments->update($paymentId, ['status' => 'rejected', 'notes' => $e->getMessage()]);

            throw $e;
        }

        $this->payments->update($paymentId, [
            'gateway_reference' => $result['gateway_reference'],
            'gateway_payload'   => json_encode($result['raw']),
        ]);

        (new ActivityModel())->log(
            'payment_gateway_initiated',
            TenantHelper::id(),
            null,
            "Transaksi gateway dimulai untuk tagihan {$invoice['invoice_number']} ({$tenant['payment_gateway']})"
        );

        return ['payment' => $this->payments->find($paymentId), 'checkout_url' => $result['checkout_url']];
    }

    /**
     * Called by WebhookController once the gateway's signature/token has
     * already been verified. Idempotent — a gateway may redeliver the same
     * webhook, so a payment that's already settled is a no-op, not an
     * error, and a `pending` status from the gateway (e.g. a VA just being
     * created) doesn't finalize anything yet.
     *
     * @return array{payment: array, invoice: ?array}|null null if nothing changed
     */
    public function confirmViaGateway(string $gatewayReference, string $gatewayStatus, array $rawPayload): ?array
    {
        $paymentModel = new PaymentModel();
        $payment      = $paymentModel->where('gateway_reference', $gatewayReference)->first();

        if (! $payment) {
            throw new RuntimeException("Pembayaran dengan referensi '{$gatewayReference}' tidak ditemukan.");
        }

        if ($payment['status'] !== 'pending' || $gatewayStatus === 'pending') {
            return null;
        }

        // Background/webhook context has no JWT-derived tenant — set it
        // from the payment row itself, same pattern as the router:sync and
        // invoices:generate commands.
        AuthHelper::setContext([
            'userId' => null, 'tenantId' => (int) $payment['tenant_id'], 'roleId' => null,
            'roleSlug' => 'system', 'email' => 'system@nethub',
        ]);

        $paymentModel->update($payment['id'], ['gateway_payload' => json_encode($rawPayload)]);

        return $this->finalizePayment($paymentModel->find($payment['id']), $gatewayStatus === 'paid', null, 'Dikonfirmasi otomatis oleh payment gateway.');
    }

    /**
     * Staff-side manual payment entry — the owner/admin/kasir personally
     * collected cash (or an out-of-band transfer never submitted through
     * the customer portal) and wants the invoice marked paid immediately,
     * with no customer submission involved at all. Reuses finalizePayment()
     * so it gets the same activity log + WhatsApp payment-confirmation
     * notification a normal verified payment would.
     *
     * @return array{payment: array, invoice: array}
     */
    public function recordManual(array $invoice, string $method, ?string $notes, int $recordedByUserId): array
    {
        if ($invoice['status'] === 'paid') {
            throw new RuntimeException('Tagihan ini sudah lunas.');
        }

        if (! in_array($method, ['cash', 'transfer'], true)) {
            throw new RuntimeException('Metode pembayaran manual tidak valid.');
        }

        $paymentId = $this->payments->create([
            'invoice_id'     => $invoice['id'],
            'customer_id'    => $invoice['customer_id'],
            'payment_method' => $method,
            'amount'         => $invoice['total_amount'],
            'status'         => 'pending',
        ]);

        (new ActivityModel())->log(
            'payment_recorded_manual',
            TenantHelper::id(),
            $recordedByUserId,
            "Pembayaran manual dicatat untuk tagihan {$invoice['invoice_number']}"
        );

        return $this->finalizePayment($this->payments->find($paymentId), true, $recordedByUserId, $notes);
    }

    /**
     * @return array{payment: array, invoice: array}
     */
    public function verify(int $paymentId, int $verifiedByUserId, bool $approve, ?string $notes): array
    {
        $payment = $this->payments->find($paymentId);
        if (! $payment) {
            throw new RuntimeException('Pembayaran tidak ditemukan.');
        }

        if ($payment['status'] !== 'pending') {
            throw new RuntimeException('Pembayaran ini sudah diverifikasi sebelumnya.');
        }

        return $this->finalizePayment($payment, $approve, $verifiedByUserId, $notes);
    }

    /**
     * @return array{payment: array, invoice: array}
     */
    private function finalizePayment(array $payment, bool $approve, ?int $verifiedByUserId, ?string $notes): array
    {
        $paymentId = (int) $payment['id'];
        $now       = date('Y-m-d H:i:s');

        $this->payments->update($paymentId, [
            'status'      => $approve ? 'verified' : 'rejected',
            'verified_by' => $verifiedByUserId,
            'verified_at' => $now,
            'paid_at'     => $approve ? $now : null,
            'notes'       => $notes,
        ]);

        $invoiceModel = new InvoiceModel();

        if ($approve) {
            $invoiceModel->update($payment['invoice_id'], ['status' => 'paid', 'paid_at' => $now]);
        }

        $invoice = $invoiceModel->find($payment['invoice_id']);

        (new ActivityModel())->log(
            $approve ? 'payment_verified' : 'payment_rejected',
            TenantHelper::id(),
            $verifiedByUserId,
            "Pembayaran #{$paymentId} " . ($approve ? 'diverifikasi' : 'ditolak') . ($verifiedByUserId === null ? ' (otomatis via gateway)' : '')
        );

        (new NotificationModel())->queue(
            'whatsapp',
            'payment_confirmation',
            'customer',
            (int) $payment['customer_id'],
            [
                'payment_id'     => $paymentId,
                'invoice_number' => $invoice['invoice_number'] ?? null,
                'approved'       => $approve,
                'notes'          => $notes,
            ],
            TenantHelper::id()
        );

        return ['payment' => $this->payments->find($paymentId), 'invoice' => $invoice];
    }
}
