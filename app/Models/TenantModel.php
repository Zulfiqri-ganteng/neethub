<?php

namespace App\Models;

use App\Helpers\CryptoHelper;
use CodeIgniter\Model;

class TenantModel extends Model
{
    protected $table            = 'tenants';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'name', 'slug', 'logo', 'address', 'whatsapp', 'whatsapp_token', 'email',
        'qris_image', 'qris_static_code', 'bank_name', 'bank_account_number', 'bank_account_name',
        'payment_gateway', 'payment_gateway_config',
        'plan', 'status', 'trial_ends_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[150]',
        'slug' => 'required|alpha_dash|max_length[100]|is_unique[tenants.slug,id,{id}]',
    ];

    protected $beforeInsert = ['encryptSecrets'];
    protected $beforeUpdate = ['encryptSecrets'];

    /**
     * `payment_gateway_config` (Midtrans server_key / Xendit secret_key)
     * and `whatsapp_token` (Fonnte device token) are both merchant/gateway
     * secrets — same "encrypted at rest" treatment as router passwords and
     * PPPoE passwords.
     */
    protected function encryptSecrets(array $data): array
    {
        foreach (['payment_gateway_config', 'whatsapp_token'] as $field) {
            if (isset($data['data'][$field]) && is_string($data['data'][$field])) {
                $data['data'][$field] = CryptoHelper::encrypt($data['data'][$field]);
            }
        }

        return $data;
    }
}
