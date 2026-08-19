<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogSeeder;

/*
| The demo catalog is what local development and staging browse, so it is
| worth asserting it is actually browsable. The first version of this seeder
| left every product at the migration's `draft` default, which produced a
| catalog that looked fine in the database and rendered as nothing at all.
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
});

it('seeds a catalog that is visible to customers', function (): void {
    expect(Product::query()->count())->toBeGreaterThan(0)
        ->and(Product::query()->active()->count())->toBe(Product::query()->count());
});

it('gives every seeded product at least one variant and a price', function (): void {
    Product::query()->with('variants')->get()->each(function (Product $product): void {
        expect($product->variants)->not->toBeEmpty()
            ->and($product->lowestPrice())->not->toBeNull()
            ->and($product->defaultVariant())->not->toBeNull();
    });
});

it('seeds a spread of stock states so both paths are visible', function (): void {
    $products = Product::query()->with('variants')->get();

    $purchasable = $products->filter(fn (Product $p): bool => $p->isPurchasable());

    expect($purchasable)->not->toBeEmpty()
        ->and($purchasable->count())->toBeLessThan($products->count());
});

it('seeds a two-level category tree with working paths', function (): void {
    $roots = Category::query()->roots()->get();

    expect($roots)->not->toBeEmpty();

    $clothing = Category::query()->where('slug', 'clothing')->firstOrFail();

    expect(Category::query()->descendantsOf($clothing)->count())->toBeGreaterThan(0)
        ->and($clothing->depth)->toBe(0);
});

it('builds the variable product from real variant axes', function (): void {
    $product = Product::query()
        ->with('variants.attributeValues')
        ->where('slug', 'unstitched-lawn-suit')
        ->firstOrFail();

    expect($product->variants->count())->toBeGreaterThan(1)
        ->and($product->defaultVariant()?->attributeValues)->not->toBeEmpty()
        ->and($product->isPurchasable())->toBeTrue();
});
