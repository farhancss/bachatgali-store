<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products carry identity and copy. They deliberately carry no price and
     * no stock — those live on product_variants, and every product owns at
     * least one variant. One place to read a price from means cart, checkout
     * and search never have to branch on product type to find it.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('simple');
            $table->string('status')->default('draft');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['brand_id', 'status']);
            $table->index(['is_featured', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
