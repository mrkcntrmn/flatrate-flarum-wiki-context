<?php

namespace FlatRate\WikiContext\Support;

final class Uuid
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }
}
