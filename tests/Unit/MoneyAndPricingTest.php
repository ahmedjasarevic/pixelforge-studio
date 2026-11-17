<?php

namespace Tests\Unit;

use App\Services\Pixelforge\CostCalculator;
use App\Services\Pixelforge\TokenEstimator;
use App\Support\Money;
use Tests\TestCase;

class MoneyAndPricingTest extends TestCase
{
    private function pricing(): array
    {
        return [
            'object' => 'pricing',
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }

    public function test_exact_usd_strings_round_trip_to_picodollars(): void
    {
        $this->assertSame(46_125_000_000, Money::fromUsd('0.046125000'));
        $this->assertSame(169_080_000_000, Money::fromUsd('0.169080000'));
        $this->assertSame('0.16908', Money::decimal(169_080_000_000));
    }

    public function test_token_costs_and_markup_are_exact(): void
    {
        $this->assertSame(258_000_000_000, Money::tokenCost(6_450, '40.00'));
        $this->assertSame(39_800_000_000, Money::tokenCost(3_184, '12.50'));
        $this->assertSame(467_343_750_000, Money::withMarkup(373_875_000_000, 2500));
    }

    public function test_estimator_matches_the_documented_worst_case(): void
    {
        config(['services.pixelforge.markup_basis_points' => 2500]);

        $estimate = (new TokenEstimator)->estimate(
            pricing: $this->pricing(),
            prompt: str_repeat('a', 400),
            size: '1536x1024',
            quality: 'high',
            referenceImages: 2,
        );

        // The docs' worked example:
        // 115 text + 1,530 image + 6,450 output + 8,000 reasoning = $0.373875.
        $this->assertSame(115, $estimate->maxTextTokens);
        $this->assertSame(373_875_000_000, $estimate->maxProviderCost);
        $this->assertSame(467_343_750_000, $estimate->maxCharge);
        $this->assertSame(330_050_000_000, $estimate->providerCost);
        $this->assertSame(412_562_500_000, $estimate->charge);
    }

    public function test_provider_cost_from_actual_usage_is_exact(): void
    {
        $cost = CostCalculator::providerCost(
            usage: [
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 600,
            ],
            rates: $this->pricing()['rates'],
        );

        // 0.00006 + 0 + 0.044 + 0.0075 = $0.05156
        $this->assertSame(51_560_000_000, $cost);
        $this->assertSame('0.05156', Money::decimal($cost));
    }
}
