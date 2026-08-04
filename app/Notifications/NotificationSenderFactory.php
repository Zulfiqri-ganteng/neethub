<?php

namespace App\Notifications;

use Config\Email as EmailConfig;
use RuntimeException;

class NotificationSenderFactory
{
    /**
     * @param ?string $whatsappToken the tenant's own decrypted Fonnte
     *                                token (each tenant connects their own
     *                                WhatsApp number) — falls back to
     *                                logging when the tenant hasn't
     *                                configured one yet.
     */
    public static function whatsapp(?string $whatsappToken): WhatsAppSenderInterface
    {
        return ! empty($whatsappToken)
            ? new FonnteWhatsAppSender($whatsappToken)
            : new LogWhatsAppSender();
    }

    public static function email(): EmailSenderInterface
    {
        return (new EmailConfig())->SMTPHost !== ''
            ? new SmtpEmailSender()
            : new LogEmailSender();
    }

    public static function push(): PushSenderInterface
    {
        return new LogPushSender();
    }

    public static function forChannel(string $channel, ?string $whatsappToken = null): WhatsAppSenderInterface|EmailSenderInterface|PushSenderInterface
    {
        return match ($channel) {
            'whatsapp' => self::whatsapp($whatsappToken),
            'email'    => self::email(),
            'push'     => self::push(),
            default    => throw new RuntimeException("Channel notifikasi '{$channel}' tidak dikenal."),
        };
    }
}
