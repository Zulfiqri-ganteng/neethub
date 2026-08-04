<?php

namespace App\Services;

use RuntimeException;

/**
 * Converts a tenant's static EMV QRIS payload (the one their PSP/bank gave
 * them when they registered as a QRIS merchant) into a per-invoice dynamic
 * payload with the exact amount embedded — a standard, well-documented EMV
 * QR Code technique (set tag 54 = amount, tag 01 = "12" dynamic, recompute
 * the tag-63 CRC16 checksum over everything before it).
 *
 * NetHub does not integrate with a payment gateway/PSP itself — it only
 * reformats a QRIS code the tenant already owns. Payment confirmation is
 * still manual (see PaymentService), same as bank transfer.
 *
 * NOTE: implemented against the documented EMVCo QR Code spec and
 * cross-checked by hand, but not validated against a real bank/e-wallet
 * scanner in this environment. A bug here fails safe (the QR just won't
 * scan) rather than silently wrong — but validate a generated code with a
 * real scanning app before relying on it in production.
 */
class QrisService
{
    private const TAG_POINT_OF_INITIATION = '01';
    private const TAG_TRANSACTION_AMOUNT  = '54';
    private const TAG_CRC                 = '63';

    /**
     * @throws RuntimeException on a malformed static payload.
     */
    public function generateDynamicPayload(string $staticPayload, float $amount): string
    {
        $tags = $this->parse($staticPayload);

        if ($tags === []) {
            throw new RuntimeException('QRIS statis tidak valid — tidak dapat dibaca.');
        }

        // Static (11) -> dynamic (12) point-of-initiation, per EMVCo convention.
        if (isset($tags[self::TAG_POINT_OF_INITIATION])) {
            $tags[self::TAG_POINT_OF_INITIATION] = '12';
        }

        unset($tags[self::TAG_CRC]);
        $tags[self::TAG_TRANSACTION_AMOUNT] = number_format($amount, 2, '.', '');

        // Tag 63 (CRC) must be last, and must not be present while the CRC
        // over the preceding bytes is calculated.
        $body = $this->serialize($tags);
        $withCrcPlaceholder = $body . self::TAG_CRC . '04';
        $crc = strtoupper(str_pad(dechex($this->crc16($withCrcPlaceholder)), 4, '0', STR_PAD_LEFT));

        return $withCrcPlaceholder . $crc;
    }

    /**
     * @return array<string, string> tag => value, insertion order preserved
     */
    private function parse(string $payload): array
    {
        $tags   = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                throw new RuntimeException('QRIS statis terpotong atau rusak.');
            }

            $tag    = substr($payload, $offset, 2);
            $valLen = (int) substr($payload, $offset + 2, 2);
            $value  = substr($payload, $offset + 4, $valLen);

            if (strlen($value) !== $valLen) {
                throw new RuntimeException('QRIS statis terpotong atau rusak.');
            }

            $tags[$tag] = $value;
            $offset += 4 + $valLen;
        }

        return $tags;
    }

    /**
     * @param array<string, string> $tags
     */
    private function serialize(array $tags): string
    {
        $out = '';

        foreach ($tags as $tag => $value) {
            $out .= $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
        }

        return $out;
    }

    /**
     * CRC-16/CCITT-FALSE — the variant EMVCo specifies for QR Code payloads:
     * poly 0x1021, init 0xFFFF, no input/output reflection, no final XOR.
     */
    private function crc16(string $data): int
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $crc;
    }
}
