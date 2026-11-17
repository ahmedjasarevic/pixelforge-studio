<?php

namespace App\Services\Pixelforge;

use App\Models\Generation;
use App\Models\PixelforgeEvent;
use App\Services\GenerationSyncService;
use Illuminate\Support\Facades\Log;

class WebhookEventProcessor
{
    public function __construct(private readonly GenerationSyncService $sync) {}

    public function process(PixelforgeEvent $event): void
    {
        if ($event->type === 'ping') {
            return;
        }

        $object = $event->payload['data']['object'] ?? null;

        if (! is_array($object) || ($object['object'] ?? null) !== 'generation') {
            Log::warning('Ignoring a Pixelforge webhook without a generation object.', [
                'event_id' => $event->event_id,
                'type' => $event->type,
            ]);

            return;
        }

        $generation = null;

        if (is_string($object['id'] ?? null)) {
            $generation = Generation::query()
                ->where('pixelforge_id', $object['id'])
                ->first();
        }

        // A webhook can arrive before our POST /v1/generations call returns
        // and before we have stored the provider id. The metadata we send on
        // creation lets us match it to the local row anyway.
        if ($generation === null) {
            $localId = $object['metadata']['generation_id'] ?? null;

            if (is_numeric($localId)) {
                $generation = Generation::query()->find((int) $localId);
            }
        }

        if ($generation === null) {
            Log::warning('Pixelforge webhook references an unknown generation.', [
                'event_id' => $event->event_id,
                'pixelforge_id' => $object['id'] ?? null,
                'metadata' => $object['metadata'] ?? null,
            ]);

            return;
        }

        $this->sync->applyProviderGeneration($generation, $object);
    }
}
