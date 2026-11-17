<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money helpers.
 *
 * Pixelforge prices individual tokens at fractions of a cent, and its usage
 * report is exact to nine decimal places. Floats cannot represent those values
 * reliably, so every amount in this application is an integer number of
 * picodollars (1 USD = 1,000,000,000,000). That is three orders of magnitude
 * more precise than Pixelforge's nine-decimal output, which lets us apply the
 * 25% markup without losing anything and still report an exact cost.
 */
final class Money
{
    public const PICO_PER_USD = 1_000_000_000_000;

    private const PICO_PER_MILLION = 1_000_000;

    /**
     * Convert an exact USD decimal string (e.g. "0.046125000") to picodollars.
     */
    public static function fromUsd(string $usd): int
    {
        $usd = trim($usd);

        if (! is_numeric($usd)) {
            throw new InvalidArgumentException("Invalid USD amount [{$usd}].");
        }

        // bcmath multiplies the decimal string exactly; scale 0 truncates any
        // sub-picodollar remainder, which Pixelforge's nine decimals never have.
        return (int) bcmul($usd, (string) self::PICO_PER_USD, 0);
    }

    /**
     * Cost of N tokens at a published rate in USD per 1M tokens.
     */
    public static function tokenCost(int $tokens, string $ratePerMillion): int
    {
        if ($tokens === 0) {
            return 0;
        }

        $picoPerToken = bcdiv((string) self::fromUsd($ratePerMillion), (string) self::PICO_PER_MILLION, 12);
        $raw = bcmul((string) $tokens, $picoPerToken, 12);

        // Half-up rounding, only relevant if Pixelforge ever publishes rates
        // with more than six decimal places.
        return (int) bcadd($raw, '0.5', 0);
    }

    /**
     * Apply our markup. Rates are stored in basis points (25% = 2500).
     */
    public static function withMarkup(int $picodollars, ?int $basisPoints = null): int
    {
        $basisPoints ??= (int) config('services.pixelforge.markup_basis_points', 2500);

        return intdiv($picodollars * (10_000 + $basisPoints) + 5_000, 10_000);
    }

    /**
     * Exact decimal USD string, without a currency symbol.
     */
    public static function decimal(int $picodollars, int $scale = 9): string
    {
        $value = bcdiv((string) $picodollars, (string) self::PICO_PER_USD, $scale);

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }

    /**
     * Human-friendly USD string. We keep at least two decimals and trim the
     * rest so tiny token costs remain readable.
     */
    public static function format(int $picodollars, int $decimals = 6): string
    {
        $negative = $picodollars < 0;
        $value = bcdiv((string) abs($picodollars), (string) self::PICO_PER_USD, $decimals);
        $formatted = number_format((float) $value, $decimals, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        if (! str_contains($formatted, '.')) {
            $formatted .= '.00';
        } elseif (strlen(explode('.', $formatted)[1]) === 1) {
            $formatted .= '0';
        }

        return ($negative ? '-$' : '$').$formatted;
    }
}
