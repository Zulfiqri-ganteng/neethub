<?php

namespace App\Notifications;

/**
 * Used when no SMTP host is configured (Config\Email::$SMTPHost empty) —
 * logs instead of letting every retry burn time on an SMTP connection
 * that was never going to succeed.
 */
class LogEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $subject, string $htmlBody): void
    {
        log_message('info', "[Email:LOG] to {$to} | subject: {$subject} | body: {$htmlBody}");
    }
}
