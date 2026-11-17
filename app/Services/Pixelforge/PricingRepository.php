<?php

namespace App\Services\Pixelforge;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pixelforge publishes its current rates at GET /v1/pricing. We cache the last
 * good response and keep serving it if a refresh fails, so a temporary outage
 * never makes the app unusable. Actual charges always use the rates returned
 * on the generation itself, never this cached copy.
 */
class PricingRepository
{
    private const CACHE_KEY = 'pixelforge.pricing';

    private const FRESH_FOR_SECONDS = 300;

    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['fetched_at'] ?? 0) > now()->subSeconds(self::FRESH_FOR_SECONDS)->timestamp) {
            return $cached['data'];
        }

        try {
            $data = $this->client->pricing();
            Cache::forever(self::CACHE_KEY, [
                'fetched_at' => now()->timestamp,
                'data' => $data,
            ]);

            return $data;
        } catch (Throwable $exception) {
            // A stale copy of the published rates is better than no page at all.
            if (is_array($cached) && is_array($cached['data'] ?? null)) {
                report($exception);

                return $cached['data'];
            }

            throw $exception;
        }
    }
}
