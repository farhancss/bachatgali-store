<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });

        // Spec-sheet attributes: true of the product however you configure it.
        Schema::create('attribute_value_product', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'attribute_value_id'], 'attr_value_product_primary');
            $table->index('attribute_value_id');
        });

        // Variant axes: the combination that identifies this specific SKU.
        Schema::create('attribute_value_product_variant', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_variant_id', 'attribute_value_id'], 'attr_value_variant_primary');
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_product_variant');
        Schema::dropIfExists('attribute_value_product');
        Schema::dropIfExists('category_product');
    }
};
