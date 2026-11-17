<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('pixelforge_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();

            $table->text('prompt');
            $table->string('size', 16);
            $table->string('quality', 16);
            $table->json('reference_images');

            $table->string('status', 20)->default('queued');

            // Prices in picodollars. The estimate is what we show before submit;
            // the max is the documented worst case and the amount we hold.
            $table->unsignedBigInteger('estimated_provider_cost');
            $table->unsignedBigInteger('max_provider_cost');
            $table->unsignedBigInteger('estimated_charge');
            $table->unsignedBigInteger('max_charge');
            $table->unsignedBigInteger('held_amount');

            $table->unsignedBigInteger('provider_cost')->nullable();
            $table->unsignedBigInteger('charged_amount')->nullable();

            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->json('estimate_breakdown')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedInteger('submit_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
