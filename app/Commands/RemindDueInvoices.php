<?php

namespace App\Commands;

use App\Models\InvoiceModel;
use App\Models\NotificationModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Meant to run once daily via cron (`php spark invoices:remind`). Queues a
 * "H-3" reminder (per the spec) for any unpaid/overdue invoice due in
 * exactly 3 days. Dedupes against same-day reminders already queued for
 * that customer, so re-running the same day is harmless.
 */
class RemindDueInvoices extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'invoices:remind';
    protected $description = 'Queues an H-3 reminder notification for invoices due in 3 days.';

    public function run(array $params)
    {
        $targetDate = date('Y-m-d', strtotime('+3 days'));
        $todayStart = date('Y-m-d 00:00:00');

        $invoiceModel      = new InvoiceModel();
        $notificationModel = new NotificationModel();

        // Join + require customers.deleted_at IS NULL: an invoice can
        // outlive its customer (soft delete doesn't cascade), and there's
        // no point reminding someone who no longer exists as an active
        // customer.
        $invoices = $invoiceModel
            ->select('invoices.*')
            ->join('customers', 'customers.id = invoices.customer_id')
            ->whereIn('invoices.status', ['unpaid', 'overdue'])
            ->where('invoices.due_date', $targetDate)
            ->where('customers.deleted_at', null)
            ->findAll();

        $queued  = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            $alreadyQueuedToday = $notificationModel
                ->where('type', 'invoice_reminder')
                ->where('notifiable_type', 'customer')
                ->where('notifiable_id', $invoice['customer_id'])
                ->where('created_at >=', $todayStart)
                ->first();

            if ($alreadyQueuedToday) {
                $skipped++;
                continue;
            }

            $notificationModel->queue('whatsapp', 'invoice_reminder', 'customer', (int) $invoice['customer_id'], [
                'invoice_number' => $invoice['invoice_number'],
                'amount'         => $invoice['total_amount'],
                'due_date'       => $invoice['due_date'],
            ], (int) $invoice['tenant_id']);

            $queued++;
        }

        CLI::write("Reminder diantrikan: {$queued}, dilewati (sudah pernah hari ini): {$skipped}", 'green');
    }
}
