<?php

namespace App\Controllers\Api\SuperAdmin;

use App\Controllers\Api\BaseApiController;
use App\Models\ActivityModel;
use App\Models\PlatformInvoiceModel;
use App\Models\TenantModel;

/**
 * Cross-tenant — deliberately NOT tenant-scoped (this is the one place in
 * the app that's allowed to see every tenant at once). Route group is
 * `jwtauth:super_admin` only, no `tenant` filter (see TenantFilter — it
 * already no-ops for super_admin, this group just doesn't need it at all).
 */
class TenantController extends BaseApiController
{
    public function index()
    {
        [$page, $perPage] = $this->paginationParams();
        $tenantModel = new TenantModel();

        $total = $tenantModel->countAllResults(false);
        $items = $tenantModel->orderBy('id', 'DESC')->findAll($perPage, ($page - 1) * $perPage);

        $invoiceModel = new PlatformInvoiceModel();
        foreach ($items as &$tenant) {
            $tenant['latest_platform_invoice'] = $invoiceModel->latestForTenant((int) $tenant['id']);
        }
        unset($tenant);

        return $this->paginated(['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function show($id = null)
    {
        $tenant = (new TenantModel())->find((int) $id);
        if (! $tenant) {
            return $this->notFound('Tenant tidak ditemukan.');
        }

        $tenant['platform_invoices'] = (new PlatformInvoiceModel())->forTenant((int) $id);

        return $this->ok($tenant);
    }

    /**
     * Manual suspend — distinct from the automatic unpaid_platform_invoice
     * suspension (Stage 13's PlatformBillingService owns that one). Doesn't
     * touch `suspended_reason` beyond what's passed, so this is also how a
     * Super Admin overrides/annotates why a tenant is blocked for anything
     * other than billing (abuse, fraud, etc).
     */
    public function suspend($id = null)
    {
        $tenantModel = new TenantModel();
        $tenant      = $tenantModel->find((int) $id);
        if (! $tenant) {
            return $this->notFound('Tenant tidak ditemukan.');
        }

        $reason = trim((string) ($this->request->getJSON(true)['reason'] ?? $this->request->getPost('reason') ?? ''));

        $tenantModel->update((int) $id, [
            'status'           => 'suspended',
            'suspended_reason' => $reason !== '' ? $reason : 'manual',
        ]);

        (new ActivityModel())->log('tenant_manually_suspended', (int) $id, null, "Tenant {$tenant['name']} disuspend manual oleh Super Admin" . ($reason !== '' ? ": {$reason}" : ''));

        return $this->ok($tenantModel->find((int) $id), 'Tenant berhasil disuspend.');
    }

    public function reactivate($id = null)
    {
        $tenantModel = new TenantModel();
        $tenant      = $tenantModel->find((int) $id);
        if (! $tenant) {
            return $this->notFound('Tenant tidak ditemukan.');
        }

        $tenantModel->update((int) $id, ['status' => 'active', 'suspended_reason' => null]);

        (new ActivityModel())->log('tenant_manually_reactivated', (int) $id, null, "Tenant {$tenant['name']} diaktifkan kembali oleh Super Admin");

        return $this->ok($tenantModel->find((int) $id), 'Tenant berhasil diaktifkan kembali.');
    }

    /**
     * Lets a Super Admin set exactly when a tenant's trial ends — e.g. a
     * manually-negotiated deal outside the normal recurring platform
     * billing (a tenant who only wants to pay once, a longer trial as a
     * favor, etc). Body: { "trial_ends_at": "YYYY-MM-DD" }.
     *
     * Accepts any date, past or future — a past date effectively ends the
     * trial immediately (the next `platform:billing` run will bill them
     * for real), a future date (however far — a year, several years) keeps
     * the tenant exempt from billing until then. Same `trial_ends_at`
     * field, same comparison, `PlatformBillingService::generateForTenant()`
     * already reads — no separate "exempt" flag needed.
     */
    public function extendTrial($id = null)
    {
        $tenantModel = new TenantModel();
        $tenant      = $tenantModel->find((int) $id);
        if (! $tenant) {
            return $this->notFound('Tenant tidak ditemukan.');
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $date = trim((string) ($data['trial_ends_at'] ?? ''));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            return $this->validationError(['trial_ends_at' => 'Tanggal wajib diisi dengan format YYYY-MM-DD.']);
        }

        $tenantModel->update((int) $id, ['trial_ends_at' => $date . ' 23:59:59']);

        (new ActivityModel())->log(
            'tenant_trial_updated',
            (int) $id,
            null,
            "Masa trial tenant {$tenant['name']} diatur Super Admin sampai {$date}"
        );

        return $this->ok($tenantModel->find((int) $id), 'Masa trial berhasil diperbarui.');
    }
}
