<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * Tolerant field extraction for model hydration. Every getter coerces
 * defensively and falls back to a safe default, so a missing or unexpectedly
 * typed field never throws while parsing a response.
 *
 * @internal
 */
final class Field
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function str(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nstr(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nint(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? null;
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * A JSON object field (e.g. `metadata`), normalized to a string-keyed array.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * A JSON array-of-objects field, filtered to the array rows.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * A JSON array-of-strings field.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function strList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * A JSON array-of-integers field.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<int>
     */
    public static function intList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $out[] = $item;
            } elseif (is_numeric($item)) {
                $out[] = (int) $item;
            }
        }

        return $out;
    }
}
