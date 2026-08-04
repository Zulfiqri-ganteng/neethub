<?php

namespace App\Notifications;

interface PushSenderInterface
{
    /**
     * @throws \RuntimeException on send failure — caller decides retry/fail.
     */
    public function send(string $deviceToken, string $title, string $body): void;
}
