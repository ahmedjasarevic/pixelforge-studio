<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseCreditsRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Models\User;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = $request->user()
            ->creditTransactions()
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('Credits', [
            'transactions' => CreditTransactionResource::collection($transactions)->resolve(),
            'purchaseOptions' => [5, 10, 25, 50],
        ]);
    }

    public function purchase(PurchaseCreditsRequest $request, WalletService $wallet): RedirectResponse
    {
        $picodollars = Money::fromUsd((string) $request->validated('amount'));

        DB::transaction(function () use ($request, $wallet, $picodollars) {
            /** @var User $user */
            $user = $request->user();

            $wallet->purchase(
                user: $user,
                picodollars: $picodollars,
                description: 'Demo credit purchase',
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Money::format($picodollars).' of credits added to your balance.',
        ]);

        return back();
    }
}
