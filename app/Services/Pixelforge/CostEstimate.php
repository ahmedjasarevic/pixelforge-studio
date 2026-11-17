<?php

namespace App\Services\Pixelforge;

use App\Support\Money;

/**
 * The estimate we show before a generation is submitted, plus the maximum we
 * hold. Output tokens and reference-image tokens are exact; text and reasoning
 * tokens are only known after Pixelforge finishes, hence the range.
 */
final class CostEstimate
{
    /**
     * @param  array<string, string>  $rates
     */
    public function __construct(
        public readonly int $characters,
        public readonly int $textTokens,
        public readonly int $maxTextTokens,
        public readonly int $imageTokens,
        public readonly int $outputTokens,
        public readonly int $reasoningTokens,
        public readonly int $maxReasoningTokens,
        public readonly int $providerCost,
        public readonly int $maxProviderCost,
        public readonly int $charge,
        public readonly int $maxCharge,
        public readonly array $rates,
        public readonly int $markupBasisPoints,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'characters' => $this->characters,
            'tokens' => [
                'input_text' => $this->textTokens,
                'input_image' => $this->imageTokens,
                'output' => $this->outputTokens,
                'reasoning' => $this->reasoningTokens,
            ],
            'max_tokens' => [
                'input_text' => $this->maxTextTokens,
                'input_image' => $this->imageTokens,
                'output' => $this->outputTokens,
                'reasoning' => $this->maxReasoningTokens,
            ],
            'provider_cost' => $this->providerCost,
            'max_provider_cost' => $this->maxProviderCost,
            'charge' => $this->charge,
            'max_charge' => $this->maxCharge,
            'provider_cost_formatted' => Money::format($this->providerCost),
            'charge_formatted' => Money::format($this->charge),
            'max_provider_cost_formatted' => Money::format($this->maxProviderCost),
            'max_charge_formatted' => Money::format($this->maxCharge),
            'markup' => $this->charge - $this->providerCost,
            'markup_formatted' => Money::format($this->charge - $this->providerCost),
            'max_markup' => $this->maxCharge - $this->maxProviderCost,
            'max_markup_formatted' => Money::format($this->maxCharge - $this->maxProviderCost),
            'markup_percent' => $this->markupBasisPoints / 100,
            'rates' => $this->rates,
        ];
    }
}
