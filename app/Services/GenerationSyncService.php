<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a Pixelforge generation object from any source (create response,
 * webhook, or polling) to our local row.
 *
 * Pixelforge delivers webhooks at least once and in any order, so this method
 * is deliberately idempotent and monotonic:
 *  - statuses may only move forward,
 *  - a final generation is never moved back,
 *  - settlements are guarded by settled_at and a unique ledger index.
 */
class GenerationSyncService
{
    public function __construct(private readonly GenerationSettlementService $settlement) {}

    /**
     * @param  array<string, mixed>  $data  Pixelforge generation object.
     */
    public function applyProviderGeneration(Generation $generation, array $data): Generation
    {
        return DB::transaction(function () use ($generation, $data) {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            $incoming = GenerationStatus::tryFrom((string) ($data['status'] ?? ''));

            if ($incoming === null) {
                Log::warning('Pixelforge returned an unknown generation status.', [
                    'generation_id' => $generation->id,
                    'status' => $data['status'] ?? null,
                ]);

                return $generation;
            }

            $providerId = isset($data['id']) ? (string) $data['id'] : null;

            if ($providerId !== null && $generation->pixelforge_id !== null && $generation->pixelforge_id !== $providerId) {
                Log::warning('Ignoring a Pixelforge event for a different provider id.', [
                    'generation_id' => $generation->id,
                    'expected' => $generation->pixelforge_id,
                    'received' => $providerId,
                ]);

                return $generation;
            }

            // A delayed "processing" event must never overwrite a success or
            // failure. Contradictory final states are ignored entirely.
            if ($generation->status->isFinal() && $incoming !== $generation->status) {
                Log::info('Ignoring an out-of-order Pixelforge status.', [
                    'generation_id' => $generation->id,
                    'current' => $generation->status->value,
                    'received' => $incoming->value,
                ]);

                return $generation;
            }

            $updates = [];

            if ($generation->pixelforge_id === null && $providerId !== null) {
                $updates['pixelforge_id'] = $providerId;
            }

            if ($generation->submitted_at === null) {
                $updates['submitted_at'] = now();
            }

            if ($generation->settled_at === null && is_array($data['rates'] ?? null)) {
                $updates['rates'] = $data['rates'];
            }

            if (is_array($data['usage'] ?? null)) {
                $updates['usage'] = $data['usage'];
            }

            if (! empty($data['started_at'])) {
                $updates['started_at'] = $data['started_at'];
            }

            if (! empty($data['completed_at'])) {
                $updates['completed_at'] = $data['completed_at'];
            }

            if (is_string($data['image_url'] ?? null) && $data['image_url'] !== '') {
                $updates['image_url'] = $data['image_url'];
                $updates['image_url_expires_at'] = $data['image_url_expires_at'] ?? null;
            }

            if ($incoming === GenerationStatus::Failed) {
                $updates['error_code'] = $data['error']['code'] ?? 'generation_failed';
                $updates['error_message'] = $data['error']['message'] ?? 'Pixelforge could not generate this image.';
            }

            if ($incoming === GenerationStatus::Succeeded) {
                $updates['error_code'] = null;
                $updates['error_message'] = null;
            }

            if (! $generation->status->isFinal() && $incoming->rank() >= $generation->status->rank()) {
                $updates['status'] = $incoming;
            }

            $generation->forceFill($updates)->save();

            if ($generation->status === GenerationStatus::Succeeded && $generation->settled_at === null) {
                $this->settlement->settleSucceeded($generation);
            } elseif ($generation->status === GenerationStatus::Failed && $generation->settled_at === null) {
                $this->settlement->settleFailed($generation);
            }

            return $generation;
        });
    }
}
