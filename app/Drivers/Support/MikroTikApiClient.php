<?php

namespace App\Drivers\Support;

use RuntimeException;

/**
 * Minimal MikroTik RouterOS API protocol client, implemented from the
 * documented binary protocol (see https://wiki.mikrotik.com/wiki/Manual:API)
 * rather than pulling in a third-party package — the protocol is a plain
 * length-prefixed word stream over a TCP socket, not HTTP, so no existing
 * HTTP client helps here.
 *
 * Assumes RouterOS >= 6.43's single-step plain-text login (`/login
 * =name=... =password=...`). Older RouterOS versions used a two-step MD5
 * challenge-response login instead — not implemented, since 6.43+ (released
 * 2018) is the realistic baseline for anything still getting security
 * updates today.
 *
 * NOTE: this has been written and reviewed against the protocol spec, but
 * has NOT been exercised against a real or virtual MikroTik device in this
 * environment (no hardware available here) — test against a real router
 * (or a MikroTik CHR VM) before relying on it in production.
 *
 * Supports both the plain (`api`, port 8728) and TLS (`api-ssl`, port 8729)
 * transports — same binary word protocol either way, just wrapped in a TLS
 * stream for the latter. RouterOS's api-ssl service uses a self-signed
 * certificate out of the box unless the admin has installed a real one, so
 * peer verification is deliberately off here (matches how other published
 * RouterOS API clients handle this) — this is about encrypting the
 * credentials/commands in transit, not about verifying router identity.
 */
class MikroTikApiClient
{
    /** @var resource|null */
    private $socket;

    public function __construct(
        private string $host,
        private int $port = 8728,
        private int $timeoutSeconds = 5,
        private bool $useTls = false
    ) {
    }

    public function connect(): void
    {
        if ($this->useTls) {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                $this->timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeoutSeconds);
        }

        if ($socket === false) {
            throw new RuntimeException("Tidak dapat terhubung ke router {$this->host}:{$this->port} ({$errstr}).");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);
        $this->socket = $socket;
    }

    public function login(string $username, string $password): bool
    {
        $this->talk(['/login', "=name={$username}", "=password={$password}"]);

        return true;
    }

    /**
     * Sends one API "sentence" (command + attribute words) and reads every
     * reply sentence until the router signals !done. Returns the parsed
     * data rows from any !re replies; throws on a router-side !trap/!fatal.
     *
     * @return array<int, array<string, string>>
     */
    public function talk(array $words): array
    {
        if ($this->socket === null) {
            throw new RuntimeException('Belum terhubung ke router.');
        }

        $this->writeSentence($words);

        $rows = [];

        while (true) {
            $sentence = $this->readSentence();

            if ($sentence === []) {
                continue;
            }

            $type = array_shift($sentence);

            if ($type === '!trap' || $type === '!fatal') {
                // RouterOS follows a !trap with its own !done, which we
                // never read here — closing the socket guarantees a caller
                // that catches this can't reuse a connection whose next
                // read would return that stale !done as the reply to a
                // different command.
                $this->close();

                throw new RuntimeException($this->extractAttribute($sentence, 'message') ?? 'Perintah router gagal.');
            }

            if ($type === '!re') {
                $rows[] = $this->parseAttributes($sentence);
            }

            if ($type === '!done') {
                break;
            }
        }

        return $rows;
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord($word);
        }
        $this->writeWord('');
    }

    private function writeWord(string $word): void
    {
        fwrite($this->socket, $this->encodeLength(strlen($word)) . $word);
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            $length |= 0x8000;

            return chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        if ($length < 0x200000) {
            $length |= 0xC00000;

            return chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        if ($length < 0x10000000) {
            $length |= 0xE0000000;

            return chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        return chr(0xF0)
            . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF)
            . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
    }

    /**
     * @return string[]
     */
    private function readSentence(): array
    {
        $words = [];

        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }
            $words[] = $word;
        }

        return $words;
    }

    private function readWord(): string
    {
        $length = $this->readLength();

        return $length > 0 ? $this->readBytes($length) : '';
    }

    private function readLength(): int
    {
        $firstByte = ord($this->readBytes(1));

        if ($firstByte < 0x80) {
            return $firstByte;
        }

        if (($firstByte & 0xC0) === 0x80) {
            $rest = $this->readBytes(1);

            return (($firstByte & 0x3F) << 8) | ord($rest);
        }

        if (($firstByte & 0xE0) === 0xC0) {
            $rest = $this->readBytes(2);

            return (($firstByte & 0x1F) << 16) | (ord($rest[0]) << 8) | ord($rest[1]);
        }

        if (($firstByte & 0xF0) === 0xE0) {
            $rest = $this->readBytes(3);

            return (($firstByte & 0x0F) << 24) | (ord($rest[0]) << 16) | (ord($rest[1]) << 8) | ord($rest[2]);
        }

        $rest = $this->readBytes(4);

        return (ord($rest[0]) << 24) | (ord($rest[1]) << 16) | (ord($rest[2]) << 8) | ord($rest[3]);
    }

    private function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Koneksi ke router terputus saat membaca respons.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @param string[] $words
     *
     * @return array<string, string>
     */
    private function parseAttributes(array $words): array
    {
        $attributes = [];

        foreach ($words as $word) {
            if (str_starts_with($word, '=')) {
                [$key, $value] = explode('=', substr($word, 1), 2) + [1 => ''];
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @param string[] $words
     */
    private function extractAttribute(array $words, string $key): ?string
    {
        return $this->parseAttributes($words)[$key] ?? null;
    }
}
