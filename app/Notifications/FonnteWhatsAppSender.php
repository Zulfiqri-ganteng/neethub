<?php

namespace App\Notifications;

use App\Helpers\PhoneHelper;
use Config\Services;
use RuntimeException;

/**
 * Fonnte (https://fonnte.com) is one of the most commonly used unofficial
 * WhatsApp gateway services among small Indonesian SaaS products in this
 * exact niche (RT/RW Net billing tools) — chosen as the concrete default
 * over inventing a generic/fictional provider. Its API is a simple HTTP
 * POST: `Authorization: <device token>` header, `target`/`message` body.
 *
 * The token is per-tenant (each ISP owner connects their OWN WhatsApp
 * number via Fonnte's QR-scan pairing, same as their own router/payment-
 * gateway credentials) — never a shared NetHub-wide credential, which is
 * why it's passed in rather than read from a global config.
 *
 * NOTE: implemented against Fonnte's publicly documented API shape from
 * memory, not verified against a live account/token in this environment —
 * there is no real WhatsApp gateway credential available here to test
 * with. Verify against a real Fonnte token before relying on this in
 * production; if the response shape differs, only this class should need
 * to change (NotificationDispatcher only cares about send() throwing or not).
 */
class FonnteWhatsAppSender implements WhatsAppSenderInterface
{
    private const API_URL = 'https://api.fonnte.com/send';

    public function __construct(private string $token)
    {
    }

    public function send(string $phone, string $message): void
    {
        $client = Services::curlrequest();

        $response = $client->post(self::API_URL, [
            'headers' => ['Authorization' => $this->token],
            'form'    => [
                'target'  => PhoneHelper::toWhatsAppFormat($phone),
                'message' => $message,
            ],
            'http_errors' => false,
        ]);

        $body = json_decode($response->getBody(), true);

        if ($response->getStatusCode() >= 300 || ($body['status'] ?? null) === false) {
            throw new RuntimeException('Fonnte gagal mengirim pesan: ' . ($body['reason'] ?? $response->getBody()));
        }
    }
}
