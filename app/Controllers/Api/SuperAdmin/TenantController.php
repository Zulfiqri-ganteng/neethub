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
}
