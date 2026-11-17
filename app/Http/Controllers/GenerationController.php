<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Services\GenerationService;
use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pixelforge\PricingRepository;
use App\Services\Pixelforge\TokenEstimator;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GenerationController extends Controller
{
    public function __construct(
        private readonly PricingRepository $pricing,
        private readonly TokenEstimator $estimator,
    ) {}

    public function index(Request $request): Response
    {
        $generations = $request->user()
            ->generations()
            ->latest()
            ->limit(60)
            ->get();

        $inProgress = $request->user()
            ->generations()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->count();

        $totalCharged = (int) $request->user()
            ->generations()
            ->where('status', GenerationStatus::Succeeded)
            ->sum('charged_amount');

        return Inertia::render('History', [
            'generations' => GenerationResource::collection($generations)->resolve(),
            'has_pending' => $inProgress > 0,
            'summary' => [
                'total_generations' => $request->user()->generations()->count(),
                'in_progress' => $inProgress,
                'total_charged' => $totalCharged,
                'total_charged_formatted' => Money::format($totalCharged, 9),
            ],
        ]);
    }

    public function create(): Response
    {
        $pricing = null;
        $pricingError = null;

        try {
            $pricing = $this->pricing->get();
        } catch (Throwable $exception) {
            report($exception);
            $pricingError = 'Pixelforge pricing is temporarily unavailable, so new generations are paused. Please try again shortly.';
        }

        $estimate = $pricing !== null
            ? $this->estimator->estimate($pricing, '', '1024x1024', 'medium', 0)
            : null;

        return Inertia::render('Generate', [
            'pricing' => $pricing !== null ? [
                'rates' => $pricing['rates'],
                'tokens' => $pricing['tokens'],
            ] : null,
            'pricing_error' => $pricingError,
            'initial_estimate' => $estimate?->toArray(),
            'defaults' => [
                'size' => '1024x1024',
                'quality' => 'medium',
                'prompt' => '',
            ],
            'markup_percent' => (int) config('services.pixelforge.markup_basis_points', 2500) / 100,
        ]);
    }

    public function estimate(EstimateGenerationRequest $request): JsonResponse
    {
        try {
            $pricing = $this->pricing->get();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Pixelforge pricing is temporarily unavailable. Please try again shortly.',
            ], 503);
        }

        $validated = $request->validated();
        $estimate = $this->estimator->estimate(
            pricing: $pricing,
            prompt: $validated['prompt'] ?? '',
            size: $validated['size'],
            quality: $validated['quality'],
            referenceImages: count($validated['reference_images'] ?? []),
        );

        return response()->json(['estimate' => $estimate->toArray()]);
    }

    public function store(StoreGenerationRequest $request, GenerationService $service): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $generation = $service->create($request->user(), [
                'prompt' => $validated['prompt'],
                'size' => $validated['size'],
                'quality' => $validated['quality'],
                'reference_images' => $validated['reference_images'] ?? [],
            ]);
        } catch (PixelforgeException|ConnectionException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'prompt' => 'Pixelforge is unavailable right now, so no credits were held. Please try again shortly.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Generation queued. It will appear below as it runs.',
        ]);

        Log::info('Generation queued.', [
            'generation_id' => $generation->id,
            'user_id' => $generation->user_id,
            'estimated_charge' => Money::format($generation->estimated_charge),
            'held' => Money::format($generation->held_amount),
        ]);

        return to_route('generations.index');
    }
}
