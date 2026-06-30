<?php

namespace App\Support;

class EmailInputNormalizer
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = trim($value);

        if ($email === '') {
            return null;
        }

        // Normalize common Turkish dotted/dotless i variants that can appear
        // when users copy addresses from rich text or type with a TR layout.
        $email = str_replace(["\u{0130}", "\u{0131}", "\u{0307}"], ['I', 'i', ''], $email);

        if (function_exists('mb_strtolower')) {
            $email = mb_strtolower($email, 'UTF-8');
        } else {
            $email = strtolower($email);
        }

        return $email === '' ? null : $email;
    }
}
