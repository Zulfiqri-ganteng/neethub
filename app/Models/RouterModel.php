<?php

namespace App\Models;

use App\Helpers\CryptoHelper;
use CodeIgniter\Model;

class RouterModel extends Model
{
    protected $table            = 'routers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'name', 'type', 'ip_address', 'port', 'username', 'password',
        'api_scheme', 'status', 'last_checked_at', 'location', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'       => 'required|min_length[2]|max_length[100]',
        'type'       => 'required|in_list[mikrotik,ubiquiti,omada,openwrt,huawei,cisco,ruijie]',
        'ip_address' => 'required|max_length[45]',
        'username'   => 'required|max_length[100]',
    ];

    protected $beforeInsert = ['encryptPassword'];
    protected $beforeUpdate = ['encryptPassword'];

    protected function encryptPassword(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = CryptoHelper::encrypt($data['data']['password']);
        }

        return $data;
    }
}
