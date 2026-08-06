<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Super Admin-configurable pricing tiers keyed by a tenant's total customer
 * count (all statuses, per the user's own decision — reflects business
 * scale regardless of how many customers happen to be suspended right now).
 * Replaces the flat `platform_settings.monthly_fee` as the *default* source
 * of truth for a generated platform invoice's amount — see
 * PlatformBillingService::generateForTenant(), which falls back to the flat
 * fee only if no tier matches (empty table, or a gap between two tiers), so
 * a misconfigured tier table can never break billing outright.
 *
 * `max_customers = NULL` means "and above" (the top/unbounded tier).
 * Overlap between tiers is enforced at the application layer
 * (PlatformPricingTierModel::overlaps), not at the DB level.
 */
class CreatePlatformPricingTiers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'min_customers' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'max_customers' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'comment'  => 'NULL = unbounded ("and above")',
            ],
            'monthly_fee' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
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
        $this->forge->createTable('platform_pricing_tiers');

        $now = date('Y-m-d H:i:s');
        $this->db->table('platform_pricing_tiers')->insertBatch([
            ['min_customers' => 0, 'max_customers' => 25, 'monthly_fee' => 50000, 'created_at' => $now, 'updated_at' => $now],
            ['min_customers' => 26, 'max_customers' => 75, 'monthly_fee' => 100000, 'created_at' => $now, 'updated_at' => $now],
            ['min_customers' => 76, 'max_customers' => 200, 'monthly_fee' => 200000, 'created_at' => $now, 'updated_at' => $now],
            ['min_customers' => 201, 'max_customers' => 500, 'monthly_fee' => 350000, 'created_at' => $now, 'updated_at' => $now],
            ['min_customers' => 501, 'max_customers' => null, 'monthly_fee' => 600000, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('platform_pricing_tiers');
    }
}
