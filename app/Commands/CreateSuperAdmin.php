<?php

namespace App\Commands;

use App\Models\RoleModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-off provisioning for the platform operator's own login — there's no
 * self-registration path for `super_admin` on purpose (Stage 13: this role
 * is the platform operator, not a tenant). Run once:
 *   php spark superadmin:create --name "Zulfiqri" --email you@example.com --password "..."
 */
class CreateSuperAdmin extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'superadmin:create';
    protected $description = 'Creates a super_admin login (the platform operator account, not tied to any tenant).';
    protected $usage       = 'superadmin:create --name "..." --email you@example.com --password "..."';

    public function run(array $params)
    {
        $name     = $params['name'] ?? CLI::prompt('Nama');
        $email    = $params['email'] ?? CLI::prompt('Email');
        $password = $params['password'] ?? CLI::prompt('Password (min 8 karakter)');

        if (strlen($password) < 8) {
            CLI::error('Password minimal 8 karakter.');

            return;
        }

        $userModel = new UserModel();
        if ($userModel->findByEmail($email)) {
            CLI::error("Email '{$email}' sudah dipakai.");

            return;
        }

        $role = (new RoleModel())->findBySlug('super_admin');
        if (! $role) {
            CLI::error("Role 'super_admin' tidak ditemukan — jalankan db:seed RolePermissionSeeder dulu.");

            return;
        }

        $userModel->insert([
            'tenant_id' => null,
            'role_id'   => $role['id'],
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'is_active' => 1,
        ]);

        CLI::write("Super Admin '{$email}' berhasil dibuat.", 'green');
    }
}
