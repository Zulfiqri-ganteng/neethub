<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TenantWhatsappTokenDropThemeColor extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'whatsapp_token' => [
                'type'    => 'TEXT',
                'null'    => true,
                'after'   => 'whatsapp',
                'comment' => 'Encrypted Fonnte device token — each tenant connects their own WhatsApp number, same pattern as router/payment-gateway credentials',
            ],
        ]);

        // theme_color was stored but never actually applied anywhere in the
        // UI — a dead setting. Dropped rather than left lingering.
        $this->forge->dropColumn('tenants', 'theme_color');
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'whatsapp_token');
        $this->forge->addColumn('tenants', [
            'theme_color' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => '#1E88E5',
            ],
        ]);
    }
}
