<?php

namespace App\Services;

use App\Helpers\TenantHelper;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;

/**
 * Read-only aggregate queries for the owner-facing Laporan (Reports) page.
 * Not a Repository since these aren't CRUD-scoped resources — same style as
 * DashboardController's inline aggregate queries, just tenant_id-filtered
 * directly against the models rather than through BaseRepository.
 */
class ReportService
{
    /**
     * @return array{rows: array, total_amount: float, total_count: int}
     */
    public function revenue(string $from, string $to, string $group): array
    {
        $tenantId = TenantHelper::requireTenantId();
        $dateExpr = $group === 'monthly' ? "DATE_FORMAT(paid_at, '%Y-%m')" : 'DATE(paid_at)';

        $rows = (new InvoiceModel())
            ->select("{$dateExpr} as period, SUM(total_amount) as total, COUNT(*) as count")
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at >=', $from . ' 00:00:00')
            ->where('paid_at <=', $to . ' 23:59:59')
            ->groupBy('period')
            ->orderBy('period', 'ASC')
            ->findAll();

        $totalAmount = 0.0;
        $totalCount  = 0;
        foreach ($rows as $row) {
            $totalAmount += (float) $row['total'];
            $totalCount  += (int) $row['count'];
        }

        return ['rows' => $rows, 'total_amount' => $totalAmount, 'total_count' => $totalCount];
    }

    /**
     * @return array{summary: array, by_package: array, items: array}
     */
    public function customers(): array
    {
        $tenantId      = TenantHelper::requireTenantId();
        $customerModel = new CustomerModel();

        $summary = [
            'total'     => $customerModel->where('tenant_id', $tenantId)->countAllResults(),
            'active'    => $customerModel->where('tenant_id', $tenantId)->where('status', 'active')->countAllResults(),
            'pending'   => $customerModel->where('tenant_id', $tenantId)->where('status', 'pending')->countAllResults(),
            'suspended' => $customerModel->where('tenant_id', $tenantId)->where('status', 'suspended')->countAllResults(),
            'disabled'  => $customerModel->where('tenant_id', $tenantId)->where('status', 'disabled')->countAllResults(),
        ];

        $byPackage = (new CustomerModel())
            ->select('packages.name as package_name, COUNT(customers.id) as count')
            ->join('packages', 'packages.id = customers.package_id', 'left')
            ->where('customers.tenant_id', $tenantId)
            ->groupBy('customers.package_id')
            ->findAll();

        $items = (new CustomerModel())
            ->select('customers.customer_code, customers.name, customers.phone, customers.status, customers.installation_date, packages.name as package_name')
            ->join('packages', 'packages.id = customers.package_id', 'left')
            ->where('customers.tenant_id', $tenantId)
            ->orderBy('customers.id', 'ASC')
            ->findAll();

        return ['summary' => $summary, 'by_package' => $byPackage, 'items' => $items];
    }

    /**
     * @param array{method?: string, status?: string} $filters
     * @return array{rows: array, summary: array}
     */
    public function payments(string $from, string $to, array $filters = []): array
    {
        $tenantId = TenantHelper::requireTenantId();

        $builder = (new PaymentModel())
            ->select('payments.*, customers.name as customer_name, invoices.invoice_number')
            ->join('customers', 'customers.id = payments.customer_id', 'left')
            ->join('invoices', 'invoices.id = payments.invoice_id', 'left')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.created_at >=', $from . ' 00:00:00')
            ->where('payments.created_at <=', $to . ' 23:59:59');

        if (! empty($filters['method'])) {
            $builder = $builder->where('payments.payment_method', $filters['method']);
        }

        if (! empty($filters['status'])) {
            $builder = $builder->where('payments.status', $filters['status']);
        }

        $rows = $builder->orderBy('payments.created_at', 'ASC')->findAll();

        $summary = ['total_amount' => 0.0, 'count' => count($rows), 'by_method' => [], 'by_status' => []];
        foreach ($rows as $row) {
            // Only settled (verified) payments count toward the revenue
            // total — pending/rejected are shown in the list but don't
            // count as real income.
            if ($row['status'] === 'verified') {
                $summary['total_amount'] += (float) $row['amount'];
            }
            $summary['by_method'][$row['payment_method']] = ($summary['by_method'][$row['payment_method']] ?? 0) + 1;
            $summary['by_status'][$row['status']]         = ($summary['by_status'][$row['status']] ?? 0) + 1;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }
}
