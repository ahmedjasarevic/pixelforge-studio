<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class PixelforgeClient
{
    /**
     * Public endpoint; no authentication required.
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get($this->url('pricing'));

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Create a generation. The caller must reuse the same idempotency key for
     * every retry so a network failure can never bill the account twice.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createGeneration(array $payload, string $idempotencyKey): array
    {
        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($this->url('generations'), $payload);

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $this->generationFrom($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGeneration(string $id): array
    {
        $response = $this->request()
            ->get($this->url('generations/'.rawurlencode($id)));

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $this->generationFrom($response);
    }

    /**
     * What Pixelforge billed this API key, per UTC day. This is the source of
     * truth used to reconcile the operator report.
     *
     * @return array<string, mixed>
     */
    public function usage(Carbon $startDate, Carbon $endDate): array
    {
        $response = $this->request()->get($this->url('usage'), [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('services.pixelforge.key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->withHeaders(['User-Agent' => config('app.name').'/1.0']);
    }

    /**
     * @return array<string, mixed>
     */
    private function generationFrom(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body) || ! isset($body['id'], $body['status'])) {
            throw new UnexpectedValueException('Pixelforge returned an unexpected generation payload.');
        }

        return $body;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.pixelforge.base_url'), '/');
    }
}
