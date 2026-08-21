<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();

        return [
            'cart_id' => Cart::factory(),
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => $variant->price,
        ];
    }
}
