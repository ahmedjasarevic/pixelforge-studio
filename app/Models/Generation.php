<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $pixelforge_id
 * @property string $idempotency_key
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property array<int, string> $reference_images
 * @property GenerationStatus $status
 * @property int $estimated_provider_cost
 * @property int $max_provider_cost
 * @property int $estimated_charge
 * @property int $max_charge
 * @property int $held_amount
 * @property int|null $provider_cost
 * @property int|null $charged_amount
 * @property array<string, string>|null $rates
 * @property array<string, int>|null $usage
 * @property array<string, mixed>|null $estimate_breakdown
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property Carbon|null $submitted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'pixelforge_id',
    'idempotency_key',
    'prompt',
    'size',
    'quality',
    'reference_images',
    'status',
    'estimated_provider_cost',
    'max_provider_cost',
    'estimated_charge',
    'max_charge',
    'held_amount',
    'provider_cost',
    'charged_amount',
    'rates',
    'usage',
    'estimate_breakdown',
    'image_url',
    'image_url_expires_at',
    'error_code',
    'error_message',
    'submit_attempts',
    'submitted_at',
    'started_at',
    'completed_at',
    'settled_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimate_breakdown' => 'array',
            'estimated_provider_cost' => 'integer',
            'max_provider_cost' => 'integer',
            'estimated_charge' => 'integer',
            'max_charge' => 'integer',
            'held_amount' => 'integer',
            'provider_cost' => 'integer',
            'charged_amount' => 'integer',
            'submit_attempts' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
