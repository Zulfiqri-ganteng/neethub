<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePackages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tenant_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'speed_download' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'comment'    => 'Mbps',
            ],
            'speed_upload' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Mbps',
            ],
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
            ],
            'billing_cycle' => [
                'type'       => 'ENUM',
                'constraint' => ['weekly', 'monthly', 'yearly', 'custom'],
                'default'    => 'monthly',
            ],
            'custom_cycle_days' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'fup_quota_gb' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'is_active']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('packages');
    }

    public function down()
    {
        $this->forge->dropTable('packages');
    }
}
