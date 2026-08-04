<?php

namespace App\Repositories;

use App\Helpers\TenantHelper;
use App\Models\PaymentModel;

class PaymentRepository extends BaseRepository
{
    public function __construct(?PaymentModel $model = null)
    {
        parent::__construct($model ?? new PaymentModel(), 'payments');
    }

    /**
     * @param array{status?: string, payment_method?: string, customer_id?: int} $filters
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->paginateScoped($filters, $page, $perPage);
    }

    protected function applyFilters($builder, array $filters)
    {
        $builder = $builder
            ->select('payments.*, customers.name as customer_name, invoices.invoice_number')
            ->join('customers', 'customers.id = payments.customer_id', 'left')
            ->join('invoices', 'invoices.id = payments.invoice_id', 'left');

        if (! empty($filters['status'])) {
            $builder = $builder->where('payments.status', $filters['status']);
        }

        if (! empty($filters['payment_method'])) {
            $builder = $builder->where('payments.payment_method', $filters['payment_method']);
        }

        if (! empty($filters['customer_id'])) {
            $builder = $builder->where('payments.customer_id', $filters['customer_id']);
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

    public function update(int $id, array $data): bool
    {
        if (! $this->find($id)) {
            return false;
        }

        return $this->model->update($id, $data);
    }
}
