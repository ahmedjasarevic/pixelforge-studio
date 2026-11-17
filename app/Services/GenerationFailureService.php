<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

/**
 * Marks a generation failed when Pixelforge rejected it at submission or never
 * accepted it after all retries. Failed generations are always free.
 */
class GenerationFailureService
{
    public function __construct(private readonly GenerationSettlementService $settlement) {}

    public function fail(Generation $generation, string $code, string $message): void
    {
        DB::transaction(function () use ($generation, $code, $message) {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            if ($generation->status->isFinal()) {
                return;
            }

            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'error_code' => $code,
                'error_message' => $message,
                'completed_at' => now(),
            ])->save();

            $this->settlement->settleFailed($generation);
        });
    }
}
