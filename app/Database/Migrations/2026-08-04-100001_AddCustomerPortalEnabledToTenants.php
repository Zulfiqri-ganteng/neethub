<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomerPortalEnabledToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'customer_portal_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'after'      => 'status',
                'comment'    => 'Per-tenant flag: whether new customers get an auto-provisioned pelanggan login (Stage 13). Default on — owner can turn it off in Settings.',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'customer_portal_enabled');
    }
}
