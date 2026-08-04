<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mirrors `invoices`' shape deliberately (Stage 13), but kept as a fully
 * separate table — this bills a *tenant* for platform usage, not a
 * tenant's customer for internet service. Keeping it separate avoids any
 * chance of a platform invoice leaking into a tenant's own
 * tenant-scoped customer-invoice queries (tenant data isolation is the
 * #1 rule in this file).
 */
class CreatePlatformInvoices extends Migration
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
            'period_start' => [
                'type' => 'DATE',
            ],
            'period_end' => [
                'type' => 'DATE',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['unpaid', 'paid', 'overdue', 'cancelled'],
                'default'    => 'unpaid',
            ],
            'due_date' => [
                'type' => 'DATE',
            ],
            'paid_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addUniqueKey(['tenant_id', 'period_start']);
        $this->forge->addKey(['tenant_id', 'status']);
        $this->forge->addKey('due_date');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('platform_invoices');
    }

    public function down()
    {
        $this->forge->dropTable('platform_invoices');
    }
}
