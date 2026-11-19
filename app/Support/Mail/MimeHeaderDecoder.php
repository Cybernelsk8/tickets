<?php

namespace App\Support\Mail;

use Throwable;

final class MimeHeaderDecoder
{
    /**
     * Decode RFC 2047 encoded words without changing the surrounding text.
     */
    public static function decode(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Whitespace between adjacent encoded words is not part of the header value.
        $value = preg_replace(
            '/(=\?[^?\s]+\?[bBqQ]\?[^?]*\?=)\s+(?==\?)/',
            '$1',
            $value,
        ) ?? $value;

        $decoded = preg_replace_callback(
            '/=\?([^?\s]+)\?([bBqQ])\?([^?]*)\?=/',
            static function (array $matches): string {
                $token = $matches[0];

                $bytes = match (strtoupper($matches[2])) {
                    'B' => base64_decode($matches[3], true),
                    'Q' => quoted_printable_decode(str_replace('_', ' ', $matches[3])),
                };

                if ($bytes === false || $bytes === '') {
                    return $token;
                }

                if (in_array(strtoupper($matches[1]), ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
                    return $bytes;
                }

                try {
                    $converted = mb_convert_encoding($bytes, 'UTF-8', $matches[1]);
                } catch (Throwable) {
                    return $token;
                }

                return $converted === false ? $token : $converted;
            },
            $value,
        ) ?? $value;

        $decoded = trim($decoded);

        return $decoded === '' ? null : $decoded;
    }
}
