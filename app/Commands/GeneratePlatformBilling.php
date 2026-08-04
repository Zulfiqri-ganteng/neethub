<?php

namespace App\Commands;

use App\Models\TenantModel;
use App\Services\PlatformBillingService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Meant to run daily via cron (same shared-hosting cron slot pattern as
 * `invoices:generate`) — generates due platform invoices for every tenant
 * still active or suspended-for-billing (so a suspended tenant's next
 * invoice still gets generated on schedule once they pay and reactivate),
 * then suspends anyone past their grace period. Safe to run more than once
 * a day (idempotent, same contract as invoices:generate).
 */
class GeneratePlatformBilling extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'platform:billing';
    protected $description = 'Generates due platform-subscription invoices and suspends tenants past their grace period.';

    public function run(array $params)
    {
        $service = new PlatformBillingService();

        // Trial/paying tenants who are suspended for a *manual* (non-billing)
        // reason should NOT get billed further while suspended — only
        // 'active' tenants, and tenants already suspended specifically for
        // unpaid billing (so their next invoice is still ready the moment
        // they pay and get reactivated).
        $tenants = (new TenantModel())
            ->groupStart()
                ->where('status', 'active')
                ->orWhere('suspended_reason', 'unpaid_platform_invoice')
            ->groupEnd()
            ->findAll();

        $generated = 0;
        foreach ($tenants as $tenant) {
            if ($service->generateForTenant($tenant)) {
                $generated++;
                CLI::write("Tenant {$tenant['slug']}: tagihan platform baru dibuat.", 'green');
            }
        }

        $result = $service->enforceOverdue();

        CLI::write(
            "Done. {$generated} tagihan baru, {$result['marked_overdue']} ditandai overdue, {$result['suspended']} tenant disuspend.",
            'yellow'
        );
    }
}
