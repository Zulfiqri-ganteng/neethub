<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOnlineUsers extends Migration
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
            'router_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'mac_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 17,
                'null'       => true,
            ],
            'uptime_seconds' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'bytes_in' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'bytes_out' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'session_start' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addUniqueKey(['router_id', 'username']);
        $this->forge->addKey('tenant_id');
        $this->forge->addKey('last_seen_at');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('router_id', 'routers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('online_users');
    }

    public function down()
    {
        $this->forge->dropTable('online_users');
    }
}
