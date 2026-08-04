<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotifications extends Migration
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
                'null'     => true,
            ],
            'notifiable_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'customer|user',
            ],
            'notifiable_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'channel' => [
                'type'       => 'ENUM',
                'constraint' => ['whatsapp', 'email', 'push'],
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'invoice_reminder|payment_confirmation|suspend|reactivation|broadcast|ticket_update',
            ],
            'payload' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'sent', 'failed'],
                'default'    => 'pending',
            ],
            'attempts' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
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
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'channel']);
        $this->forge->addKey('tenant_id');
        $this->forge->addKey(['notifiable_type', 'notifiable_id']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('notifications');
    }

    public function down()
    {
        $this->forge->dropTable('notifications');
    }
}
