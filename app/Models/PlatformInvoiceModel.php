<?php

namespace App\Models;

use CodeIgniter\Model;

class PlatformInvoiceModel extends Model
{
    protected $table            = 'platform_invoices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'period_start', 'period_end', 'amount', 'status', 'due_date', 'paid_at', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function latestForTenant(int $tenantId): ?array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('period_end', 'DESC')->first();
    }

    public function forTenant(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('period_start', 'DESC')->findAll();
    }

    /**
     * Unpaid invoices whose due_date + $graceDays has already passed —
     * candidates for auto-suspension.
     */
    public function overdueBeyondGrace(int $graceDays): array
    {
        $cutoff = date('Y-m-d', strtotime("-{$graceDays} days"));

        return $this->whereIn('status', ['unpaid', 'overdue'])
            ->where('due_date <=', $cutoff)
            ->findAll();
    }
}
