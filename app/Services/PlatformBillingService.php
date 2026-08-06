<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\CustomerModel;
use App\Models\PlatformInvoiceModel;
use App\Models\PlatformPaymentModel;
use App\Models\PlatformPricingTierModel;
use App\Models\PlatformSettingModel;
use App\Models\TenantModel;
use RuntimeException;

/**
 * Bills a *tenant* for using the platform itself (Stage 13) — separate
 * concern from InvoiceService/PaymentService, which bill a tenant's own
 * customers for internet service. See CLAUDE.md Stage 13 for the full
 * design/decisions.
 */
class PlatformBillingService
{
    public function __construct(
        private PlatformInvoiceModel $invoices = new PlatformInvoiceModel(),
        private PlatformPaymentModel $payments = new PlatformPaymentModel(),
        private PlatformSettingModel $settings = new PlatformSettingModel(),
        private TenantModel $tenants = new TenantModel(),
        private PlatformPricingTierModel $pricingTiers = new PlatformPricingTierModel(),
        private CustomerModel $customers = new CustomerModel()
    ) {
    }

    /**
     * Generates the next platform invoice for one tenant, if due. Safe to
     * call repeatedly (idempotent) — same contract as InvoiceService.
     *
     * Rules: trial tenants generate nothing until `trial_ends_at` has
     * passed. First invoice starts the day the trial ends. Each
     * subsequent invoice starts the day after the previous one's
     * `period_end`, and only gets generated once that date has arrived —
     * never generates invoices in advance.
     *
     * @return array|null the created invoice row, or null if nothing was due
     */
    public function generateForTenant(array $tenant): ?array
    {
        $today = date('Y-m-d');

        if (! empty($tenant['trial_ends_at']) && $tenant['trial_ends_at'] > $today) {
            return null; // still in trial
        }

        $settings = $this->settings->current();
        $last     = $this->invoices->latestForTenant((int) $tenant['id']);

        if ($last) {
            $nextStart = date('Y-m-d', strtotime($last['period_end'] . ' +1 day'));
        } else {
            // First invoice: starts the day the trial ended (or, for a
            // tenant with no trial_ends_at at all — e.g. seeded directly —
            // starts today).
            $nextStart = ! empty($tenant['trial_ends_at'])
                ? date('Y-m-d', strtotime($tenant['trial_ends_at']))
                : $today;
        }

        if ($nextStart > $today) {
            return null; // next period hasn't started yet
        }

        $periodEnd = date('Y-m-d', strtotime($nextStart . ' +1 month -1 day'));
        $dueDate   = date('Y-m-d', strtotime($nextStart . ' +7 days'));

        // Tiered pricing by total customer count (all statuses — a
        // suspended/pending customer still counts, per the user's own
        // decision), Super Admin-configurable. Falls back to the flat
        // platform_settings.monthly_fee if no tier matches (empty tier
        // table, or a gap between two tiers) so a misconfigured tier list
        // can never break billing outright.
        $customerCount = $this->customers->where('tenant_id', $tenant['id'])->countAllResults();
        $amount        = $this->pricingTiers->resolveFee($customerCount) ?? $settings['monthly_fee'];

        $invoiceId = $this->invoices->insert([
            'tenant_id'    => $tenant['id'],
            'period_start' => $nextStart,
            'period_end'   => $periodEnd,
            'amount'       => $amount,
            'status'       => 'unpaid',
            'due_date'     => $dueDate,
        ]);

        (new ActivityModel())->log(
            'platform_invoice_generated',
            (int) $tenant['id'],
            null,
            "Tagihan platform dibuat untuk tenant {$tenant['name']} ({$nextStart} s/d {$periodEnd})"
        );

        return $this->invoices->find($invoiceId);
    }

