<?php

namespace App\Commands;

use App\Services\NotificationDispatcher;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Meant to run every 1-2 minutes via cron (`php spark notifications:send`).
 * Idempotent and safe to overlap — each row is claimed by being updated
 * (sent/failed/attempts incremented) as soon as it's processed.
 */
class SendNotifications extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'notifications:send';
    protected $description = 'Processes the pending notification queue (WhatsApp/Email/Push).';

    public function run(array $params)
    {
        $result = (new NotificationDispatcher())->processPending(100);

        CLI::write("Sent: {$result['sent']}, retrying: {$result['retrying']}, failed: {$result['failed']}", 'green');
    }
}
