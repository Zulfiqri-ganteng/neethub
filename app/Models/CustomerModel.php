<?php

namespace App\Models;

use App\Helpers\CryptoHelper;
use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table            = 'customers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'user_id', 'package_id', 'router_id', 'customer_code', 'name', 'email',
        'phone', 'whatsapp', 'address', 'pppoe_username', 'pppoe_password', 'ip_address',
        'mac_address', 'status', 'installation_date', 'referral_code', 'referred_by',
        'latitude', 'longitude', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'          => 'required|min_length[2]|max_length[150]',
        'customer_code' => 'required|max_length[30]',
        'status'        => 'permit_empty|in_list[pending,active,suspended,disabled]',
    ];

    protected $beforeInsert = ['encryptPppoePassword'];
    protected $beforeUpdate = ['encryptPppoePassword'];

    protected function encryptPppoePassword(array $data): array
    {
        if (isset($data['data']['pppoe_password'])) {
            $data['data']['pppoe_password'] = CryptoHelper::encrypt($data['data']['pppoe_password']);
        }

        return $data;
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }
}
