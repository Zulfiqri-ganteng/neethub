<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\OnlineUserModel;
use App\Models\PackageModel;
use Config\Services;

class DashboardController extends BaseApiController
{
    public function stats()
    {
        $tenantId = TenantHelper::requireTenantId();
        $cache    = Services::cache();
        $key      = "dashboard_stats_{$tenantId}";

        // 5-minute cache per the spec's caching rules. TTL-based only for
        // now — explicit invalidation on customer/invoice writes is a
        // Stage 13 (Optimization) concern, tracked in CLAUDE.md.
        if ($cached = $cache->get($key)) {
            return $this->ok($cached);
        }

        $customerModel = new CustomerModel();
        $invoiceModel  = new InvoiceModel();

        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $revenueThisMonth = $invoiceModel
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at >=', $monthStart . ' 00:00:00')
            ->where('paid_at <=', $monthEnd . ' 23:59:59')
            ->selectSum('total_amount')
            ->first();

        $stats = [
            'customers' => [
                'total'     => $customerModel->where('tenant_id', $tenantId)->countAllResults(),
                'active'    => $customerModel->where('tenant_id', $tenantId)->where('status', 'active')->countAllResults(),
                'suspended' => $customerModel->where('tenant_id', $tenantId)->where('status', 'suspended')->countAllResults(),
                'pending'   => $customerModel->where('tenant_id', $tenantId)->where('status', 'pending')->countAllResults(),
            ],
            'invoices' => [
                'unpaid'  => $invoiceModel->where('tenant_id', $tenantId)->where('status', 'unpaid')->countAllResults(),
                'overdue' => $invoiceModel->where('tenant_id', $tenantId)->where('status', 'overdue')->countAllResults(),
                'paid'    => $invoiceModel->where('tenant_id', $tenantId)->where('status', 'paid')->countAllResults(),
            ],
            'revenue_this_month' => (float) ($revenueThisMonth['total_amount'] ?? 0),
            'packages_active'    => (new PackageModel())->where('tenant_id', $tenantId)->where('is_active', 1)->countAllResults(),
            'customers_online'   => (new OnlineUserModel())->where('tenant_id', $tenantId)->countAllResults(),
        ];

        $cache->save($key, $stats, 300);

        return $this->ok($stats);
    }
}
