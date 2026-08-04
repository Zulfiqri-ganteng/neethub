<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentGatewayToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'payment_gateway' => [
                'type'       => 'ENUM',
                'constraint' => ['midtrans', 'xendit'],
                'null'       => true,
                'after'      => 'qris_static_code',
            ],
            'payment_gateway_config' => [
                'type'    => 'TEXT',
                'null'    => true,
                'after'   => 'payment_gateway',
                'comment' => 'Encrypted JSON, shape depends on payment_gateway (server_key/client_key for Midtrans, secret_key/callback_token for Xendit)',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'payment_gateway');
        $this->forge->dropColumn('tenants', 'payment_gateway_config');
    }
}
