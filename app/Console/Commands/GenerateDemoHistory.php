<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GenerationService;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Creates a small, realistic set of generations for the public demo account.
 * Run this once after deploying, with a queue worker running, then run
 * `php artisan pixelforge:poll` or wait for webhooks/polling to finish them.
 */
class GenerateDemoHistory extends Command
{
    protected $signature = 'pixelforge:demo {--force : Create another set even if the demo user already has generations}';

    protected $description = 'Create a few real generations on the demo account for reviewers';

    public function handle(GenerationService $service): int
    {
        $user = User::query()->where('email', 'demo@pixelforge.test')->first();

        if ($user === null) {
            $this->error('Demo user not found. Run `php artisan db:seed --force` first.');

            return self::FAILURE;
        }

        if ($user->generations()->exists() && ! $this->option('force')) {
            $this->info('Demo user already has generations. Use --force to add more.');

            return self::SUCCESS;
        }

        $requests = [
            [
                'prompt' => 'A quiet coastal lighthouse at dawn, oil painting, warm light and soft mist [succeed]',
                'size' => '1024x1024',
                'quality' => 'low',
                'reference_images' => [],
            ],
            [
                'prompt' => 'An isometric greenhouse floating above a sea of clouds, soft morning light, detailed [succeed]',
                'size' => '1536x1024',
                'quality' => 'medium',
                'reference_images' => ['https://picsum.photos/seed/pixelforge-style/800/600'],
            ],
            [
                'prompt' => 'A bold abstract poster in violet, fuchsia and amber, layered geometric shapes [succeed]',
                'size' => '1024x1536',
                'quality' => 'high',
                'reference_images' => [],
            ],
            [
                'prompt' => 'A deliberate provider failure for the assessment [fail]',
                'size' => '1024x1024',
                'quality' => 'low',
                'reference_images' => [],
            ],
        ];

        foreach ($requests as $request) {
            try {
                $generation = $service->create($user, $request);
                $this->line("Queued generation #{$generation->id} · {$request['quality']} {$request['size']}");
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                $this->error('Not enough demo credits: '.$message);

                return self::FAILURE;
            } catch (PixelforgeException $exception) {
                $this->error('Pixelforge error: '.$exception->userMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Demo generations queued. With a queue worker running, they finish in 10–90 seconds.');
        $this->line('Run `php artisan pixelforge:poll` to recover their state if webhooks are delayed.');

        return self::SUCCESS;
    }
}
