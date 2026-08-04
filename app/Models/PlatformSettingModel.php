<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Always exactly one row (id=1, seeded by its migration). Super Admin
 * pricing/grace-period config — see CreatePlatformSettings migration.
 */
class PlatformSettingModel extends Model
{
    protected $table            = 'platform_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'monthly_fee', 'trial_days', 'grace_days',
        'bank_name', 'bank_account_number', 'bank_account_name',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'monthly_fee' => 'required|decimal',
        'trial_days'  => 'required|integer|greater_than[0]',
        'grace_days'  => 'required|integer|greater_than_equal_to[0]',
    ];

    public function current(): array
    {
        return $this->find(1) ?? [
            'id' => 1, 'monthly_fee' => 100000, 'trial_days' => 30, 'grace_days' => 3,
        ];
    }
}
