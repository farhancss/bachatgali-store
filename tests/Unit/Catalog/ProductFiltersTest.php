<?php

declare(strict_types=1);

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Shared\ValueObjects\Money;

it('starts with nothing applied', function (): void {
    $filters = ProductFilters::none();

    expect($filters->isEmpty())->toBeTrue()
        ->and($filters->activeCount())->toBe(0)
        ->and($filters->sort)->toBe(ProductSort::Relevance);
});

it('does not count a category or a sort as an active filter', function (): void {
    // Both are navigation, not narrowing — showing "Clear all (1)" on a plain
    // category page would be nonsense.
    $filters = ProductFilters::none()->withCategory('clothing')->withSort(ProductSort::Newest);

    expect($filters->isEmpty())->toBeTrue()
        ->and($filters->activeCount())->toBe(0)
        ->and($filters->categorySlug)->toBe('clothing')
        ->and($filters->sort)->toBe(ProductSort::Newest);
});

it('counts a price range once however many bounds are set', function (): void {
    $min = new ProductFilters(minPrice: Money::fromRupees(500));
    $both = new ProductFilters(minPrice: Money::fromRupees(500), maxPrice: Money::fromRupees(5_000));

    expect($min->activeCount())->toBe(1)
        ->and($both->activeCount())->toBe(1)
        ->and($both->isEmpty())->toBeFalse();
});

it('counts each brand and attribute separately', function (): void {
    $filters = new ProductFilters(
        brandSlugs: ['khaadi', 'servis'],
        attributeValues: ['black'],
        inStockOnly: true,
        onSaleOnly: true,
    );

    expect($filters->activeCount())->toBe(5)
        ->and($filters->isEmpty())->toBeFalse();
});

it('treats an empty search string as no search', function (): void {
    expect(new ProductFilters(search: '')->isEmpty())->toBeTrue()
        ->and(new ProductFilters(search: 'kettle')->isEmpty())->toBeFalse();
});

it('preserves every other filter when narrowing to a category', function (): void {
    $original = new ProductFilters(
        brandSlugs: ['khaadi'],
        minPrice: Money::fromRupees(100),
        inStockOnly: true,
        sort: ProductSort::PriceLowToHigh,
        search: 'lawn',
    );

    $narrowed = $original->withCategory('women');

    expect($narrowed->brandSlugs)->toBe(['khaadi'])
        ->and($narrowed->minPrice)->toBeMoney(10_000)
        ->and($narrowed->inStockOnly)->toBeTrue()
        ->and($narrowed->sort)->toBe(ProductSort::PriceLowToHigh)
        ->and($narrowed->search)->toBe('lawn');
});

it('falls back to relevance for an unknown sort in the URL', function (): void {
    expect(ProductSort::fromRequest('nonsense'))->toBe(ProductSort::Relevance)
        ->and(ProductSort::fromRequest(null))->toBe(ProductSort::Relevance)
        ->and(ProductSort::fromRequest('price-asc'))->toBe(ProductSort::PriceLowToHigh);
});

it('knows which sorts need the variant price aggregate', function (ProductSort $sort, bool $usesPrice): void {
    expect($sort->usesVariantPrice())->toBe($usesPrice);
})->with([
    'low to high' => [ProductSort::PriceLowToHigh, true],
    'high to low' => [ProductSort::PriceHighToLow, true],
    'newest' => [ProductSort::Newest, false],
    'relevance' => [ProductSort::Relevance, false],
    'discount' => [ProductSort::Discount, false],
]);

it('labels every sort and gives it a direction', function (ProductSort $sort): void {
    expect($sort->label())->not->toBeEmpty()
        ->and($sort->direction())->toBeIn(['asc', 'desc']);
})->with(ProductSort::cases());
