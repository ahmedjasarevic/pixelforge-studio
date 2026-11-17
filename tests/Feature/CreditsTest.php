<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fake_purchase_adds_exact_credits_and_writes_a_ledger_entry(): void
    {
        $user = User::factory()->create(['credit_balance_picodollars' => 0]);

        $response = $this->actingAs($user)->post('/credits/purchase', [
            'amount' => '10.25',
        ]);

        $response->assertRedirect();
        $this->assertSame(Money::fromUsd('10.250000000'), $user->fresh()->credit_balance_picodollars);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'amount' => Money::fromUsd('10.250000000'),
            'balance_after' => Money::fromUsd('10.250000000'),
        ]);
    }

    public function test_purchase_amount_is_validated(): void
    {
        $user = User::factory()->create(['credit_balance_picodollars' => 0]);

        $this->actingAs($user)
            ->post('/credits/purchase', ['amount' => '0.50'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, $user->fresh()->credit_balance_picodollars);
        $this->assertDatabaseCount('credit_transactions', 0);
    }
}
