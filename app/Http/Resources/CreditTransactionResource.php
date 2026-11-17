<?php

namespace App\Http\Resources;

use App\Models\CreditTransaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'amount_formatted' => ($this->amount >= 0 ? '+' : '').Money::format($this->amount, 9),
            'balance_after_formatted' => Money::format($this->balance_after, 9),
            'description' => $this->description,
            'generation_id' => $this->generation_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
