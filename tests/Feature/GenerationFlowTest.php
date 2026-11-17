<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\CostCalculator;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GenerationFlowTest extends TestCase
{
    use RefreshDatabase;

    private array $rates = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.pixelforge.key' => 'pf_test',
            'services.pixelforge.webhook_secret' => 'whsec_test',
            'services.pixelforge.markup_basis_points' => 2500,
        ]);

        Cache::forever('pixelforge.pricing', [
            'fetched_at' => now()->timestamp,
            'data' => $this->pricing(),
        ]);
    }

    private function pricing(): array
    {
        return [
            'rates' => $this->rates,
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }

    private function userWithBalance(string $usd = '1.000000000'): User
    {
        return User::factory()->create([
            'credit_balance_picodollars' => Money::fromUsd($usd),
        ]);
    }

    public function test_generation_is_queued_with_the_max_cost_held(): void
    {
        $user = $this->userWithBalance();

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response($this->createResponse(), 202),
        ]);

        $response = $this->actingAs($user)->post('/generate', [
            'prompt' => 'A test image',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ]);

        $response->assertRedirect(route('generations.index'));

        $generation = Generation::query()->sole();

        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame('gen_test', $generation->pixelforge_id);
        $this->assertSame($generation->max_charge, $generation->held_amount);
        $this->assertSame(
            Money::fromUsd('1.000000000') - $generation->max_charge,
            $user->fresh()->credit_balance_picodollars,
        );

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'generation_id' => $generation->id,
            'type' => 'hold',
            'amount' => -$generation->max_charge,
        ]);

        Http::assertSent(function ($request) use ($generation) {
            return $request->url() === 'https://pixelforge.promptedit.dev/v1/generations'
                && $request->hasHeader('Idempotency-Key', $generation->idempotency_key)
                && $request['metadata']['generation_id'] === (string) $generation->id;
        });
    }

    public function test_generation_is_blocked_when_the_balance_cannot_cover_the_hold(): void
    {
        $user = $this->userWithBalance('0.000000001');

        Http::fake();

        $response = $this->actingAs($user)->post('/generate', [
            'prompt' => 'A test image',
            'size' => '1024x1024',
            'quality' => 'medium',
        ]);

        $response->assertSessionHasErrors('credits');
        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(Money::fromUsd('0.000000001'), $user->fresh()->credit_balance_picodollars);
        Http::assertNothingSent();
    }

    public function test_success_webhooks_settle_the_exact_cost_once_even_when_duplicated(): void
    {
        $user = $this->userWithBalance();
        $generation = $this->queuedGeneration($user);

        $usage = [
            'input_text_tokens' => 12,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 600,
            'total_tokens' => 1712,
        ];

        $payload = $this->generationEvent('generation.succeeded', $generation, [
            'usage' => $usage,
            'image_url' => 'https://pixelforge.promptedit.dev/images/gen_test.png',
            'image_url_expires_at' => now()->addDay()->toIso8601ZuluString(),
        ]);

        $this->sendWebhook($payload)->assertNoContent();
        $this->sendWebhook($payload)->assertNoContent();

        // Same generation, different event id (another duplicate delivery).
        $payload['id'] = 'evt_'.Str::random(20);
        $this->sendWebhook($payload)->assertNoContent();

        $generation->refresh();

        $expectedProviderCost = CostCalculator::providerCost($usage, $this->rates);
        $expectedCharge = Money::withMarkup($expectedProviderCost, 2500);

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame($expectedProviderCost, $generation->provider_cost);
        $this->assertSame($expectedCharge, $generation->charged_amount);
        $this->assertNotNull($generation->settled_at);
        $this->assertSame(
            Money::fromUsd('1.000000000') - $expectedCharge,
            $user->fresh()->credit_balance_picodollars,
        );
        $this->assertSame(1, DB::table('credit_transactions')
            ->where('generation_id', $generation->id)
            ->where('type', 'settlement')
            ->count());

        // A delayed processing event must not move a final generation back.
        $processing = $this->generationEvent('generation.processing', $generation, []);
        $this->sendWebhook($processing)->assertNoContent();

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
        $this->assertSame(
            Money::fromUsd('1.000000000') - $expectedCharge,
            $user->fresh()->credit_balance_picodollars,
        );
    }

    public function test_failed_generation_releases_the_hold_and_costs_nothing(): void
    {
        $user = $this->userWithBalance();
        $generation = $this->queuedGeneration($user);
        $held = $generation->held_amount;

        $payload = $this->generationEvent('generation.failed', $generation, [
            'error' => [
                'code' => 'generation_failed',
                'message' => 'The model could not produce an image for this prompt.',
            ],
            'completed_at' => now()->toIso8601ZuluString(),
        ]);

        $this->sendWebhook($payload)->assertNoContent();

        $generation->refresh();

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->provider_cost);
        $this->assertSame(0, $generation->charged_amount);
        $this->assertSame(Money::fromUsd('1.000000000'), $user->fresh()->credit_balance_picodollars);
        $this->assertDatabaseHas('credit_transactions', [
            'generation_id' => $generation->id,
            'type' => 'settlement',
            'amount' => $held,
        ]);
    }

    public function test_webhook_can_arrive_before_the_create_response_is_stored(): void
    {
        $user = User::factory()->create(['credit_balance_picodollars' => Money::fromUsd('1.000000000')]);

        $generation = Generation::factory()->create([
            'user_id' => $user->id,
            'pixelforge_id' => null,
            'status' => GenerationStatus::Queued,
            'held_amount' => Money::fromUsd('0.100000000'),
            'max_charge' => Money::fromUsd('0.100000000'),
            'estimated_charge' => Money::fromUsd('0.080000000'),
            'rates' => $this->rates,
        ]);

        DB::transaction(fn () => app(WalletService::class)->hold($user, $generation, $generation->held_amount));

        $payload = $this->generationEvent('generation.succeeded', $generation, [
            'id' => 'gen_raced_ahead',
            'usage' => [
                'input_text_tokens' => 10,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 200,
                'total_tokens' => 1310,
            ],
        ], providerId: 'gen_raced_ahead');

        $this->sendWebhook($payload)->assertNoContent();

        $generation->refresh();

        $this->assertSame('gen_raced_ahead', $generation->pixelforge_id);
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertNotNull($generation->settled_at);
    }

    public function test_webhook_signature_is_verified(): void
    {
        $payload = ['id' => 'evt_fake', 'type' => 'generation.succeeded', 'data' => ['object' => []]];

        $response = $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => 't='.time().',v1=deadbeef'],
            json_encode($payload),
        );

        $response->assertStatus(400);
        $this->assertDatabaseCount('pixelforge_events', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createResponse(): array
    {
        return [
            'id' => 'gen_test',
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => 'A test image',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'metadata' => [],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => $this->rates,
            'error' => null,
            'created_at' => now()->toIso8601ZuluString(),
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    private function queuedGeneration(User $user): Generation
    {
        $generation = Generation::factory()->create([
            'user_id' => $user->id,
            'pixelforge_id' => 'gen_test',
            'status' => GenerationStatus::Queued,
            'held_amount' => Money::fromUsd('0.100000000'),
            'max_charge' => Money::fromUsd('0.100000000'),
            'estimated_charge' => Money::fromUsd('0.080000000'),
            'rates' => $this->rates,
        ]);

        DB::transaction(fn () => app(WalletService::class)->hold($user, $generation, $generation->held_amount));

        return $generation;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function generationEvent(
        string $type,
        Generation $generation,
        array $overrides,
        ?string $providerId = null,
    ): array {
        $object = [
            'id' => $providerId ?? $generation->pixelforge_id,
            'object' => 'generation',
            'status' => match ($type) {
                'generation.succeeded' => 'succeeded',
                'generation.failed' => 'failed',
                default => 'processing',
            },
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_images' => $generation->reference_images,
            'metadata' => ['generation_id' => (string) $generation->id],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => $generation->rates,
            'error' => null,
            'created_at' => $generation->created_at->toIso8601ZuluString(),
            'started_at' => now()->toIso8601ZuluString(),
            'completed_at' => null,
        ];

        return [
            'id' => 'evt_'.Str::random(20),
            'object' => 'event',
            'type' => $type,
            'created_at' => now()->toIso8601ZuluString(),
            'data' => [
                'object' => array_replace_recursive($object, $overrides),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

        return $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $body,
        );
    }
}
