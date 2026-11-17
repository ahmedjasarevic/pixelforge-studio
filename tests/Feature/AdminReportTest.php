<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_open_the_report(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/report')->assertForbidden();
    }

    public function test_report_reconciles_our_provider_costs_with_pixelforge_usage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Demo User']);

        $providerCost = Money::fromUsd('0.058400000');
        $charged = Money::withMarkup($providerCost, 2500);

        Generation::factory()->create([
            'user_id' => $customer->id,
            'status' => GenerationStatus::Succeeded,
            'provider_cost' => $providerCost,
            'charged_amount' => $charged,
            'held_amount' => Money::fromUsd('0.100000000'),
            'settled_at' => now(),
            'completed_at' => now(),
        ]);

        $usageDays = [];

        foreach (range(6, 0) as $daysAgo) {
            $date = now('UTC')->subDays($daysAgo)->toDateString();
            $isToday = $daysAgo === 0;

            $usageDays[] = [
                'date' => $date,
                'generations' => $isToday ? 1 : 0,
                'input_text_tokens' => 0,
                'input_image_tokens' => 0,
                'output_tokens' => 0,
                'reasoning_tokens' => 0,
                'cost_usd' => $isToday ? '0.058400000' : '0.000000000',
            ];
        }

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/usage*' => Http::response([
                'object' => 'usage',
                'start_date' => now('UTC')->subDays(6)->toDateString(),
                'end_date' => now('UTC')->toDateString(),
                'data' => $usageDays,
                'total' => [
                    'generations' => 1,
                    'input_text_tokens' => 0,
                    'input_image_tokens' => 0,
                    'output_tokens' => 0,
                    'reasoning_tokens' => 0,
                    'cost_usd' => '0.058400000',
                ],
            ]),
        ]);

        $response = $this->actingAs($admin)->get('/admin/report');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Report')
            ->where('report.totals.provider_cost', $providerCost)
            ->where('report.totals.charged', $charged)
            ->where('report.totals.profit', $charged - $providerCost)
            ->where('report.totals.reconciled', true)
            ->where('report.user_totals.0.user_name', 'Demo User'));
    }

    public function test_report_surfaces_a_reconciliation_mismatch(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Generation::factory()->create([
            'user_id' => $admin->id,
            'status' => GenerationStatus::Succeeded,
            'provider_cost' => Money::fromUsd('0.058400000'),
            'charged_amount' => Money::withMarkup(Money::fromUsd('0.058400000'), 2500),
            'settled_at' => now(),
            'completed_at' => now(),
        ]);

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/usage*' => Http::response([
                'object' => 'usage',
                'data' => [],
                'total' => ['cost_usd' => '0.000000000', 'generations' => 0],
            ]),
        ]);

        $this->actingAs($admin)
            ->get('/admin/report')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.totals.reconciled', false)
                ->where('report.totals.provider_delta', Money::fromUsd('0.058400000')));
    }
}
