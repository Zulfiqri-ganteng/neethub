<?php

namespace App\Notifications;

/**
 * Placeholder only — there's no Flutter app yet to register a device token
 * against (that's Stage 8/9), so there's nothing real to send push
 * notifications to. Once the mobile app exists and registers FCM tokens,
 * replace this with a real FcmPushSender behind PushSenderInterface; the
 * queue/dispatch plumbing around it (NotificationDispatcher, retry logic)
 * won't need to change.
 */
class LogPushSender implements PushSenderInterface
{
    public function send(string $deviceToken, string $title, string $body): void
    {
        log_message('info', "[Push:LOG] to {$deviceToken} | {$title}: {$body}");
    }
}
