<?php

namespace App\Repositories;

use App\Helpers\TenantHelper;
use App\Models\RouterModel;

class RouterRepository extends BaseRepository
{
    public function __construct(?RouterModel $model = null)
    {
        parent::__construct($model ?? new RouterModel(), 'routers');
    }

    /**
     * @param array{status?: string} $filters
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->paginateScoped($filters, $page, $perPage);
    }

    protected function applyFilters($builder, array $filters)
    {
        if (! empty($filters['status'])) {
            $builder = $builder->where('routers.status', $filters['status']);
        }

        return $builder;
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
