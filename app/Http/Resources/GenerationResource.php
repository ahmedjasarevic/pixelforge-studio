<?php

namespace App\Http\Resources;

use App\Models\Generation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images ?? [],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_final' => $this->status->isFinal(),
            'image_url' => $this->image_url,
            'image_url_expires_at' => $this->image_url_expires_at?->toIso8601String(),
            'estimated_charge' => $this->estimated_charge,
            'estimated_charge_formatted' => Money::format($this->estimated_charge),
            'max_charge' => $this->max_charge,
            'max_charge_formatted' => Money::format($this->max_charge),
            'charged_amount' => $this->charged_amount,
            'charged_amount_formatted' => $this->charged_amount !== null
                ? Money::format($this->charged_amount, 9)
                : null,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'usage' => $this->usage,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
