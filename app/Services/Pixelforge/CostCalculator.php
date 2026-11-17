<?php

namespace App\Services\Pixelforge;

use App\Support\Money;

/**
 * Converts Pixelforge's token usage into the exact amount Pixelforge will bill
 * for a generation. Pixelforge never returns a price, so this is the only place
 * that turns usage + the generation's fixed rates into money.
 */
final class CostCalculator
{
    public const TOKEN_TYPES = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    /**
     * @param  array<string, int|string>  $usage
     * @param  array<string, string>  $rates  USD per 1M tokens, fixed at creation.
     */
    public static function providerCost(array $usage, array $rates): int
    {
        $cost = 0;

        foreach (self::TOKEN_TYPES as $type) {
            if (! array_key_exists($type, $rates)) {
                throw new \InvalidArgumentException("Missing Pixelforge rate for {$type}.");
            }

            $cost += Money::tokenCost((int) ($usage[$type] ?? 0), (string) $rates[$type]);
        }

        return $cost;
    }
}
