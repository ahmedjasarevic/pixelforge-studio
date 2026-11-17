<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * These two accounts are the public demo access for the assessment.
     * Credentials are documented in the README.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@pixelforge.test'],
            [
                'name' => 'Operator',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_admin' => true,
            ],
        );

        if (! $admin->is_admin) {
            $admin->forceFill(['is_admin' => true])->save();
        }

        $demo = User::query()->firstOrCreate(
            ['email' => 'demo@pixelforge.test'],
            [
                'name' => 'Demo User',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $this->grantWelcomeCredits($admin, 50);
        $this->grantWelcomeCredits($demo, 25);
    }

    private function grantWelcomeCredits(User $user, int $dollars): void
    {
        if ($user->creditTransactions()->where('type', 'purchase')->exists()) {
            return;
        }

        DB::transaction(function () use ($user, $dollars) {
            app(WalletService::class)->purchase(
                user: $user,
                picodollars: $dollars * Money::PICO_PER_USD,
                description: $dollars.' demo credits',
            );
        });
    }
}
