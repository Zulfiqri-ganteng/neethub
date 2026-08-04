<?php

namespace App\Notifications;

use Config\Services;
use RuntimeException;

/**
 * Config comes from `platform_settings` (Super Admin-editable, Stage 13
 * follow-up), not `.env`/Config\Email — the platform operator's own mail
 * account, configurable at runtime without a redeploy, same reasoning as
 * the rest of platform_settings.
 */
class SmtpEmailSender implements EmailSenderInterface
{
    /**
     * @param array{smtp_host: string, smtp_port: int, smtp_user: ?string, smtp_pass: ?string,
     *              smtp_crypto: ?string, smtp_from_email: ?string, smtp_from_name: ?string} $settings
     *              smtp_pass must already be decrypted.
     */
    public function __construct(private array $settings)
    {
    }

    public function send(string $to, string $subject, string $htmlBody): void
    {
        $email = Services::email([
            'protocol'   => 'smtp',
            'SMTPHost'   => $this->settings['smtp_host'],
            'SMTPPort'   => (int) ($this->settings['smtp_port'] ?? 587),
            'SMTPUser'   => $this->settings['smtp_user'] ?? '',
            'SMTPPass'   => $this->settings['smtp_pass'] ?? '',
            'SMTPCrypto' => $this->settings['smtp_crypto'] ?? 'tls',
            'mailType'   => 'html',
        ], false);

        $fromEmail = $this->settings['smtp_from_email'] ?: ($this->settings['smtp_user'] ?? 'no-reply@nethub');
        $fromName  = $this->settings['smtp_from_name'] ?: 'NetHub';

        $email->setFrom($fromEmail, $fromName);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($htmlBody);

        if (! $email->send()) {
            // printDebugger() already returns a string (built internally
            // via its own implode()) — wrapping it in another implode() here
            // was a pre-existing bug, throws a TypeError in PHP 8+ since
            // implode()'s 2nd arg must be an array. Never triggered before
            // because SmtpEmailSender was never actually invoked until this
            // session (SMTP was never configured anywhere before Stage 13).
            throw new RuntimeException('Gagal mengirim email: ' . $email->printDebugger(['headers']));
        }
    }
}
