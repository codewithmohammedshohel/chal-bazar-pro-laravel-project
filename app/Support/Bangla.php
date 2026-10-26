<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Bengali (Bangla) digit helpers for server-rendered documents.
 *
 * The shop is BDT-only, so amounts are always rendered as
 * "৳ ১,২৩৪.৫০" with Bengali digits and two decimal places.
 */
final class Bangla
{
    /** @var array<string, string> */
    private const DIGITS = [
        '0' => '০',
        '1' => '১',
        '2' => '২',
        '3' => '৩',
        '4' => '৪',
        '5' => '৫',
        '6' => '৬',
        '7' => '৭',
        '8' => '৮',
        '9' => '৯',
    ];

    public const TAKA = '৳';

    /**
     * Transliterate every ASCII digit in the value to its Bengali
     * counterpart. All other characters are preserved.
     */
    public static function digits(string|int|float $value): string
    {
        return strtr((string) $value, self::DIGITS);
    }

    /**
     * Grouped amount without the currency symbol, e.g. "১,২৩৪.৫০".
     */
    public static function number(string|int|float|null $value, int $decimals = 2): string
    {
        return self::digits(number_format((float) $value, $decimals));
    }

    /**
     * Amount with the BDT symbol, e.g. "৳ ১,২৩৪.৫০".
     */
    public static function money(string|int|float|null $value): string
    {
        return self::TAKA.' '.self::number($value);
    }

    /**
     * Calendar date in Bengali digits, e.g. "২৬/১০/২০২৬".
     */
    public static function date(?DateTimeInterface $date, string $format = 'd/m/Y'): string
    {
        return $date === null ? '' : self::digits($date->format($format));
    }
}
