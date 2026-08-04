<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQrisStaticCodeToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'qris_static_code' => [
                'type'    => 'TEXT',
                'null'    => true,
                'after'   => 'qris_image',
                'comment' => 'Static EMV QRIS payload string from the tenant\'s PSP; used to derive a per-invoice dynamic QR (amount injected + CRC recomputed)',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'qris_static_code');
    }
}
