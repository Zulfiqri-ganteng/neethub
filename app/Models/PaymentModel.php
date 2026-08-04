<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table            = 'payments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'invoice_id', 'customer_id', 'payment_method', 'amount', 'proof_image',
        'gateway_reference', 'gateway_payload', 'status', 'verified_by', 'verified_at',
        'paid_at', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'payment_method' => 'required|in_list[qris,transfer,gateway,cash]',
        'amount'         => 'required|decimal|greater_than[0]',
    ];
}
