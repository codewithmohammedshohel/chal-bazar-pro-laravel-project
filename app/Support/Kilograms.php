<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact kilogram arithmetic.
 *
 * Stock columns are decimal(12,4), i.e. one unit = 0.0001 kg. Converting to
 * integer units before adding/subtracting/comparing avoids the accumulation
 * and boundary errors that come with binary floating point.
 */
final class Kilograms
{
    public const SCALE = 10000;

    /**
     * @throws InvalidArgumentException when the value is not a plain decimal
     *                                  with at most four fractional digits.
     */
    public static function toUnits(int|float|string|null $value): int
    {
        if ($value === null) {
            throw new InvalidArgumentException('Kilogram value is missing.');
        }

        if (is_int($value)) {
            return $value * self::SCALE;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Kilogram value is not finite.');
            }

            $value = number_format($value, 4, '.', '');
        }

        $value = trim($value);

        if ($value === '' || preg_match('/^(-?)(\d+)(?:\.(\d{1,4}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid kilogram value: {$value}");
        }

        $units = ((int) $matches[2]) * self::SCALE
            + (int) str_pad($matches[3] ?? '', 4, '0');

        return $matches[1] === '-' ? -$units : $units;
    }

    public static function fromUnits(int $units): string
    {
        $sign = $units < 0 ? '-' : '';
        $absolute = abs($units);

        return $sign.intdiv($absolute, self::SCALE).'.'.str_pad(
            (string) ($absolute % self::SCALE),
            4,
            '0',
            STR_PAD_LEFT,
        );
    }

    public static function toFloat(int $units): float
    {
        return $units / self::SCALE;
    }

    /**
     * Human readable label without trailing zeroes, e.g. "1.5", "25", "0.0001".
     */
    public static function format(int $units): string
    {
        $formatted = rtrim(rtrim(self::fromUnits($units), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    public static function formatKg(int $units): string
    {
        return self::format($units).' kg';
    }
}
