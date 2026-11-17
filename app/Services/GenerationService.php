<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PricingRepository;
use App\Services\Pixelforge\TokenEstimator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerationService
{
    public function __construct(
        private readonly PricingRepository $pricing,
        private readonly TokenEstimator $estimator,
        private readonly WalletService $wallet,
    ) {}

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: array<int, string>}  $data
     */
    public function create(User $user, array $data): Generation
    {
        $pricing = $this->pricing->get();
        $referenceImages = array_values($data['reference_images'] ?? []);

        $estimate = $this->estimator->estimate(
            pricing: $pricing,
            prompt: $data['prompt'],
            size: $data['size'],
            quality: $data['quality'],
            referenceImages: count($referenceImages),
        );

        return DB::transaction(function () use ($user, $data, $referenceImages, $estimate) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            // Hold the documented worst case, not the midpoint estimate, so the
            // balance can never be spent twice while a generation is running.
            if (! $lockedUser->canAfford($estimate->maxCharge)) {
                throw ValidationException::withMessages([
                    'credits' => sprintf(
                        'Not enough credits. This generation can cost up to %s, and your balance is %s. Buy credits or choose a cheaper size/quality.',
                        Money::format($estimate->maxCharge),
                        Money::format($lockedUser->credit_balance_picodollars),
                    ),
                ]);
            }

            $generation = Generation::create([
                'user_id' => $lockedUser->id,
                'idempotency_key' => (string) Str::uuid(),
                'prompt' => $data['prompt'],
                'size' => $data['size'],
                'quality' => $data['quality'],
                'reference_images' => $referenceImages,
                'status' => GenerationStatus::Queued,
                'estimated_provider_cost' => $estimate->providerCost,
                'max_provider_cost' => $estimate->maxProviderCost,
                'estimated_charge' => $estimate->charge,
                'max_charge' => $estimate->maxCharge,
                'held_amount' => $estimate->maxCharge,
                'estimate_breakdown' => $estimate->toArray(),
            ]);

            $this->wallet->hold($lockedUser, $generation, $estimate->maxCharge);

            SubmitGenerationJob::dispatch($generation->id)->afterCommit();

            return $generation;
        });
    }
}
