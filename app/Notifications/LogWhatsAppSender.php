<?php

namespace App\Notifications;

/**
 * Used when no WhatsApp gateway is configured (see Config\WhatsApp) — logs
 * what would have been sent instead of failing every attempt, so the rest
 * of the notification pipeline (queueing, retry, dispatch) stays testable
 * without real gateway credentials.
 */
class LogWhatsAppSender implements WhatsAppSenderInterface
{
    public function send(string $phone, string $message): void
    {
        log_message('info', "[WhatsApp:LOG] to {$phone}: {$message}");
    }
}
