<?php

namespace App\Services\Pixelforge;

use App\Support\Money;
use InvalidArgumentException;

class TokenEstimator
{
    /**
     * @param  array<string, mixed>  $pricing  Response from GET /v1/pricing.
     */
    public function estimate(
        array $pricing,
        string $prompt,
        string $size,
        string $quality,
        int $referenceImages,
    ): CostEstimate {
        $tokens = $pricing['tokens'] ?? [];
        $rates = $pricing['rates'] ?? [];

        $charactersPerToken = max(1, (int) ($tokens['characters_per_text_token'] ?? 4));
        $variance = (float) ($tokens['text_token_variance'] ?? 0.15);
        $perReferenceImage = (int) ($tokens['per_reference_image'] ?? 765);

        $outputTokens = $tokens['output'][$quality][$size] ?? null;
        $reasoningMin = $tokens['reasoning'][$quality]['min'] ?? null;
        $reasoningMax = $tokens['reasoning'][$quality]['max'] ?? null;

        if ($outputTokens === null || $reasoningMin === null || $reasoningMax === null) {
            throw new InvalidArgumentException("Pixelforge pricing does not cover {$quality} at {$size}.");
        }

        $characters = mb_strlen($prompt);
        $textTokens = (int) ceil($characters / $charactersPerToken);
        // The documented worst case: text tokens up to +15% of the estimate,
        // reasoning tokens at the top of the quality band, exact output and
        // reference-image counts.
        $maxTextTokens = (int) ceil($textTokens * (1 + $variance));
        $imageTokens = $referenceImages * $perReferenceImage;
        $reasoningTokens = intdiv($reasoningMin + $reasoningMax, 2);

        $providerCost = $this->providerCost(
            $textTokens,
            $imageTokens,
            $outputTokens,
            $reasoningTokens,
            $rates,
        );

        $maxProviderCost = $this->providerCost(
            $maxTextTokens,
            $imageTokens,
            $outputTokens,
            $reasoningMax,
            $rates,
        );

        $markup = (int) config('services.pixelforge.markup_basis_points', 2500);

        return new CostEstimate(
            characters: $characters,
            textTokens: $textTokens,
            maxTextTokens: $maxTextTokens,
            imageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningTokens: $reasoningTokens,
            maxReasoningTokens: $reasoningMax,
            providerCost: $providerCost,
            maxProviderCost: $maxProviderCost,
            charge: Money::withMarkup($providerCost, $markup),
            maxCharge: Money::withMarkup($maxProviderCost, $markup),
            rates: $rates,
            markupBasisPoints: $markup,
        );
    }

    /**
     * @param  array<string, string>  $rates
     */
    private function providerCost(
        int $textTokens,
        int $imageTokens,
        int $outputTokens,
        int $reasoningTokens,
        array $rates,
    ): int {
        return Money::tokenCost($textTokens, $rates['input_text_tokens'])
            + Money::tokenCost($imageTokens, $rates['input_image_tokens'])
            + Money::tokenCost($outputTokens, $rates['output_tokens'])
            + Money::tokenCost($reasoningTokens, $rates['reasoning_tokens']);
    }
}
