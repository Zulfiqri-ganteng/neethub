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

    /**
     * Last 6 months of revenue + new-customer counts, for the Dashboard's
     * trend charts — deliberately separate from ReportController's own
     * revenue/customers endpoints (those are filterable/exportable detail
     * views for the Laporan page; this is a small fixed-window summary
     * purpose-built for the Dashboard, same "own inline aggregate queries"
     * convention as stats() above rather than going through ReportService).
     */
    public function analytics()
    {
        $tenantId = TenantHelper::requireTenantId();
        $cache    = Services::cache();
        $key      = "dashboard_analytics_{$tenantId}";

        if ($cached = $cache->get($key)) {
            return $this->ok($cached);
        }

        $months = 6;
        $from   = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $revenueRows = (new InvoiceModel())
            ->select("DATE_FORMAT(paid_at, '%Y-%m') as period, SUM(total_amount) as total")
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at >=', $from . ' 00:00:00')
            ->groupBy('period')
            ->findAll();

        $customerRows = (new CustomerModel())
            ->select("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->where('tenant_id', $tenantId)
            ->where('created_at >=', $from . ' 00:00:00')
            ->groupBy('period')
            ->findAll();

        $data = [
            'revenue_trend'   => $this->fillMonths($months, array_column($revenueRows, 'total', 'period'), true),
            'customer_growth' => $this->fillMonths($months, array_column($customerRows, 'total', 'period'), false),
        ];

        $cache->save($key, $data, 300);

        return $this->ok($data);
    }

    /**
     * Fills every month in the trailing `$months`-month window with 0 where
     * no row exists, so the chart always renders a continuous timeline
     * instead of skipping months with no activity.
     *
     * @return array<int, array{period: string, total: float|int}>
     */
    private function fillMonths(int $months, array $byPeriod, bool $asFloat): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period  = date('Y-m', strtotime("-{$i} months"));
            $value   = $byPeriod[$period] ?? 0;
            $result[] = ['period' => $period, 'total' => $asFloat ? (float) $value : (int) $value];
        }

        return $result;
    }
}
