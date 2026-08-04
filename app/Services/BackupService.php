<?php

namespace App\Services;

use App\Helpers\TenantHelper;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\PackageModel;
use App\Models\TenantModel;

/**
 * Tenant-scoped data backup/restore. Deliberately narrow scope: only
 * packages, customers, and invoices are included — never payments (real
 * financial transactions, too risky to blindly reinsert) or any encrypted
 * credential (PPPoE/router passwords, payment gateway secrets, WhatsApp
 * token), which must be re-entered by hand on a genuinely fresh install.
 *
 * Restore is merge-only: existing rows (matched by their natural business
 * key — customer_code, invoice_number, package name) are never overwritten
 * or deleted, only missing ones get inserted. This is the one rule that
 * must never be relaxed — a destructive restore could wipe a tenant's live
 * data, and this always runs against the CURRENTLY authenticated tenant
 * (never whatever tenant the uploaded file happens to reference), so a
 * restore can never cross into another tenant's data either.
 */
class BackupService
{
    public function export(): array
    {
        $tenantId = TenantHelper::requireTenantId();
        $tenant   = (new TenantModel())->find($tenantId);

        $packages     = (new PackageModel())->where('tenant_id', $tenantId)->findAll();
        $packagesById = array_column($packages, null, 'id');

        $customers     = (new CustomerModel())->where('tenant_id', $tenantId)->findAll();
        $customersById = array_column($customers, null, 'id');

        $invoices = (new InvoiceModel())->where('tenant_id', $tenantId)->findAll();

        return [
            'version'     => 1,
            'exported_at' => date('c'),
            'tenant_name' => $tenant['name'] ?? null,
            'packages'    => array_map(static fn ($p) => [
                'name'              => $p['name'],
                'description'       => $p['description'],
                'speed_download'    => $p['speed_download'],
                'speed_upload'      => $p['speed_upload'],
                'price'             => $p['price'],
                'billing_cycle'     => $p['billing_cycle'],
                'custom_cycle_days' => $p['custom_cycle_days'],
                'fup_quota_gb'      => $p['fup_quota_gb'],
                'is_active'         => $p['is_active'],
            ], $packages),
            'customers' => array_map(static fn ($c) => [
                'customer_code'     => $c['customer_code'],
                'name'              => $c['name'],
                'email'             => $c['email'],
                'phone'             => $c['phone'],
                'whatsapp'          => $c['whatsapp'],
                'address'           => $c['address'],
                'status'            => $c['status'],
                'installation_date' => $c['installation_date'],
                'notes'             => $c['notes'],
                'package_name'      => $c['package_id'] ? ($packagesById[$c['package_id']]['name'] ?? null) : null,
            ], $customers),
            'invoices' => array_map(static fn ($i) => [
                'invoice_number' => $i['invoice_number'],
                'customer_code'  => $customersById[$i['customer_id']]['customer_code'] ?? null,
                'package_name'   => $i['package_id'] ? ($packagesById[$i['package_id']]['name'] ?? null) : null,
                'period_start'   => $i['period_start'],
                'period_end'     => $i['period_end'],
                'amount'         => $i['amount'],
                'discount'       => $i['discount'],
                'tax'            => $i['tax'],
                'total_amount'   => $i['total_amount'],
                'status'         => $i['status'],
                'due_date'       => $i['due_date'],
                'paid_at'        => $i['paid_at'],
                'notes'          => $i['notes'],
            ], $invoices),
        ];
    }

    /**
     * @return array{packages: array, customers: array, invoices: array} each {created: int, skipped: int}
     */
    public function restore(array $data): array
    {
        $tenantId = TenantHelper::requireTenantId();

        $packageModel  = new PackageModel();
        $customerModel = new CustomerModel();
        $invoiceModel  = new InvoiceModel();

        // Seeded from what already exists in THIS tenant, so an existing
        // row is matched (and skipped) instead of re-created.
        $packageIdByName = array_column($packageModel->where('tenant_id', $tenantId)->findAll(), 'id', 'name');
        $customerIdByCode = array_column($customerModel->where('tenant_id', $tenantId)->findAll(), 'id', 'customer_code');
        $existingInvoiceNumbers = array_flip(array_column(
            $invoiceModel->where('tenant_id', $tenantId)->findAll(),
            'invoice_number'
        ));

        $result = [
            'packages'  => ['created' => 0, 'skipped' => 0],
            'customers' => ['created' => 0, 'skipped' => 0],
            'invoices'  => ['created' => 0, 'skipped' => 0],
        ];

        foreach ($data['packages'] ?? [] as $p) {
            if (isset($packageIdByName[$p['name']])) {
                $result['packages']['skipped']++;
                continue;
            }

            $packageIdByName[$p['name']] = $packageModel->insert([
                'tenant_id'         => $tenantId,
                'name'              => $p['name'],
                'description'       => $p['description'] ?? null,
                'speed_download'    => $p['speed_download'],
                'speed_upload'      => $p['speed_upload'],
                'price'             => $p['price'],
                'billing_cycle'     => $p['billing_cycle'],
                'custom_cycle_days' => $p['custom_cycle_days'] ?? null,
                'fup_quota_gb'      => $p['fup_quota_gb'] ?? null,
                'is_active'         => $p['is_active'] ?? 1,
            ]);
            $result['packages']['created']++;
        }

        foreach ($data['customers'] ?? [] as $c) {
            if (isset($customerIdByCode[$c['customer_code']])) {
                $result['customers']['skipped']++;
                continue;
            }

            $customerIdByCode[$c['customer_code']] = $customerModel->insert([
                'tenant_id'         => $tenantId,
                'customer_code'     => $c['customer_code'],
                'name'              => $c['name'],
                'email'             => $c['email'] ?? null,
                'phone'             => $c['phone'] ?? null,
                'whatsapp'          => $c['whatsapp'] ?? null,
                'address'           => $c['address'] ?? null,
                'status'            => $c['status'] ?? 'pending',
                'installation_date' => $c['installation_date'] ?? null,
                'notes'             => $c['notes'] ?? null,
                'package_id'        => $packageIdByName[$c['package_name'] ?? ''] ?? null,
            ]);
            $result['customers']['created']++;
        }

        foreach ($data['invoices'] ?? [] as $i) {
            if (isset($existingInvoiceNumbers[$i['invoice_number']])) {
                $result['invoices']['skipped']++;
                continue;
            }

            $customerId = $customerIdByCode[$i['customer_code'] ?? ''] ?? null;
            if ($customerId === null) {
                // customer_id is a required column — an invoice whose
                // customer wasn't restored (or was never in the backup)
                // can't be safely inserted, and guessing a customer would
                // be worse than skipping it.
                $result['invoices']['skipped']++;
                continue;
            }

            $invoiceModel->insert([
                'tenant_id'      => $tenantId,
                'customer_id'    => $customerId,
                'package_id'     => $packageIdByName[$i['package_name'] ?? ''] ?? null,
                'invoice_number' => $i['invoice_number'],
                'period_start'   => $i['period_start'],
                'period_end'     => $i['period_end'],
                'amount'         => $i['amount'],
                'discount'       => $i['discount'] ?? 0,
                'tax'            => $i['tax'] ?? 0,
                'total_amount'   => $i['total_amount'],
                'status'         => $i['status'],
                'due_date'       => $i['due_date'],
                'paid_at'        => $i['paid_at'] ?? null,
                'notes'          => $i['notes'] ?? null,
            ]);
            $existingInvoiceNumbers[$i['invoice_number']] = true;
            $result['invoices']['created']++;
        }

        return $result;
    }
}
