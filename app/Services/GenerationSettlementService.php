<?php

namespace App\Services;

use App\Models\Generation;
use App\Services\Pixelforge\CostCalculator;
use App\Support\Money;

/**
 * Turns a final Pixelforge generation into money. Called while the generation
 * row is locked, exactly once: `settled_at` guards it and the credit ledger has
 * a unique (generation_id, type) index as a second line of defence.
 */
class GenerationSettlementService
{
    public function __construct(private readonly WalletService $wallet) {}

    public function settleSucceeded(Generation $generation): void
    {
        if ($generation->settled_at !== null) {
            return;
        }

        if ($generation->usage === null || $generation->rates === null) {
            report(new \RuntimeException("Succeeded generation #{$generation->id} has no usage or rates; cannot settle."));

            return;
        }

        $providerCost = CostCalculator::providerCost($generation->usage, $generation->rates);
        $charge = Money::withMarkup($providerCost);

        $this->wallet->settle(
            userId: $generation->user_id,
            generationId: $generation->id,
            adjustment: $generation->held_amount - $charge,
            chargedPicodollars: $charge,
        );

        $generation->forceFill([
            'provider_cost' => $providerCost,
            'charged_amount' => $charge,
            'settled_at' => now(),
        ])->save();
    }

    public function settleFailed(Generation $generation): void
    {
        if ($generation->settled_at !== null) {
            return;
        }

        $this->wallet->settle(
            userId: $generation->user_id,
            generationId: $generation->id,
            adjustment: $generation->held_amount,
            chargedPicodollars: 0,
        );

        $generation->forceFill([
            'provider_cost' => 0,
            'charged_amount' => 0,
            'settled_at' => now(),
        ])->save();
    }
}
