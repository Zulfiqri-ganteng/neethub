<?php

namespace App\Helpers;

/**
 * Indonesian phone numbers get typed/stored in every shape imaginable
 * (08xx, +628xx, 628xx, with spaces/dashes) — WhatsApp gateways uniformly
 * expect the bare "62..." form, so this is the one place that gets fixed.
 */
class PhoneHelper
{
    public static function toWhatsAppFormat(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }
}
