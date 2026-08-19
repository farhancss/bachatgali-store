<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'barcode' => null,
            'name' => null,
            'price' => Money::fromRupees(fake()->numberBetween(200, 15_000)),
            'compare_at_price' => null,
            'cost' => null,
            'stock_quantity' => fake()->numberBetween(5, 200),
            'low_stock_threshold' => 5,
            'backorder_allowed' => false,
            'is_pre_order' => false,
            'weight_grams' => fake()->numberBetween(100, 3_000),
            'is_default' => false,
            'position' => 0,
        ];
    }

    public function priced(int $rupees): self
    {
        return $this->state(fn (): array => ['price' => Money::fromRupees($rupees)]);
    }

    public function onSale(int $fromRupees, int $toRupees): self
    {
        return $this->state(fn (): array => [
            'price' => Money::fromRupees($toRupees),
            'compare_at_price' => Money::fromRupees($fromRupees),
        ]);
    }

    public function outOfStock(): self
    {
        return $this->state(fn (): array => ['stock_quantity' => 0]);
    }

    public function lowStock(): self
    {
        return $this->state(fn (): array => [
            'stock_quantity' => 2,
            'low_stock_threshold' => 5,
        ]);
    }

    public function backorderable(): self
    {
        return $this->state(fn (): array => [
            'stock_quantity' => 0,
            'backorder_allowed' => true,
        ]);
    }

    public function preOrder(): self
    {
        return $this->state(fn (): array => ['is_pre_order' => true]);
    }
}
