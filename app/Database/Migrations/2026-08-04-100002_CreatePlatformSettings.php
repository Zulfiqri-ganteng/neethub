<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Single-row config table (id=1 always) — Super Admin-editable platform
 * pricing/grace period, so changing them doesn't need a redeploy (Stage 13
 * decision: "someone selling this to many different WiFi businesses will
 * want to change pricing without a redeploy").
 */
class CreatePlatformSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'TINYINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'monthly_fee' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 100000,
            ],
            'trial_days' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 30,
            ],
            'grace_days' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 3,
                'comment'  => 'Days after due_date before an unpaid platform invoice auto-suspends the tenant',
            ],
            'bank_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'bank_account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'bank_account_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('platform_settings');

        $this->db->table('platform_settings')->insert([
            'monthly_fee' => 100000,
            'trial_days'  => 30,
            'grace_days'  => 3,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('platform_settings');
    }
}
