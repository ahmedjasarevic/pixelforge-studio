<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;

/**
 * A tiny double-entry-ish wallet. The balance on `users` is a cache of the
 * ledger; every movement is also written to credit_transactions so the history
 * is auditable and duplicate settlements are rejected by a unique index.
 *
 * All methods must be called inside a database transaction.
 */
class WalletService
{
    public function purchase(User $user, int $picodollars, string $description): CreditTransaction
    {
        return $this->apply(
            userId: $user->id,
            amount: $picodollars,
            type: CreditTransactionType::Purchase,
            generationId: null,
            description: $description,
        );
    }

    public function hold(User $user, Generation $generation, int $picodollars): CreditTransaction
    {
        return $this->apply(
            userId: $user->id,
            amount: -$picodollars,
            type: CreditTransactionType::Hold,
            generationId: $generation->id,
            description: 'Hold for generation #'.$generation->id,
        );
    }

    /**
     * Replace the hold with the final charge. A positive adjustment refunds the
     * unused part of the hold; a negative adjustment tops it up if the actual
     * cost exceeded our estimate.
     */
    public function settle(
        int $userId,
        int $generationId,
        int $adjustment,
        int $chargedPicodollars,
    ): CreditTransaction {
        $description = $chargedPicodollars > 0
            ? 'Generation #'.$generationId.' cost '.Money::format($chargedPicodollars, 9)
            : 'Hold released for failed generation #'.$generationId;

        return $this->apply(
            userId: $userId,
            amount: $adjustment,
            type: CreditTransactionType::Settlement,
            generationId: $generationId,
            description: $description,
        );
    }

    private function apply(
        int $userId,
        int $amount,
        CreditTransactionType $type,
        ?int $generationId,
        string $description,
    ): CreditTransaction {
        $user = User::query()->lockForUpdate()->findOrFail($userId);
        $newBalance = $user->credit_balance_picodollars + $amount;

        $transaction = CreditTransaction::create([
            'user_id' => $userId,
            'generation_id' => $generationId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'description' => $description,
        ]);

        $user->forceFill(['credit_balance_picodollars' => $newBalance])->save();

        return $transaction;
    }
}
