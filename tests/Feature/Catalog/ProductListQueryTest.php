<?php

declare(strict_types=1);

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Queries\ProductListQuery;
use App\Domain\Catalog\Queries\RelatedProductsQuery;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Collection;

function listing(ProductFilters $filters): Collection
{
    return new ProductListQuery($filters)->paginate(50)->getCollection()->pluck('slug');
}

function pricedProduct(string $name, int $rupees, ?int $wasRupees = null, int $stock = 10): Product
{
    $product = Product::factory()->create(['name' => $name, 'slug' => Str::slug($name)]);
    $product->variants()->delete();

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => Money::fromRupees($rupees),
        'compare_at_price' => $wasRupees === null ? null : Money::fromRupees($wasRupees),
        'stock_quantity' => $stock,
        'is_default' => true,
    ]);

    return $product->refresh();
}

it('shows only active products', function (): void {
    pricedProduct('Visible', 100);
    Product::factory()->draft()->create(['name' => 'Hidden', 'slug' => 'hidden']);
    Product::factory()->archived()->create(['name' => 'Gone', 'slug' => 'gone']);

    expect(listing(ProductFilters::none()))->toContain('visible')
        ->not->toContain('hidden', 'gone');
});

it('includes products in descendant categories, not just the exact one', function (): void {
    $root = Category::factory()->create(['slug' => 'clothing']);
    $child = Category::factory()->childOf($root)->create();

    $deep = pricedProduct('Deep', 100);
    $deep->categories()->attach($child->id);
    pricedProduct('Elsewhere', 100);

    expect(listing(new ProductFilters(categorySlug: 'clothing')))
        ->toContain('deep')
        ->not->toContain('elsewhere');
});

it('returns nothing rather than everything for an unknown category', function (): void {
    pricedProduct('Anything', 100);

    expect(listing(new ProductFilters(categorySlug: 'no-such-category')))->toBeEmpty();
});

it('filters by brand slug', function (): void {
    $brand = Brand::factory()->create(['slug' => 'khaadi']);
    $mine = pricedProduct('Mine', 100);
    $mine->update(['brand_id' => $brand->id]);
    pricedProduct('Theirs', 100);

    expect(listing(new ProductFilters(brandSlugs: ['khaadi'])))
        ->toContain('mine')
        ->not->toContain('theirs');
});

it('filters on the variant price, not a product column', function (): void {
    pricedProduct('Cheap', 500);
    pricedProduct('Dear', 9_000);

    expect(listing(new ProductFilters(maxPrice: Money::fromRupees(1_000))))
        ->toContain('cheap')
        ->not->toContain('dear');

    expect(listing(new ProductFilters(minPrice: Money::fromRupees(5_000))))
        ->toContain('dear')
        ->not->toContain('cheap');
});

it('sorts by the cheapest variant in both directions', function (): void {
    pricedProduct('Mid', 500);
    pricedProduct('Low', 100);
    pricedProduct('High', 900);

    expect(listing(new ProductFilters(sort: ProductSort::PriceLowToHigh))->all())
        ->toBe(['low', 'mid', 'high']);

    expect(listing(new ProductFilters(sort: ProductSort::PriceHighToLow))->all())
        ->toBe(['high', 'mid', 'low']);
});

it('sorts a variable product by its lowest variant, not its first', function (): void {
    $cheapSingle = pricedProduct('Single', 400);
    $variable = pricedProduct('Variable', 900);
    ProductVariant::factory()->priced(100)->create(['product_id' => $variable->id]);

    expect(listing(new ProductFilters(sort: ProductSort::PriceLowToHigh))->all())
        ->toBe(['variable', 'single'])
        ->and($cheapSingle->slug)->toBe('single');
});

it('filters to items with stock', function (): void {
    pricedProduct('Available', 100, stock: 5);
    pricedProduct('Sold out', 100, stock: 0);

    expect(listing(new ProductFilters(inStockOnly: true)))
        ->toContain('available')
        ->not->toContain('sold-out');
});

it('filters to genuine reductions only', function (): void {
    pricedProduct('Reduced', 800, wasRupees: 1_000);
    pricedProduct('Full price', 800);
    pricedProduct('Fake sale', 800, wasRupees: 600);

    expect(listing(new ProductFilters(onSaleOnly: true)))
        ->toContain('reduced')
        ->not->toContain('full-price', 'fake-sale');
});

it('searches name and short description', function (): void {
    pricedProduct('Electric Kettle', 100);
    pricedProduct('Frying Pan', 100);

    expect(listing(new ProductFilters(search: 'kettle')))
        ->toContain('electric-kettle')
        ->not->toContain('frying-pan');
});

it('combines filters as an AND', function (): void {
    $brand = Brand::factory()->create(['slug' => 'servis']);
    $match = pricedProduct('Match', 500, wasRupees: 900, stock: 3);
    $match->update(['brand_id' => $brand->id]);

    $wrongBrand = pricedProduct('Wrong brand', 500, wasRupees: 900, stock: 3);

    expect(listing(new ProductFilters(brandSlugs: ['servis'], inStockOnly: true, onSaleOnly: true)))
        ->toContain('match')
        ->not->toContain($wrongBrand->slug);
});

it('suggests related products from shared categories first', function (): void {
    $category = Category::factory()->create();
    $subject = pricedProduct('Subject', 100);
    $sibling = pricedProduct('Sibling', 100);
    $stranger = pricedProduct('Stranger', 100);

    $subject->categories()->attach($category->id);
    $sibling->categories()->attach($category->id);

    $related = new RelatedProductsQuery($subject)->get(6)->pluck('slug');

    expect($related->first())->toBe('sibling')
        ->and($related)->not->toContain('subject')
        ->and($stranger->slug)->toBe('stranger');
});

it('never shows an empty related rail', function (): void {
    // An uncategorised product still needs something under it.
    $orphan = pricedProduct('Orphan', 100);
    pricedProduct('Something else', 100);

    expect(new RelatedProductsQuery($orphan)->get(6))->not->toBeEmpty();
});
