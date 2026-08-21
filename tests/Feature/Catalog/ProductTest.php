<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;

it('always owns at least one variant', function (): void {
    // The invariant the whole schema leans on: price is reachable without
    // ever asking what type the product is.
    $product = Product::factory()->create();

    expect($product->variants()->count())->toBe(1)
        ->and($product->variants()->where('is_default', true)->exists())->toBeTrue();
});

it('opens on the default variant', function (): void {
    $product = Product::factory()->withVariants(3)->create()->load('variants');

    expect($product->variants)->toHaveCount(3)
        ->and($product->defaultVariant())->not->toBeNull()
        ->and($product->defaultVariant()?->is_default)->toBeTrue();
});

it('falls back to the first variant when none is marked default', function (): void {
    $product = Product::factory()->create();
    $product->variants()->update(['is_default' => false]);

    $product = $product->fresh()?->load('variants');

    expect($product?->defaultVariant())->not->toBeNull();
});

it('reports the lowest variant price for the listing card', function (): void {
    $product = Product::factory()->create();
    $product->variants()->delete();

    ProductVariant::factory()->priced(2_500)->create(['product_id' => $product->id]);
    ProductVariant::factory()->priced(1_800)->create(['product_id' => $product->id]);
    ProductVariant::factory()->priced(4_000)->create(['product_id' => $product->id]);

    expect($product->load('variants')->lowestPrice())->toBeMoney(180_000);
});

it('has no price when it has no variants', function (): void {
    $product = Product::factory()->create();
    $product->variants()->delete();

    expect($product->load('variants')->lowestPrice())->toBeNull();
});

it('is purchasable only when active with sellable stock', function (): void {
    $product = Product::factory()->create();
    $product->variants()->delete();
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 5]);

    expect($product->load('variants')->isPurchasable())->toBeTrue();

    $product->update(['status' => ProductStatus::Draft]);
    expect($product->fresh()?->load('variants')->isPurchasable())->toBeFalse();
});

it('is not purchasable when every variant is out of stock', function (): void {
    $product = Product::factory()->create();
    $product->variants()->delete();
    ProductVariant::factory()->outOfStock()->create(['product_id' => $product->id]);

    expect($product->load('variants')->isPurchasable())->toBeFalse();
});

it('belongs to a brand and to many categories', function (): void {
    $brand = Brand::factory()->create();
    $product = Product::factory()->forBrand($brand)->create();
    $categories = Category::factory()->count(2)->create();

    $product->categories()->attach($categories->pluck('id'));

    $product = $product->load(['brand', 'categories']);

    expect($product->brand?->id)->toBe($brand->id)
        ->and($product->categories)->toHaveCount(2)
        ->and($brand->products()->count())->toBe(1);
});

it('carries spec-sheet attribute values independently of its variants', function (): void {
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);
    $product = Product::factory()->create();

    $product->attributeValues()->attach($value->id);

    expect($product->load('attributeValues')->attributeValues)->toHaveCount(1)
        ->and($value->products()->count())->toBe(1);
});

it('scopes to active and featured products', function (): void {
    $active = Product::factory()->featured()->create();
    $draft = Product::factory()->draft()->create();
    $archived = Product::factory()->archived()->create();

    expect(Product::query()->active()->pluck('id'))
        ->toContain($active->id)
        ->not->toContain($draft->id, $archived->id);

    expect(Product::query()->featured()->pluck('id'))->toContain($active->id);
});

it('soft deletes so historical orders keep resolving', function (): void {
    $product = Product::factory()->create();

    $product->delete();

    expect(Product::query()->find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('routes by slug', function (): void {
    expect(Product::factory()->create()->getRouteKeyName())->toBe('slug');
});

it('opens on the requested variant when one is asked for', function (): void {
    $product = Product::factory()->withVariants(3)->create()->load('variants');
    $wanted = $product->variants->last();

    $this->withoutVite()
        ->get(route('product', $product).'?variant='.$wanted?->id)
        ->assertOk()
        ->assertSee($wanted?->sku, escape: false);
});

it('ignores a variant belonging to a different product', function (): void {
    // Without this check a crafted ?variant= would render another product's
    // price and SKU under this product's name — and the JSON-LD would
    // publish it to Google.
    $product = Product::factory()->create()->load('variants');
    $foreign = ProductVariant::factory()->create();

    $this->withoutVite()
        ->get(route('product', $product).'?variant='.$foreign->id)
        ->assertOk()
        ->assertSee($product->defaultVariant()?->sku, escape: false)
        ->assertDontSee($foreign->sku, escape: false);
});

it('ignores a nonsense variant parameter', function (string $value): void {
    $product = Product::factory()->create()->load('variants');

    $this->withoutVite()
        ->get(route('product', $product).'?variant='.$value)
        ->assertOk()
        ->assertSee($product->defaultVariant()?->sku, escape: false);
})->with(['abc', '-1', '0', '999999']);
