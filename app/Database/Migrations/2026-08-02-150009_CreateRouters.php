<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRouters extends Migration
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
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['mikrotik', 'ubiquiti', 'omada', 'openwrt', 'huawei', 'cisco', 'ruijie'],
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
            ],
            'port' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 8728,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'comment'    => 'encrypted at rest',
            ],
            'api_scheme' => [
                'type'       => 'ENUM',
                'constraint' => ['api', 'api-ssl', 'ssh'],
                'default'    => 'api',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['online', 'offline', 'unknown'],
                'default'    => 'unknown',
            ],
            'last_checked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'location' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addKey(['tenant_id', 'status']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('routers');
    }

    public function down()
    {
        $this->forge->dropTable('routers');
    }
}
