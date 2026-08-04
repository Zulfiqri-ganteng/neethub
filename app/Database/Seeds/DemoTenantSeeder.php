<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds two independent demo tenants so multi-tenant isolation can be
 * verified immediately (see NetHub multi-tenant isolation rules).
 */
class DemoTenantSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $roleIds = [];
        foreach ($this->db->table('roles')->get()->getResult() as $role) {
            $roleIds[$role->slug] = $role->id;
        }

        $tenantDefs = [
            [
                'name'         => 'RT/RW Net Melati',
                'slug'         => 'melati-net',
                'whatsapp'     => '628111111111',
                'email'        => 'owner@melati-net.test',
                'bank_name'    => 'BCA',
                'bank_account' => '1234567890',
                'customer_seed'=> 'melati',
            ],
            [
                'name'         => 'Warnet Sejahtera',
                'slug'         => 'sejahtera-net',
                'whatsapp'     => '628222222222',
                'email'        => 'owner@sejahtera-net.test',
                'bank_name'    => 'Mandiri',
                'bank_account' => '0987654321',
                'customer_seed'=> 'sejahtera',
            ],
        ];

        foreach ($tenantDefs as $def) {
            $tenantId = $this->seedTenant($def, $now);
            $userIds  = $this->seedUsers($tenantId, $def, $roleIds, $now);
            $packageIds = $this->seedPackages($tenantId, $now);
            $routerId = $this->seedRouter($tenantId, $def, $now);
            $customerIds = $this->seedCustomers($tenantId, $def, $userIds, $packageIds, $routerId, $now);
            $this->seedInvoicesAndPayments($tenantId, $customerIds, $packageIds, $userIds, $now);
        }
    }

    private function seedTenant(array $def, string $now): int
    {
        $existing = $this->db->table('tenants')->where('slug', $def['slug'])->get()->getRow();
        if ($existing) {
            return (int) $existing->id;
        }

        $this->db->table('tenants')->insert([
            'name'                => $def['name'],
            'slug'                => $def['slug'],
            'whatsapp'            => $def['whatsapp'],
            'email'               => $def['email'],
            'bank_name'           => $def['bank_name'],
            'bank_account_number' => $def['bank_account'],
            'bank_account_name'   => $def['name'],
            'plan'                => 'pro',
            'status'              => 'active',
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return $this->db->insertID();
    }

    private function seedUsers(int $tenantId, array $def, array $roleIds, string $now): array
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
        $seed = $def['customer_seed'];

        $users = [
            'owner'   => ['name' => 'Owner ' . $def['name'], 'email' => "owner@{$seed}.test"],
            'admin'   => ['name' => 'Admin ' . $def['name'], 'email' => "admin@{$seed}.test"],
            'teknisi' => ['name' => 'Teknisi ' . $def['name'], 'email' => "teknisi@{$seed}.test"],
            'kasir'   => ['name' => 'Kasir ' . $def['name'], 'email' => "kasir@{$seed}.test"],
        ];

        $userIds = [];
        foreach ($users as $roleSlug => $u) {
            $existing = $this->db->table('users')->where('email', $u['email'])->get()->getRow();
            if ($existing) {
                $userIds[$roleSlug] = (int) $existing->id;
                continue;
            }

            $this->db->table('users')->insert([
                'tenant_id'  => $tenantId,
                'role_id'    => $roleIds[$roleSlug],
                'name'       => $u['name'],
                'email'      => $u['email'],
                'phone'      => '08123456789',
                'password'   => $hash,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userIds[$roleSlug] = $this->db->insertID();
        }

        return $userIds;
    }

    private function seedPackages(int $tenantId, string $now): array
    {
        $defs = [
            ['name' => 'Paket Hemat 10 Mbps', 'down' => 10, 'up' => 5, 'price' => 100000],
            ['name' => 'Paket Reguler 20 Mbps', 'down' => 20, 'up' => 10, 'price' => 150000],
            ['name' => 'Paket Premium 50 Mbps', 'down' => 50, 'up' => 25, 'price' => 300000],
        ];

        $ids = [];
        foreach ($defs as $d) {
            $this->db->table('packages')->insert([
                'tenant_id'      => $tenantId,
                'name'           => $d['name'],
                'speed_download' => $d['down'],
                'speed_upload'   => $d['up'],
                'price'          => $d['price'],
                'billing_cycle'  => 'monthly',
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $ids[] = $this->db->insertID();
        }

        return $ids;
    }

    private function seedRouter(int $tenantId, array $def, string $now): int
    {
        // Seeders write via the query builder directly, bypassing Model
        // beforeInsert hooks — so unlike real API-created routers, the
        // password has to be encrypted by hand here to match what
        // RouterDriverFactory expects to decrypt.
        $this->db->table('routers')->insert([
            'tenant_id'  => $tenantId,
            'name'       => 'Router Utama - ' . $def['name'],
            'type'       => 'mikrotik',
            'ip_address' => '192.168.88.1',
            'port'       => 8728,
            'username'   => 'admin',
            'password'   => \App\Helpers\CryptoHelper::encrypt('demo-placeholder-password'),
            'api_scheme' => 'api',
            'status'     => 'unknown',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->insertID();
    }

    private function seedCustomers(int $tenantId, array $def, array $userIds, array $packageIds, int $routerId, string $now): array
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
        $seed = $def['customer_seed'];
        $pelangganRoleId = $this->db->table('roles')->where('slug', 'pelanggan')->get()->getRow()->id;

        $names = ['Budi Santoso', 'Siti Aminah', 'Andi Wijaya'];
        $statuses = ['active', 'active', 'suspended'];
        $customerIds = [];

        foreach ($names as $i => $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . "@{$seed}.test";

            $existingUser = $this->db->table('users')->where('email', $email)->get()->getRow();
            if ($existingUser) {
                $userId = (int) $existingUser->id;
            } else {
                $this->db->table('users')->insert([
                    'tenant_id'  => $tenantId,
                    'role_id'    => $pelangganRoleId,
                    'name'       => $name,
                    'email'      => $email,
                    'phone'      => '0812000000' . $i,
                    'password'   => $hash,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $userId = $this->db->insertID();
            }

            $customerCode = strtoupper(substr($seed, 0, 3)) . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $existingCustomer = $this->db->table('customers')
                ->where('tenant_id', $tenantId)
                ->where('customer_code', $customerCode)
                ->get()->getRow();

            if ($existingCustomer) {
                $customerIds[] = (int) $existingCustomer->id;
                continue;
            }

            $this->db->table('customers')->insert([
                'tenant_id'        => $tenantId,
                'user_id'          => $userId,
                'package_id'       => $packageIds[$i % count($packageIds)],
                'router_id'        => $routerId,
                'customer_code'    => $customerCode,
                'name'             => $name,
                'email'            => $email,
                'phone'            => '0812000000' . $i,
                'pppoe_username'   => strtolower($seed) . $i,
                'status'           => $statuses[$i],
                'installation_date'=> date('Y-m-d', strtotime('-3 months')),
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $customerIds[] = $this->db->insertID();
        }

        return $customerIds;
    }

    private function seedInvoicesAndPayments(int $tenantId, array $customerIds, array $packageIds, array $userIds, string $now): void
    {
        foreach ($customerIds as $i => $customerId) {
            $customer = $this->db->table('customers')->where('id', $customerId)->get()->getRow();
            $package  = $this->db->table('packages')->where('id', $customer->package_id)->get()->getRow();

            $invoiceNumber = 'INV-' . $tenantId . '-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);

            $existing = $this->db->table('invoices')
                ->where('tenant_id', $tenantId)
                ->where('invoice_number', $invoiceNumber)
                ->get()->getRow();
            if ($existing) {
                continue;
            }

            $status  = $i === 2 ? 'overdue' : 'paid';
            $dueDate = $i === 2 ? date('Y-m-d', strtotime('-5 days')) : date('Y-m-d', strtotime('+10 days'));

            $this->db->table('invoices')->insert([
                'tenant_id'      => $tenantId,
                'customer_id'    => $customerId,
                'package_id'     => $package->id,
                'invoice_number' => $invoiceNumber,
                'period_start'   => date('Y-m-01'),
                'period_end'     => date('Y-m-t'),
                'amount'         => $package->price,
                'total_amount'   => $package->price,
                'status'         => $status,
                'due_date'       => $dueDate,
                'paid_at'        => $status === 'paid' ? $now : null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $invoiceId = $this->db->insertID();

            if ($status === 'paid') {
                $this->db->table('payments')->insert([
                    'tenant_id'      => $tenantId,
                    'invoice_id'     => $invoiceId,
                    'customer_id'    => $customerId,
                    'payment_method' => 'qris',
                    'amount'         => $package->price,
                    'status'         => 'verified',
                    'verified_by'    => $userIds['kasir'],
                    'verified_at'    => $now,
                    'paid_at'        => $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }
}
