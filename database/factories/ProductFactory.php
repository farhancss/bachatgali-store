<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'brand_id' => null,
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'is_featured' => false,
            'position' => 0,
            'published_at' => now(),
        ];
    }

    /**
     * Every product owns at least one variant — that invariant is what lets
     * the rest of the system read a price without asking the product type.
     * The factory honours it so tests never build an impossible product.
     */
    public function configure(): self
    {
        return $this->afterCreating(function (Product $product): void {
            if (! $product->variants()->exists()) {
                ProductVariantFactory::new()
                    ->state(['product_id' => $product->id, 'is_default' => true])
                    ->create();
            }
        });
    }

    /**
     * Replaces the auto-created default variant rather than adding to it.
     *
     * configure()'s callback is registered first and therefore runs first, so
     * topping up would leave $count + 1 variants. Clearing and recreating
     * keeps the count exactly what the test asked for.
     */
    public function withVariants(int $count): self
    {
        return $this->state(fn (): array => ['type' => ProductType::Variable])
            ->afterCreating(function (Product $product) use ($count): void {
                $product->variants()->delete();

                for ($i = 0; $i < $count; $i++) {
                    ProductVariantFactory::new()
                        ->state([
                            'product_id' => $product->id,
                            'is_default' => $i === 0,
                            'position' => $i,
                        ])
                        ->create();
                }
            });
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Archived]);
    }

    public function featured(): self
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function forBrand(Brand $brand): self
    {
        return $this->state(fn (): array => ['brand_id' => $brand->id]);
    }
}
