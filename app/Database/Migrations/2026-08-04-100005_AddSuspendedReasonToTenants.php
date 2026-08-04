<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuspendedReasonToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'suspended_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'status',
                'comment'    => 'e.g. unpaid_platform_invoice (auto, Stage 13) vs null when manually suspended by Super Admin, or when active',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'suspended_reason');
    }
}
