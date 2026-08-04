<?php

namespace App\Repositories;

use App\Helpers\TenantHelper;
use App\Models\PackageModel;

class PackageRepository extends BaseRepository
{
    public function __construct(?PackageModel $model = null)
    {
        parent::__construct($model ?? new PackageModel(), 'packages');
    }

    /**
     * @param array{is_active?: bool} $filters
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->paginateScoped($filters, $page, $perPage);
    }

    protected function applyFilters($builder, array $filters)
    {
        if (array_key_exists('is_active', $filters)) {
            $builder = $builder->where('is_active', $filters['is_active'] ? 1 : 0);
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
