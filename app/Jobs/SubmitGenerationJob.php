<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\GenerationFailureService;
use App\Services\GenerationSyncService;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Hands a generation to Pixelforge. The database row (and its idempotency key)
 * exists before this job runs, so even a webhook that races ahead of the create
 * response can be matched back to the right user.
 */
class SubmitGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 45;

    public function __construct(public readonly int $generationId) {}

    public function handle(PixelforgeClient $client, GenerationSyncService $sync): void
    {
        $generation = Generation::find($this->generationId);

        if ($generation === null || $generation->status->isFinal()) {
            return;
        }

        // A webhook may have already delivered the generation (including its
        // provider id) before this job ran.
        if ($generation->pixelforge_id !== null) {
            return;
        }

        $generation->increment('submit_attempts');

        try {
            $payload = [
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'metadata' => ['generation_id' => (string) $generation->id],
            ];

            if ($generation->reference_images !== []) {
                $payload['reference_images'] = $generation->reference_images;
            }

            $data = $client->createGeneration($payload, $generation->idempotency_key);

            $sync->applyProviderGeneration($generation, $data);
        } catch (PixelforgeException $exception) {
            if ($exception->retryable && $this->attempts() < $this->tries) {
                $this->release($this->retryDelay($exception));

                return;
            }

            app(GenerationFailureService::class)->fail(
                generation: $generation,
                code: $exception->errorCode,
                message: $exception->userMessage(),
            );
        } catch (Throwable $exception) {
            report($exception);

            if ($this->attempts() < $this->tries) {
                $this->release(min(2 ** $this->attempts(), 60));

                return;
            }

            app(GenerationFailureService::class)->fail(
                generation: $generation,
                code: 'internal_error',
                message: 'Something went wrong while submitting this generation. Nothing was charged. Please try again.',
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $generation = Generation::find($this->generationId);

        if ($generation === null || $generation->status->isFinal() || $generation->pixelforge_id !== null) {
            return;
        }

        app(GenerationFailureService::class)->fail(
            generation: $generation,
            code: 'provider_unavailable',
            message: 'Pixelforge did not accept this generation after several attempts. Nothing was charged. Please try again.',
        );
    }

    /**
     * Respect Pixelforge's Retry-After, and back off further on every attempt.
     */
    private function retryDelay(PixelforgeException $exception): int
    {
        $backoff = min(2 ** max(1, $this->attempts()), 60);

        return max($backoff, min($exception->retryAfter ?? 0, 300));
    }
}
