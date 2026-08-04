<?php

namespace App\Repositories;

use App\Helpers\TenantHelper;
use CodeIgniter\Model;

/**
 * Every tenant-scoped repository extends this. Centralizes the one rule
 * that must never be forgotten: no query leaves this layer without a
 * tenant_id filter (super admin cross-tenant access, when needed, is a
 * deliberate separate code path — not the default).
 *
 * Columns are always qualified with $tableName because subclasses often
 * LEFT JOIN another tenant-scoped table (e.g. invoices -> customers) that
 * also has its own tenant_id/id columns — an unqualified column name would
 * be ambiguous to MySQL the moment a join is added.
 */
abstract class BaseRepository
{
    public function __construct(protected Model $model, protected string $tableName)
    {
    }

    protected function scoped()
    {
        return $this->model->where("{$this->tableName}.tenant_id", TenantHelper::requireTenantId());
    }

    /**
     * @return array{items: array, total: int, page: int, per_page: int}
     */
    protected function paginateScoped(array $filters, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $builder = $this->applyFilters($this->scoped(), $filters);
        $total   = $builder->countAllResults(false);

        $items = $builder->orderBy("{$this->tableName}.id", 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Override in subclasses to translate a filters array into where()
     * clauses. Default: no extra filtering.
     */
    protected function applyFilters($builder, array $filters)
    {
        return $builder;
    }

    public function find(int $id): ?array
    {
        return $this->scoped()->where("{$this->tableName}.id", $id)->first();
    }

    public function delete(int $id): bool
    {
        // Confirm the row is actually in this tenant's scope first, so a
        // cross-tenant ID can't even report a misleading "success".
        if (! $this->find($id)) {
            return false;
        }

        return (bool) $this->model->delete($id);
    }
}
