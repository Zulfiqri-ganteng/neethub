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
        private string $password
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
        $this->connected()->talk([
            '/ppp/secret/add',
            "=name={$username}",
            "=password={$password}",
            '=service=pppoe',
            '=profile=default',
            // RouterOS rate-limit format is "rx-rate/tx-rate" from the
            // router's perspective, i.e. upload/download from the client's.
            "=rate-limit={$uploadMbps}M/{$downloadMbps}M",
        ]);
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
        $this->connected()->talk(['/ppp/secret/remove', '=.id=' . $this->findSecretId($username)]);
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
            $client = new MikroTikApiClient($this->host, $this->port);
            $client->connect();

            if (! $client->login($this->username, $this->password)) {
                throw new RuntimeException('Autentikasi ke router gagal. Periksa username/password.');
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
        $rows = $this->connected()->talk(['/ppp/secret/print', "?name={$username}"]);

        if (empty($rows) || ! isset($rows[0]['.id'])) {
            throw new RuntimeException("User PPPoE '{$username}' tidak ditemukan di router.");
        }

        return $rows[0]['.id'];
    }
}
