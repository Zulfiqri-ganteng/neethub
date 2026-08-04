<?php

namespace App\Models;

use CodeIgniter\Model;

class PlatformPaymentModel extends Model
{
    protected $table            = 'platform_payments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'platform_invoice_id', 'tenant_id', 'payment_method', 'amount', 'proof_image',
        'gateway_reference', 'gateway_payload', 'status', 'verified_by', 'verified_at', 'paid_at', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function pending(): array
    {
        return $this->select('platform_payments.*, tenants.name as tenant_name')
            ->join('tenants', 'tenants.id = platform_payments.tenant_id')
            ->where('platform_payments.status', 'pending')
            ->orderBy('platform_payments.created_at', 'ASC')
            ->findAll();
    }
}
