<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('type')->default('percentage_off');

            // Money is bigInteger paisa; percentage is a whole number 1-100.
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->bigInteger('maximum_discount')->nullable();
            $table->bigInteger('minimum_spend')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('times_used')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
