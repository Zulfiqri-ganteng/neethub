<?php

namespace App\Services;

use App\Helpers\TenantHelper;
use App\Models\ActivityModel;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Repositories\CustomerRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PackageRepository;
use RuntimeException;

/**
 * Turns "customer + package + billing_cycle" into the next invoice,
 * without ever double-billing an in-progress period. Pure business logic —
 * tenant scoping itself is enforced by the repositories it calls.
 */
class InvoiceService
{
    public function __construct(
        private CustomerRepository $customers = new CustomerRepository(),
        private PackageRepository $packages = new PackageRepository(),
        private InvoiceRepository $invoices = new InvoiceRepository(),
        private ActivityModel $activities = new ActivityModel()
    ) {
    }

    /**
     * @throws RuntimeException when the customer has no package, or the
     *                           current billing period isn't over yet.
     */
    public function generateForCustomer(int $customerId): array
    {
        $customer = $this->customers->find($customerId);
        if (! $customer) {
            throw new RuntimeException('Pelanggan tidak ditemukan.');
        }

        if (empty($customer['package_id'])) {
            throw new RuntimeException('Pelanggan belum memiliki paket. Assign paket terlebih dahulu.');
        }

        $package = $this->packages->find((int) $customer['package_id']);
        if (! $package) {
            throw new RuntimeException('Paket pelanggan tidak ditemukan.');
        }

        $lastInvoice = (new InvoiceModel())->findLastForCustomer($customerId);

        if ($lastInvoice && $lastInvoice['period_end'] >= date('Y-m-d')) {
            throw new RuntimeException('Periode tagihan saat ini belum berakhir.');
        }

        [$periodStart, $periodEnd, $dueDate] = $this->computePeriod($package, $lastInvoice, $customer['installation_date'] ?? null);

        $invoiceNumber = $this->invoices->nextInvoiceNumber();

        $invoiceId = $this->invoices->create([
            'customer_id'    => $customerId,
            'package_id'     => $package['id'],
            'invoice_number' => $invoiceNumber,
            'period_start'   => $periodStart,
            'period_end'     => $periodEnd,
            'amount'         => $package['price'],
            'discount'       => 0,
            'tax'            => 0,
            'total_amount'   => $package['price'],
            'status'         => 'unpaid',
            'due_date'       => $dueDate,
        ]);

        $this->activities->log(
            'invoice_generated',
            TenantHelper::id(),
            null,
            "Tagihan {$invoiceNumber} dibuat untuk {$customer['name']}"
        );

        return $this->invoices->find($invoiceId);
    }

    /**
     * Generates invoices for every active customer in the current tenant
     * whose billing period has ended. Errors per-customer are collected,
     * not thrown, so one bad record doesn't stop the whole batch.
     *
     * @return array{generated: array, skipped: array}
     */
    public function generateDueForTenant(): array
    {
        $generated = [];
        $skipped   = [];

        $customers = (new CustomerModel())
            ->where('tenant_id', TenantHelper::requireTenantId())
            ->where('status', 'active')
            ->findAll();

        foreach ($customers as $customer) {
            try {
                $generated[] = $this->generateForCustomer((int) $customer['id']);
            } catch (RuntimeException $e) {
                $skipped[] = ['customer_id' => $customer['id'], 'reason' => $e->getMessage()];
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    /**
     * @param ?string $installationDate the customer's own subscription start
     *                date (`customers.installation_date`) — used to anchor
     *                the very first invoice so migrated customers keep
     *                their real billing day (e.g. "always the 1st") instead
     *                of it shifting to whatever day they got added to
     *                NetHub. Ignored once a lastInvoice exists, since every
     *                invoice after the first is anchored to the previous
     *                one's period_end instead.
     *
     * @return array{0: string, 1: string, 2: string} [period_start, period_end, due_date]
     */
    private function computePeriod(array $package, ?array $lastInvoice, ?string $installationDate = null): array
    {
        $start = match (true) {
            $lastInvoice !== null    => date('Y-m-d', strtotime($lastInvoice['period_end'] . ' +1 day')),
            $installationDate !== null && $installationDate !== '' => $installationDate,
            default                  => date('Y-m-d'),
        };

        $end = match ($package['billing_cycle']) {
            'weekly'  => date('Y-m-d', strtotime($start . ' +7 days -1 day')),
            'monthly' => date('Y-m-d', strtotime($start . ' +1 month -1 day')),
            'yearly'  => date('Y-m-d', strtotime($start . ' +1 year -1 day')),
            'custom'  => date('Y-m-d', strtotime($start . ' +' . (int) $package['custom_cycle_days'] . ' days -1 day')),
        };

        // 3-day grace period before an invoice is considered overdue.
        $dueDate = date('Y-m-d', strtotime($start . ' +3 days'));

        return [$start, $end, $dueDate];
    }
}
