<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityModel extends Model
{
    protected $table            = 'activities';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'tenant_id', 'user_id', 'action', 'description', 'subject_type', 'subject_id',
        'ip_address', 'user_agent', 'meta',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function log(
        string $action,
        ?int $tenantId,
        ?int $userId,
        string $description = '',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $meta = null
    ): void {
        $this->insert([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => $action,
            'description'  => $description,
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
            'meta'         => $meta !== null ? json_encode($meta) : null,
        ]);
    }
}
