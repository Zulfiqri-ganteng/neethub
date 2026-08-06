<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The outbound notification queue. Rows are inserted here by whatever
 * feature triggers a notification (payment confirmed, invoice due soon,
 * etc.) — actually SENDING them (WhatsApp/email/push) is the Notifications
 * stage's worker, not built yet. Queuing now means nothing needs to be
 * retrofitted once that worker exists.
 */
class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'tenant_id', 'notifiable_type', 'notifiable_id', 'channel', 'type', 'payload',
        'status', 'attempts', 'last_error', 'sent_at', 'read_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function queue(string $channel, string $type, string $notifiableType, int $notifiableId, array $payload, ?int $tenantId): void
    {
        $this->insert([
            'tenant_id'       => $tenantId,
            'notifiable_type' => $notifiableType,
            'notifiable_id'   => $notifiableId,
            'channel'         => $channel,
            'type'            => $type,
            'payload'         => json_encode($payload),
            'status'          => 'pending',
        ]);
    }
}
