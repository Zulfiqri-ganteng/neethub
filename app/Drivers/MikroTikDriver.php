<?php

namespace App\Drivers;

use App\Drivers\Support\MikroTikApiClient;
use RuntimeException;

/**
 * Manages customers as PPPoE secrets (`/ppp/secret/*`), which matches the
 * `pppoe_username`/`pppoe_password` columns already on the `customers`
 * table. Bandwidth is enforced via the secret's own `rate-limit` field
 * rather than a separate Simple Queue — one less moving part. RouterOS only
 * reads that field when a session *connects*, though, so `updateUser()` and
 * `suspendUser()` both force a reconnect (see `kickActiveSession()`) rather
 * than leaving an already-active session on its old settings.
 */
class MikroTikDriver implements RouterDriverInterface
{
    private ?MikroTikApiClient $client = null;

    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $apiScheme = 'api'
    ) {
    }

    public function testConnection(): bool
    {
        try {
            $this->connected();

            return true;
        } finally {
            $this->disconnect();
        }
    }

    public function addUser(string $username, string $password, int $downloadMbps, int $uploadMbps): void
    {
        $this->upsertUser($username, $password, $downloadMbps, $uploadMbps, false);
    }

    public function upsertUser(string $username, string $password, ?int $downloadMbps, ?int $uploadMbps, bool $disabled): void
    {
        $words = [
            "=password={$password}",
            '=disabled=' . ($disabled ? 'yes' : 'no'),
        ];

        if ($downloadMbps !== null && $uploadMbps !== null) {
            // RouterOS rate-limit format is "rx-rate/tx-rate" from the
            // router's perspective, i.e. upload/download from the client's.
            $words[] = "=rate-limit={$uploadMbps}M/{$downloadMbps}M";
        }

        $existingId = $this->findSecretIdOrNull($username);

        if ($existingId === null) {
            $this->connected()->talk([
                '/ppp/secret/add',
                "=name={$username}",
                '=service=pppoe',
                '=profile=default',
                ...$words,
            ]);

            return;
        }

        // Adopting a secret that already existed (made by hand before
        // NetHub, or left behind by an earlier failed sync): keep its own
        // profile/service — a school/ISP may use custom PPP profiles — and
        // only overwrite what NetHub owns. Kick the live session so the new
        // password/speed/disabled state applies now, not at next redial.
        $this->connected()->talk(['/ppp/secret/set', "=.id={$existingId}", ...$words]);
        $this->kickActiveSession($username);
    }

    public function updateUser(string $username, ?string $password, ?int $downloadMbps, ?int $uploadMbps): void
    {
        $words = ['/ppp/secret/set', '=.id=' . $this->findSecretId($username)];

        if ($password !== null) {
            $words[] = "=password={$password}";
        }

        $speedChanged = $downloadMbps !== null && $uploadMbps !== null;
        if ($speedChanged) {
            $words[] = "=rate-limit={$uploadMbps}M/{$downloadMbps}M";
        }

        $this->connected()->talk($words);

        // RouterOS only applies a PPP secret's rate-limit at *connection
        // time* — an already-active session keeps its old speed until it
        // reconnects on its own, which for an idle customer could be hours
        // away. Kick the active session (same as suspendUser) so a package
        // upgrade/downgrade takes effect immediately instead of "eventually".
        if ($speedChanged) {
            $this->kickActiveSession($username);
        }
    }

    public function removeUser(string $username): void
    {
        $existingId = $this->findSecretIdOrNull($username);

        if ($existingId !== null) {
            $this->connected()->talk(['/ppp/secret/remove', "=.id={$existingId}"]);
        }

        // Removing a secret doesn't drop a session that's already up — a
        // deleted customer would otherwise keep free internet until their
        // modem happened to redial.
        $this->kickActiveSession($username);
    }

    public function suspendUser(string $username): void
    {
        $this->connected()->talk(['/ppp/secret/set', '=.id=' . $this->findSecretId($username), '=disabled=yes']);

        // Disabling the secret doesn't drop an already-connected session —
        // kick it explicitly so a suspension takes effect immediately.
        $this->kickActiveSession($username);
    }

    /**
     * Forces a PPPoE reconnect: the client's modem/router will
     * auto-redial within seconds and pick up whatever secret settings
     * (rate-limit, disabled) are current at that point.
     */
    private function kickActiveSession(string $username): void
    {
        $client = $this->connected();

        foreach ($client->talk(['/ppp/active/print', "?name={$username}"]) as $session) {
            if (isset($session['.id'])) {
                $client->talk(['/ppp/active/remove', '=.id=' . $session['.id']]);
            }
        }
    }

    public function enableUser(string $username): void
    {
        $this->connected()->talk(['/ppp/secret/set', '=.id=' . $this->findSecretId($username), '=disabled=no']);
    }

    public function getOnlineUsers(): array
    {
        return $this->connected()->talk(['/ppp/active/print']);
    }

    public function getTraffic(string $username): array
    {
        // RouterOS names the dynamic PPPoE interface after the secret.
        $result = $this->connected()->talk([
            '/interface/monitor-traffic',
            "=interface=<pppoe-{$username}>",
            '=once=',
        ]);

        return $result[0] ?? [];
    }

    public function reboot(): void
    {
        $this->connected()->talk(['/system/reboot']);
    }

    private function connected(): MikroTikApiClient
    {
        if ($this->client === null) {
            $client = new MikroTikApiClient($this->host, $this->port, useTls: $this->apiScheme === 'api-ssl');
            $client->connect();

            // A rejected login comes back as a !trap (thrown by talk()), not
            // as login() returning false — rethrow it with a message an owner
            // can act on, keeping RouterOS's own reason for the technician.
            try {
                $client->login($this->username, $this->password);
            } catch (RuntimeException $e) {
                throw new RuntimeException("Login ke router ditolak ({$e->getMessage()}). Periksa username/password API dan izin grup user-nya.");
            }

            $this->client = $client;
        }

        return $this->client;
    }

    private function disconnect(): void
    {
        $this->client?->close();
        $this->client = null;
    }

    private function findSecretId(string $username): string
    {
        return $this->findSecretIdOrNull($username)
            ?? throw new RuntimeException("User PPPoE '{$username}' tidak ditemukan di router.");
    }

    private function findSecretIdOrNull(string $username): ?string
    {
        $rows = $this->connected()->talk(['/ppp/secret/print', "?name={$username}"]);

        return $rows[0]['.id'] ?? null;
    }
}
