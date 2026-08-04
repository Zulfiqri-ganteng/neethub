<?php

namespace App\Notifications;

interface WhatsAppSenderInterface
{
    /**
     * @throws \RuntimeException on send failure — caller decides retry/fail.
     */
    public function send(string $phone, string $message): void;
}
