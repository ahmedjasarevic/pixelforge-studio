<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Services\GenerationSyncService;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for webhooks that were never delivered. Pixelforge explicitly says
 * delivery is not guaranteed, so non-final generations are polled until they
 * reach a final state.
 */
class PollPixelforgeGenerations extends Command
{
    protected $signature = 'pixelforge:poll {--limit=20}';

    protected $description = 'Poll in-flight Pixelforge generations and recover stuck submissions';

    public function handle(PixelforgeClient $client, GenerationSyncService $sync): int
    {
        $generations = Generation::query()
            ->where(function ($query) {
                $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
                    // A succeeded generation that could not be settled (for
                    // example a malformed webhook) is retried here too.
                    ->orWhere(fn ($query) => $query
                        ->where('status', GenerationStatus::Succeeded)
                        ->whereNull('settled_at'));
            })
            ->whereNotNull('pixelforge_id')
            // Give webhooks a head start so polling does not waste rate limit.
            ->where('updated_at', '<=', now()->subSeconds(15))
            ->orderBy('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($generations as $generation) {
            try {
                $data = $client->getGeneration($generation->pixelforge_id);
                $sync->applyProviderGeneration($generation, $data);
                $this->line("Polled generation #{$generation->id} ({$generation->pixelforge_id}).");
            } catch (PixelforgeException $exception) {
                if ($exception->errorCode === 'rate_limit_exceeded') {
                    $this->warn('Pixelforge rate limit reached; ending this poll run.');
                    break;
                }

                Log::warning('Could not poll a Pixelforge generation.', [
                    'generation_id' => $generation->id,
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                ]);
            }

            // Stay well under Pixelforge's 60 requests/minute limit.
            usleep(250_000);
        }

        $this->recoverStuckSubmissions();

        return self::SUCCESS;
    }

    /**
     * If a queue worker was down when a generation was created, its submit job
     * may never have run. Re-dispatch it; the idempotency key makes this safe.
     */
    private function recoverStuckSubmissions(): void
    {
        $stuck = Generation::query()
            ->where('status', GenerationStatus::Queued)
            ->whereNull('pixelforge_id')
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('submit_attempts', '<', 8)
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($stuck as $generation) {
            SubmitGenerationJob::dispatch($generation->id);
            $this->line("Re-queued stuck submission #{$generation->id}.");
        }
    }
}
