<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    protected $model = Generation::class;

    public function definition(): array
    {
        $estimate = 10_000_000; // $0.00001, arbitrary but exact

        return [
            'user_id' => User::factory(),
            'pixelforge_id' => 'gen_'.Str::upper(Str::random(24)),
            'idempotency_key' => (string) Str::uuid(),
            'prompt' => fake()->sentence(),
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'status' => GenerationStatus::Queued,
            'estimated_provider_cost' => $estimate,
            'max_provider_cost' => $estimate,
            'estimated_charge' => 12_500_000,
            'max_charge' => 12_500_000,
            'held_amount' => 12_500_000,
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
        ];
    }
}
