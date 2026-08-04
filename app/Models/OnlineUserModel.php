<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Real-time router session state. Populated by the router sync job added in
 * the Router Integration stage — for now this model exists just so features
 * built earlier (like the dashboard) have something to query against.
 */
class OnlineUserModel extends Model
{
    protected $table            = 'online_users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'tenant_id', 'router_id', 'customer_id', 'username', 'ip_address', 'mac_address',
        'uptime_seconds', 'bytes_in', 'bytes_out', 'session_start', 'last_seen_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
