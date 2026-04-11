<?php

namespace App\Support;

class ReferenceNormalizer
{
    public static function normalize(?string $value, string $strategy = 'uppercase_compact'): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return match ($strategy) {
            'trim' => $value,
            default => strtoupper((string) preg_replace('/\s+/', '', $value)),
        };
    }
}
