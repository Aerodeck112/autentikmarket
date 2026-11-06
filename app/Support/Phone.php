<?php

namespace App\Support;

/**
 * Numere de telefon comparabile indiferent de cum au fost scrise:
 * „0740 123 456”, „+40 740-123-456”, „0040740123456” → „40740123456”.
 * Numerele maghiare (06…, +36…) → „36…”. Folosit la logare și la identificarea clientului.
 */
class Phone
{
    public static function key(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '06') && strlen($digits) === 11) {
            $digits = '36'.substr($digits, 2); // HU național: 06 30 123 4567
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '40'.substr($digits, 1); // RO național: 0740 123 456
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    /** Arată ca un număr de telefon (nu e-mail, nu cod de membru)? */
    public static function looksLikePhone(string $input): bool
    {
        return ! str_contains($input, '@') && preg_match('/^[\d\s+().\/-]{8,25}$/', trim($input)) === 1 && self::key($input) !== null;
    }

    /** Link WhatsApp „click to chat” (wa.me) cu mesaj precompletat; null fără număr valid. */
    public static function whatsappUrl(?string $phone, string $text = ''): ?string
    {
        $key = self::key($phone);

        return $key ? 'https://wa.me/'.$key.($text !== '' ? '?text='.rawurlencode($text) : '') : null;
    }
}
