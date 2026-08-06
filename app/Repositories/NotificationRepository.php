<?php

namespace App\Repositories;

use App\Models\NotificationModel;

/**
 * Read side of the `notifications` queue, repurposed as an in-app feed for
 * whichever notifiable is asking (pelanggan first — see Flutter CLAUDE.md's
 * Priority 3 roadmap item, staff roles not wired up yet since nothing
 * queues a `notifiable_type = 'user'` row today). `read_at` is app-local —
 * independent of `status` (which tracks the WhatsApp/email/push delivery
 * attempt for that same row), so a failed WhatsApp send still shows up and
 * can still be marked read in-app.
 */
class NotificationRepository extends BaseRepository
{
    public function __construct(?NotificationModel $model = null)
    {
        parent::__construct($model ?? new NotificationModel(), 'notifications');
    }

    /**
     * @param array{unread_only?: bool} $filters
     */
    public function paginateForNotifiable(string $notifiableType, int $notifiableId, array $filters, int $page, int $perPage): array
    {
        $filters['notifiable_type'] = $notifiableType;
        $filters['notifiable_id']   = $notifiableId;

        return $this->paginateScoped($filters, $page, $perPage);
    }

    protected function applyFilters($builder, array $filters)
    {
        $builder = $builder
            ->where('notifications.notifiable_type', $filters['notifiable_type'])
            ->where('notifications.notifiable_id', $filters['notifiable_id']);

        if (! empty($filters['unread_only'])) {
            $builder = $builder->where('notifications.read_at', null);
        }

        return $builder;
    }

    public function unreadCount(string $notifiableType, int $notifiableId): int
    {
        return $this->scoped()
            ->where('notifications.notifiable_type', $notifiableType)
            ->where('notifications.notifiable_id', $notifiableId)
            ->where('notifications.read_at', null)
            ->countAllResults();
    }

    /**
     * Only marks the row read if it actually belongs to this notifiable —
     * same "verify ownership inside the scoped query" discipline as every
     * other tenant-scoped repository here, so one pelanggan can never mark
     * (or even discover the existence of) another's notification by id.
     */
    public function markRead(int $id, string $notifiableType, int $notifiableId): bool
    {
        $row = $this->scoped()
            ->where('notifications.id', $id)
            ->where('notifications.notifiable_type', $notifiableType)
            ->where('notifications.notifiable_id', $notifiableId)
            ->first();

        if ($row === null) {
            return false;
        }

        if ($row['read_at'] !== null) {
            return true;
        }

        return (bool) $this->model->update($id, ['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(string $notifiableType, int $notifiableId): int
    {
        $rows = $this->scoped()
            ->where('notifications.notifiable_type', $notifiableType)
            ->where('notifications.notifiable_id', $notifiableId)
            ->where('notifications.read_at', null)
            ->select('notifications.id')
            ->findAll();

        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $this->model->update($row['id'], ['read_at' => $now]);
        }

        return count($rows);
    }
}
