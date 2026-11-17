<?php

namespace App\Http\Controllers;

use App\Models\PixelforgeEvent;
use App\Services\Pixelforge\WebhookEventProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PixelforgeWebhookController extends Controller
{
    public function __construct(private readonly WebhookEventProcessor $processor) {}

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.pixelforge.webhook_secret');

        if ($secret === '') {
            Log::error('PIXELFORGE_WEBHOOK_SECRET is not configured; rejecting webhook.');

            return response('Webhook secret is not configured.', 500);
        }

        $payload = $request->getContent();
        $signature = $this->parseSignature((string) $request->header('Pixelforge-Signature'));
        $timestamp = (int) ($signature['t'] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        if (
            ! isset($signature['v1'])
            || $timestamp === 0
            || ! hash_equals($expected, $signature['v1'])
            || abs(time() - $timestamp) > (int) config('services.pixelforge.webhook_tolerance', 300)
        ) {
            Log::warning('Rejected a Pixelforge webhook with an invalid signature.', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid Pixelforge signature.', 400);
        }

        $event = json_decode($payload, true);

        if (
            ! is_array($event)
            || ! is_string($event['id'] ?? null)
            || ! is_string($event['type'] ?? null)
        ) {
            return response('Invalid Pixelforge event.', 400);
        }

        // Cheap early exit for the common duplicate delivery.
        if (PixelforgeEvent::query()->where('event_id', $event['id'])->exists()) {
            return response()->noContent();
        }

        $object = $event['data']['object'] ?? [];

        DB::transaction(function () use ($event, $request, $object) {
            $model = PixelforgeEvent::create([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'pixelforge_generation_id' => is_array($object) && is_string($object['id'] ?? null)
                    ? $object['id']
                    : null,
                'delivery_attempt' => max(1, (int) $request->header('Pixelforge-Delivery-Attempt', '1')),
                'payload' => $event,
            ]);

            $this->processor->process($model);

            $model->forceFill(['processed_at' => now()])->save();
        });

        return response()->noContent();
    }

    /**
     * @return array<string, string>
     */
    private function parseSignature(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        return $parts;
    }
}
