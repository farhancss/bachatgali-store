<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every money column is a bigInteger of PAISA and is cast with MoneyCast.
     * No decimals, no floats — see App\Domain\Shared\ValueObjects\Money and
     * the architecture test that fails the build on float arithmetic here.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->string('name')->nullable();

            $table->bigInteger('price')->default(0);
            $table->bigInteger('compare_at_price')->nullable();
            $table->bigInteger('cost')->nullable();

            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->boolean('backorder_allowed')->default(false);
            $table->boolean('is_pre_order')->default(false);

            $table->unsignedInteger('weight_grams')->default(0);
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();

            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
            $table->index(['product_id', 'is_default']);
            $table->index('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
