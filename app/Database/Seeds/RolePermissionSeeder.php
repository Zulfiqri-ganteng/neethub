<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * slug => name
     */
    private array $roles = [
        'super_admin' => 'Super Admin SaaS',
        'owner'       => 'Owner',
        'admin'       => 'Admin',
        'teknisi'     => 'Teknisi',
        'kasir'       => 'Kasir',
        'pelanggan'   => 'Pelanggan',
    ];

    /**
     * group => [slug => name]
     */
    private array $permissionGroups = [
        'tenants' => [
            'tenants.manage' => 'Kelola Tenant (Global)',
        ],
        'dashboard' => [
            'dashboard.view' => 'Lihat Dashboard',
        ],
        'customers' => [
            'customers.view'    => 'Lihat Pelanggan',
            'customers.create'  => 'Tambah Pelanggan',
            'customers.update'  => 'Edit Pelanggan',
            'customers.delete'  => 'Hapus Pelanggan',
            'customers.suspend' => 'Suspend/Enable Pelanggan',
        ],
        'packages' => [
            'packages.view'   => 'Lihat Paket',
            'packages.create' => 'Tambah Paket',
            'packages.update' => 'Edit Paket',
            'packages.delete' => 'Hapus Paket',
        ],
        'invoices' => [
            'invoices.view'   => 'Lihat Tagihan',
            'invoices.create' => 'Buat Tagihan',
            'invoices.update' => 'Edit Tagihan',
        ],
        'payments' => [
            'payments.view'   => 'Lihat Pembayaran',
            'payments.verify' => 'Verifikasi Pembayaran',
        ],
        'routers' => [
            'routers.view'   => 'Lihat Router',
            'routers.create' => 'Tambah Router',
            'routers.update' => 'Edit Router',
            'routers.delete' => 'Hapus Router',
            'routers.test'   => 'Test Koneksi Router',
        ],
        'monitoring' => [
            'monitoring.view' => 'Lihat Monitoring',
        ],
        'broadcast' => [
            'broadcast.send' => 'Kirim Broadcast',
        ],
        'reports' => [
            'reports.view' => 'Lihat Laporan',
        ],
        'settings' => [
            'settings.manage' => 'Kelola Pengaturan Tenant',
        ],
        'tickets' => [
            'tickets.view'   => 'Lihat Komplain',
            'tickets.create' => 'Buat Komplain',
            'tickets.update' => 'Update Komplain',
            'tickets.assign' => 'Assign Komplain',
        ],
        'profile' => [
            'profile.manage' => 'Kelola Profil Sendiri',
        ],
    ];

    /**
     * role slug => permission slugs (or ['*'] for all)
     */
    private array $rolePermissions = [
        'super_admin' => ['*'],
        'owner' => [
            'dashboard.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.suspend',
            'packages.view', 'packages.create', 'packages.update', 'packages.delete',
            'invoices.view', 'invoices.create', 'invoices.update',
            'payments.view', 'payments.verify',
            'routers.view', 'routers.create', 'routers.update', 'routers.delete', 'routers.test',
            'monitoring.view',
            'broadcast.send',
            'reports.view',
            'settings.manage',
            'tickets.view', 'tickets.update', 'tickets.assign',
            'profile.manage',
        ],
        'admin' => [
            'dashboard.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.suspend',
            'packages.view', 'packages.create', 'packages.update',
            'invoices.view', 'invoices.create', 'invoices.update',
            'payments.view', 'payments.verify',
            'routers.view', 'routers.create', 'routers.update', 'routers.test',
            'monitoring.view',
            'broadcast.send',
            'reports.view',
            'tickets.view', 'tickets.update', 'tickets.assign',
            'profile.manage',
        ],
        'teknisi' => [
            'dashboard.view',
            'customers.view', 'customers.suspend',
            'routers.view', 'routers.update', 'routers.test',
            'monitoring.view',
            'tickets.view', 'tickets.update',
            'profile.manage',
        ],
        'kasir' => [
            'dashboard.view',
            'customers.view',
            'invoices.view', 'invoices.create',
            'payments.view', 'payments.verify',
            'reports.view',
            'profile.manage',
        ],
        'pelanggan' => [
            'invoices.view',
            'payments.view',
            'tickets.view', 'tickets.create',
            'profile.manage',
        ],
    ];

    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // Roles
        $roleIds = [];
        foreach ($this->roles as $slug => $name) {
            $existing = $this->db->table('roles')->where('slug', $slug)->get()->getRow();
            if ($existing) {
                $roleIds[$slug] = $existing->id;
                continue;
            }
            $this->db->table('roles')->insert([
                'slug'       => $slug,
                'name'       => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roleIds[$slug] = $this->db->insertID();
        }

        // Permissions
        $permissionIds = [];
        foreach ($this->permissionGroups as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                $existing = $this->db->table('permissions')->where('slug', $slug)->get()->getRow();
                if ($existing) {
                    $permissionIds[$slug] = $existing->id;
                    continue;
                }
                $this->db->table('permissions')->insert([
                    'slug'       => $slug,
                    'name'       => $name,
                    'group'      => $group,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $permissionIds[$slug] = $this->db->insertID();
            }
        }

        // Role <-> Permission
        foreach ($this->rolePermissions as $roleSlug => $permissionSlugs) {
            $slugs = $permissionSlugs === ['*'] ? array_keys($permissionIds) : $permissionSlugs;

            foreach ($slugs as $permissionSlug) {
                $roleId       = $roleIds[$roleSlug];
                $permissionId = $permissionIds[$permissionSlug];

                $existing = $this->db->table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->get()->getRow();

                if ($existing) {
                    continue;
                }

                $this->db->table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permissionId,
                    'created_at'    => $now,
                ]);
            }
        }
    }
}
