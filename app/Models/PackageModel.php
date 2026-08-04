<?php

namespace App\Models;

use CodeIgniter\Model;

class PackageModel extends Model
{
    protected $table            = 'packages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'name', 'description', 'speed_download', 'speed_upload', 'price',
        'billing_cycle', 'custom_cycle_days', 'fup_quota_gb', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'           => 'required|min_length[2]|max_length[100]',
        'speed_download' => 'required|integer|greater_than[0]',
        'speed_upload'   => 'required|integer|greater_than[0]',
        'price'          => 'required|decimal|greater_than[0]',
        'billing_cycle'  => 'required|in_list[weekly,monthly,yearly,custom]',
    ];
}
