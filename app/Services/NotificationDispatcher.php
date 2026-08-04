<?php

namespace App\Services;

use App\Helpers\CryptoHelper;
use App\Models\CustomerModel;
use App\Models\NotificationModel;
use App\Models\TenantModel;
use App\Models\UserModel;
use App\Notifications\NotificationSenderFactory;
use Throwable;

/**
 * Processes the `notifications` queue. A row stays `pending` and gets
 * retried on the next run until it either succeeds or exhausts
 * MAX_ATTEMPTS, at which point it's marked `failed` for good — "queue-based
 * with retry, never blocking the request" per the spec.
 */
class NotificationDispatcher
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private NotificationModel $notifications = new NotificationModel(),
        private NotificationTemplates $templates = new NotificationTemplates()
    ) {
    }

    /**
     * @return array{sent: int, failed: int, retrying: int}
     */
    public function processPending(int $limit = 50): array
    {
        $rows = $this->notifications
            ->where('status', 'pending')
            ->where('attempts <', self::MAX_ATTEMPTS)
            ->orderBy('id', 'ASC')
            ->findAll($limit);

        $counts = ['sent' => 0, 'failed' => 0, 'retrying' => 0];

        foreach ($rows as $row) {
            $counts[$this->dispatchOne($row)]++;
        }

        return $counts;
    }

    private function dispatchOne(array $notification): string
    {
        $recipient = $this->resolveRecipient($notification);

        if ($recipient === null) {
            $this->notifications->update($notification['id'], [
                'status'     => 'failed',
                'attempts'   => $notification['attempts'] + 1,
                'last_error' => 'Tidak ada kontak (telepon/email) untuk penerima ini.',
            ]);

            return 'failed';
        }

        try {
            $rendered = $this->templates->render($notification);
            $sender   = NotificationSenderFactory::forChannel(
                $notification['channel'],
                $notification['channel'] === 'whatsapp' ? $this->resolveWhatsappToken($notification['tenant_id']) : null
            );

            if ($notification['channel'] === 'whatsapp') {
                $sender->send($recipient, $rendered['message']);
            } else {
                $sender->send($recipient, $rendered['subject'], $rendered['message']);
            }

            $updates = [
                'status'     => 'sent',
                'attempts'   => $notification['attempts'] + 1,
                'sent_at'    => date('Y-m-d H:i:s'),
                'last_error' => null,
            ];

            // account_created carries a plaintext one-time password so the
            // template can render it — once actually delivered, there's no
            // reason for it to keep sitting in the DB in plaintext forever.
            // (password_reset used to go through this same queue and had
            // the equivalent fix here, but it now sends synchronously from
            // AuthController::forgotPassword and never reaches this queue
            // at all — see Stage 13's CLAUDE.md notes.)
            if ($notification['type'] === 'account_created') {
                $redactedPayload = json_decode($notification['payload'], true) ?? [];
                $redactedPayload['password'] = '[redacted-after-send]';
                $updates['payload'] = json_encode($redactedPayload);
            }

            $this->notifications->update($notification['id'], $updates);

            return 'sent';
        } catch (Throwable $e) {
            $attempts = $notification['attempts'] + 1;
            $status   = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';

            $this->notifications->update($notification['id'], [
                'status'     => $status,
                'attempts'   => $attempts,
                'last_error' => $e->getMessage(),
            ]);

            return $status === 'failed' ? 'failed' : 'retrying';
        }
    }

    private function resolveRecipient(array $notification): ?string
    {
        if ($notification['channel'] === 'push') {
            // No device-token storage yet — real FCM wiring lands with the
            // Flutter stage. See LogPushSender.
            return 'device-token-placeholder';
        }

        $record = $notification['notifiable_type'] === 'customer'
            ? (new CustomerModel())->find($notification['notifiable_id'])
            : (new UserModel())->find($notification['notifiable_id']);

        if ($record === null) {
            return null;
        }

        if ($notification['channel'] === 'email') {
            return $record['email'] ?? null;
        }

        return $record['phone'] ?? $record['whatsapp'] ?? null;
    }

    /**
     * Each tenant connects their own WhatsApp number via Fonnte — never a
     * shared NetHub-wide credential (same pattern as router/payment-gateway
     * credentials). Returns null (falls back to logging) if the tenant
     * hasn't configured one, or the notification has no tenant at all.
     */
    private function resolveWhatsappToken(?int $tenantId): ?string
    {
        if ($tenantId === null) {
            return null;
        }

        $tenant = (new TenantModel())->find($tenantId);

        return CryptoHelper::decrypt($tenant['whatsapp_token'] ?? null);
    }
}