    /**
     * Marks unpaid/overdue invoices as `overdue` once past due_date, and
     * suspends any tenant whose oldest unpaid invoice is past due_date +
     * grace_days. Reactivation happens automatically in verifyPayment()
     * once the qualifying invoice is paid — never requires a separate
     * manual "un-suspend" step for a billing-caused suspension.
     *
     * @return array{marked_overdue: int, suspended: int}
     */
    public function enforceOverdue(): array
    {
        $settings = $this->settings->current();
        $today    = date('Y-m-d');

        $markedOverdue = $this->invoices
            ->where('status', 'unpaid')
            ->where('due_date <', $today)
            ->set(['status' => 'overdue'])
            ->update();

        $overdueInvoices = $this->invoices->overdueBeyondGrace((int) $settings['grace_days']);
        $suspended       = 0;

        foreach ($overdueInvoices as $invoice) {
            $tenant = $this->tenants->find($invoice['tenant_id']);
            if (! $tenant || $tenant['status'] !== 'active') {
                continue; // already suspended (billing or otherwise), or doesn't exist
            }

            $this->tenants->update($tenant['id'], [
                'status'           => 'suspended',
                'suspended_reason' => 'unpaid_platform_invoice',
            ]);

            (new ActivityModel())->log(
                'tenant_auto_suspended',
                (int) $tenant['id'],
                null,
                "Tenant {$tenant['name']} disuspend otomatis — tagihan platform #{$invoice['id']} lewat masa tenggang {$settings['grace_days']} hari"
            );

            $suspended++;
        }

        return ['marked_overdue' => is_int($markedOverdue) ? $markedOverdue : 0, 'suspended' => $suspended];
    }

    /**
     * Owner/staff submits a manual-transfer proof against their tenant's own
     * unpaid invoice. Mirrors PaymentService::submitPayment's contract —
     * always creates a `pending` row, never auto-confirms.
     */
    public function submitManualPayment(int $tenantId, int $invoiceId, string $proofPath): array
    {
        $invoice = $this->invoices->find($invoiceId);

        if (! $invoice || (int) $invoice['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Tagihan platform tidak ditemukan.');
        }

        if ($invoice['status'] === 'paid') {
            throw new RuntimeException('Tagihan ini sudah lunas.');
        }

        $paymentId = $this->payments->insert([
            'platform_invoice_id' => $invoiceId,
            'tenant_id'            => $tenantId,
            'payment_method'       => 'transfer',
            'amount'               => $invoice['amount'],
            'proof_image'          => $proofPath,
            'status'               => 'pending',
        ]);

        return $this->payments->find($paymentId);
    }

    /**
     * Super Admin verifies (or rejects) a pending platform payment. On
     * approval: marks the invoice paid, and reactivates the tenant if it
     * was suspended specifically for this unpaid invoice (never touches a
     * manual, non-billing suspension).
     *
     * @return array{payment: array, invoice: array}
     */
    public function verifyPayment(int $paymentId, int $verifiedByUserId, bool $approve, ?string $notes): array
    {
        $payment = $this->payments->find($paymentId);
        if (! $payment) {
            throw new RuntimeException('Pembayaran tidak ditemukan.');
        }

        if ($payment['status'] !== 'pending') {
            throw new RuntimeException('Pembayaran ini sudah diverifikasi sebelumnya.');
        }

        $now = date('Y-m-d H:i:s');

        $this->payments->update($paymentId, [
            'status'      => $approve ? 'verified' : 'rejected',
            'verified_by' => $verifiedByUserId,
            'verified_at' => $now,
            'paid_at'     => $approve ? $now : null,
            'notes'       => $notes,
        ]);

        $invoice = $this->invoices->find($payment['platform_invoice_id']);

        if ($approve) {
            $this->invoices->update($invoice['id'], ['status' => 'paid', 'paid_at' => $now]);

            $tenant = $this->tenants->find($payment['tenant_id']);
            if ($tenant && $tenant['status'] === 'suspended' && $tenant['suspended_reason'] === 'unpaid_platform_invoice') {
                $this->tenants->update($tenant['id'], ['status' => 'active', 'suspended_reason' => null]);
            }
        }

        (new ActivityModel())->log(
            $approve ? 'platform_payment_verified' : 'platform_payment_rejected',
            (int) $payment['tenant_id'],
            $verifiedByUserId,
            "Pembayaran platform #{$paymentId} " . ($approve ? 'diverifikasi' : 'ditolak') . ' oleh Super Admin'
        );

        return ['payment' => $this->payments->find($paymentId), 'invoice' => $this->invoices->find($invoice['id'])];
    }
}
