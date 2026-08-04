<?php

namespace App\Models;

use App\Helpers\AuthHelper;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'tenant_id', 'role_id', 'name', 'email', 'phone', 'password',
        'is_active', 'last_login_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'     => 'required|min_length[2]|max_length[150]',
        'email'    => 'required|valid_email|max_length[150]|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[8]',
        'role_id'  => 'required|integer',
    ];

    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = AuthHelper::hashPassword($data['data']['password']);
        }

        return $data;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * User row joined with its role slug/name — the shape every auth flow needs.
     */
    public function findWithRole(int $id): ?array
    {
        return $this->select('users.*, roles.slug as role_slug, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->find($id);
    }

    public function findByEmailWithRole(string $email): ?array
    {
        return $this->select('users.*, roles.slug as role_slug, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.email', $email)
            ->first();
    }
}
