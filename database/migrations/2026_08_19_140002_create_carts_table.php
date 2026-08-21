<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            // Guests are the norm on a COD storefront — most customers never
            // create an account — so the session owns the cart and user_id is
            // filled in later if they do sign in.
            $table->string('session_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('voucher_code')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            // The price when it went in the cart. Re-validated at checkout:
            // a price that moves mid-session must not silently change what
            // the customer agreed to, and must not let a stale cart buy at
            // yesterday's price either.
            $table->bigInteger('unit_price');

            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
