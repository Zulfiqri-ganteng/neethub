<?php

namespace App\Repositories;

use App\Helpers\TenantHelper;
use App\Models\CustomerModel;

class CustomerRepository extends BaseRepository
{
    public function __construct(?CustomerModel $model = null)
    {
        parent::__construct($model ?? new CustomerModel(), 'customers');
    }

    /**
     * @param array{status?: string, search?: string, package_id?: int} $filters
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->paginateScoped($filters, $page, $perPage);
    }

    protected function applyFilters($builder, array $filters)
    {
        // Join package/router names now so the list view never needs a
        // per-row follow-up query (no N+1).
        $builder = $builder
            ->select('customers.*, packages.name as package_name, routers.name as router_name')
            ->join('packages', 'packages.id = customers.package_id', 'left')
            ->join('routers', 'routers.id = customers.router_id', 'left');

        if (! empty($filters['status'])) {
            $builder = $builder->where('customers.status', $filters['status']);
        }

        if (! empty($filters['package_id'])) {
            $builder = $builder->where('customers.package_id', $filters['package_id']);
        }

        if (! empty($filters['search'])) {
            $builder = $builder->groupStart()
                ->like('customers.name', $filters['search'])
                ->orLike('customers.customer_code', $filters['search'])
                ->orLike('customers.phone', $filters['search'])
                ->orLike('customers.email', $filters['search'])
                ->groupEnd();
        }

        return $builder;
    }

    public function create(array $data): int
    {
        $data['tenant_id']     = TenantHelper::requireTenantId();
        $data['customer_code'] = $data['customer_code'] ?? $this->nextCustomerCode();

        return (int) $this->model->insert($data);
    }

    public function update(int $id, array $data): bool
    {
        if (! $this->find($id)) {
            return false;
        }

        return $this->model->update($id, $data);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->scoped()->where('user_id', $userId)->first();
    }

    private function nextCustomerCode(): string
    {
        $tenantId = TenantHelper::requireTenantId();
        $seq      = $this->model->withDeleted()->where('tenant_id', $tenantId)->countAllResults() + 1;
        $code     = 'CUST-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        while ($this->model->withDeleted()->where('tenant_id', $tenantId)->where('customer_code', $code)->first()) {
            $code = 'CUST-' . str_pad((string) (++$seq), 5, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
