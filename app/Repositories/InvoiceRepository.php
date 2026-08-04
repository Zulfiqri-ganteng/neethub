<?php

namespace App\Repositories;

use App\Helpers\TenantHelper;
use App\Models\InvoiceModel;

class InvoiceRepository extends BaseRepository
{
    public function __construct(?InvoiceModel $model = null)
    {
        parent::__construct($model ?? new InvoiceModel(), 'invoices');
    }

    /**
     * @param array{status?: string, customer_id?: int} $filters
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->paginateScoped($filters, $page, $perPage);
    }

    protected function applyFilters($builder, array $filters)
    {
        $builder = $builder
            ->select('invoices.*, customers.name as customer_name, customers.customer_code')
            ->join('customers', 'customers.id = invoices.customer_id', 'left');

        if (! empty($filters['status'])) {
            $builder = $builder->where('invoices.status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $builder = $builder->where('invoices.customer_id', $filters['customer_id']);
        }

        return $builder;
    }

    /**
     * @param array{status?: string} $filters
     */
    public function paginateForCustomer(int $customerId, array $filters, int $page, int $perPage): array
    {
        $filters['customer_id'] = $customerId;

        return $this->paginateScoped($filters, $page, $perPage);
    }

    public function create(array $data): int
    {
        $data['tenant_id'] = TenantHelper::requireTenantId();

        return (int) $this->model->insert($data);
    }

    public function nextInvoiceNumber(): string
    {
        $tenantId = TenantHelper::requireTenantId();
        $seq      = $this->model->withDeleted()->where('tenant_id', $tenantId)->countAllResults() + 1;
        $number   = 'INV-' . $tenantId . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        while ($this->model->withDeleted()->where('tenant_id', $tenantId)->where('invoice_number', $number)->first()) {
            $number = 'INV-' . $tenantId . '-' . str_pad((string) (++$seq), 5, '0', STR_PAD_LEFT);
        }

        return $number;
    }
}
