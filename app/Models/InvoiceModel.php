<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table            = 'invoices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'customer_id', 'package_id', 'invoice_number', 'period_start', 'period_end',
        'amount', 'discount', 'tax', 'total_amount', 'status', 'due_date', 'paid_at', 'pdf_path',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function findLastForCustomer(int $customerId): ?array
    {
        return $this->where('customer_id', $customerId)
            ->orderBy('period_end', 'DESC')
            ->first();
    }

    /**
     * Marks unpaid invoices whose due_date has passed as overdue. Safe to
     * call repeatedly (idempotent) — used by the invoice:generate command.
     */
    public function markOverdueForTenant(int $tenantId): int
    {
        $this->where('tenant_id', $tenantId)
            ->where('status', 'unpaid')
            ->where('due_date <', date('Y-m-d'))
            ->set(['status' => 'overdue'])
            ->update();

        return $this->db->affectedRows();
    }
}
