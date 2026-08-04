<?php

namespace App\Commands;

use App\Models\PaymentModel;
use App\Models\TenantModel;
use App\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * The spec's "retry mechanism" for the payment gateway: a webhook can be
 * delayed, dropped, or never delivered (network blip on either side). Runs
 * every ~10-15 min via cron, directly polls the gateway's own status API
 * for any `gateway` payment still `pending` after a grace period, so a
 * missed webhook doesn't leave an invoice stuck unpaid forever.
 */
class ReconcileGatewayPayments extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'payments:reconcile';
    protected $description = 'Re-checks pending gateway payments directly against Midtrans/Xendit in case a webhook was missed.';

    private const GRACE_MINUTES = 10;

    public function run(array $params)
    {
        $paymentModel = new PaymentModel();
        $tenantModel  = new TenantModel();

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::GRACE_MINUTES . ' minutes'));

        $pending = $paymentModel
            ->where('payment_method', 'gateway')
            ->where('status', 'pending')
            ->where('created_at <=', $cutoff)
            ->findAll();

        $updated = 0;
        $stillPending = 0;
        $errors = 0;

        foreach ($pending as $payment) {
            try {
                $tenant = $tenantModel->find($payment['tenant_id']);
                [$gateway, $config] = PaymentGatewayFactory::make($tenant);

                $result = $gateway->checkStatus($payment['gateway_reference'], $config);

                if ($result['status'] === 'pending') {
                    $stillPending++;
                    continue;
                }

                (new PaymentService())->confirmViaGateway($payment['gateway_reference'], $result['status'], $result['raw']);
                $updated++;
            } catch (Throwable $e) {
                $errors++;
                CLI::write("Payment #{$payment['id']}: {$e->getMessage()}", 'red');
            }
        }

        CLI::write("Diperbarui: {$updated}, masih pending: {$stillPending}, error: {$errors} (dari " . count($pending) . ' payment gateway pending)', 'green');
    }
}
