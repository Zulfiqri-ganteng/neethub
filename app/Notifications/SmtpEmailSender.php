<?php

namespace App\Notifications;

use Config\Services;
use RuntimeException;

/**
 * Thin wrapper over CI4's built-in Email service (Config\Email, configured
 * via .env's `email.*` keys) — standard framework functionality, not a
 * custom integration, so no external-provider guessing involved here.
 */
class SmtpEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $subject, string $htmlBody): void
    {
        $email = Services::email();

        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($htmlBody);

        if (! $email->send()) {
            throw new RuntimeException('Gagal mengirim email: ' . implode(' ', $email->printDebugger(['headers'])));
        }
    }
}
