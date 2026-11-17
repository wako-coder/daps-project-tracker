<?php

namespace App\Support;

/**
 * Ethiopian Birr amounts are stored as whole santim (1 ETB = 100 santim) in
 * bigint columns, so sums and comparisons never touch floating point.
 */
final class Money
{
    /** What a user may type: digits with at most two decimals, e.g. "1500" or "1500.50". */
    public const PATTERN = '/^\d{1,12}(\.\d{1,2})?$/';

    /** Convert validated user input ("1500.5") to santim (150050) without floats. */
    public static function toSantim(string $birr): int
    {
        [$whole, $fraction] = array_pad(explode('.', $birr, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    /** Santim (150050) to a display string ("ETB 1,500.50"). */
    public static function format(int $santim): string
    {
        $absolute = abs($santim);

        return sprintf(
            '%sETB %s.%02d',
            $santim < 0 ? '-' : '',
            number_format(intdiv($absolute, 100)),
            $absolute % 100,
        );
    }

    /** Santim (150050) to a form field value ("1500.50"). */
    public static function toInput(int $santim): string
    {
        return sprintf('%d.%02d', intdiv($santim, 100), $santim % 100);
    }
}
