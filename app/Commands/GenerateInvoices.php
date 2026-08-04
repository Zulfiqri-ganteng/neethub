<?php

namespace App\Commands;

use App\Helpers\AuthHelper;
use App\Models\InvoiceModel;
use App\Models\TenantModel;
use App\Services\InvoiceService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Meant to run daily via cron (shared hosting: cPanel/Rumahweb cron job
 * calling `php spark invoices:generate`). Safe to run more than once a day —
 * InvoiceService only generates an invoice once a customer's current
 * billing period has actually ended.
 */
class GenerateInvoices extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'invoices:generate';
    protected $description = 'Generates due invoices for every active tenant and flags overdue ones.';

    public function run(array $params)
    {
        $tenants      = (new TenantModel())->where('status', 'active')->findAll();
        $service      = new InvoiceService();
        $invoiceModel = new InvoiceModel();

        $totalGenerated = 0;
        $totalOverdue   = 0;

        foreach ($tenants as $tenant) {
            // No HTTP request/JWT in a CLI command, so the tenant context
            // that JwtAuthFilter would normally set has to be set by hand
            // here, once per tenant in the loop.
            AuthHelper::setContext([
                'userId'   => null,
                'tenantId' => (int) $tenant['id'],
                'roleId'   => null,
                'roleSlug' => 'system',
                'email'    => 'system@nethub',
            ]);

            $result       = $service->generateDueForTenant();
            $overdueCount = $invoiceModel->markOverdueForTenant((int) $tenant['id']);

            $totalGenerated += count($result['generated']);
            $totalOverdue   += $overdueCount;

            $skippedNote = count($result['skipped']) ? ', ' . count($result['skipped']) . ' skipped' : '';
            CLI::write(
                "Tenant {$tenant['slug']}: " . count($result['generated']) . " generated, {$overdueCount} marked overdue{$skippedNote}",
                'green'
            );
        }

        CLI::write(
            "Done. Total: {$totalGenerated} generated, {$totalOverdue} marked overdue across " . count($tenants) . ' tenant(s).',
            'yellow'
        );
    }
}
