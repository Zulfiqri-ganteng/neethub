<?php

namespace App\Notifications;

interface EmailSenderInterface
{
    /**
     * @throws \RuntimeException on send failure — caller decides retry/fail.
     */
    public function send(string $to, string $subject, string $htmlBody): void;
}
