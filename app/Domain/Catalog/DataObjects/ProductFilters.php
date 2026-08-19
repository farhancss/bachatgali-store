<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DataObjects;

use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Everything a listing page can be narrowed by, as one immutable object.
 *
 * The controller turns the request into this and hands it to the query; the
 * query never sees a Request. That is what keeps ProductListQuery a plain
 * unit test with no HTTP and no container.
 */
final readonly class ProductFilters
{
    /**
     * @param  list<string>  $brandSlugs
     * @param  list<string>  $attributeValues  Attribute value ids or slugs to intersect on.
     */
    public function __construct(
        public ?string $categorySlug = null,
        public array $brandSlugs = [],
        public array $attributeValues = [],
        public ?Money $minPrice = null,
        public ?Money $maxPrice = null,
        public bool $inStockOnly = false,
        public bool $onSaleOnly = false,
        public ProductSort $sort = ProductSort::Relevance,
        public ?string $search = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public function withCategory(string $slug): self
    {
        return new self(
            categorySlug: $slug,
            brandSlugs: $this->brandSlugs,
            attributeValues: $this->attributeValues,
            minPrice: $this->minPrice,
            maxPrice: $this->maxPrice,
            inStockOnly: $this->inStockOnly,
            onSaleOnly: $this->onSaleOnly,
            sort: $this->sort,
            search: $this->search,
        );
    }

    public function withSort(ProductSort $sort): self
    {
        return new self(
            categorySlug: $this->categorySlug,
            brandSlugs: $this->brandSlugs,
            attributeValues: $this->attributeValues,
            minPrice: $this->minPrice,
            maxPrice: $this->maxPrice,
            inStockOnly: $this->inStockOnly,
            onSaleOnly: $this->onSaleOnly,
            sort: $sort,
            search: $this->search,
        );
    }

    /** Is anything actually narrowing the list? Drives the "clear all" chip. */
    public function isEmpty(): bool
    {
        return $this->brandSlugs === []
            && $this->attributeValues === []
            && ! $this->minPrice instanceof Money
            && ! $this->maxPrice instanceof Money
            && ! $this->inStockOnly
            && ! $this->onSaleOnly
            && ($this->search === null || $this->search === '');
    }

    public function activeCount(): int
    {
        return count($this->brandSlugs)
            + count($this->attributeValues)
            + ($this->minPrice instanceof Money || $this->maxPrice instanceof Money ? 1 : 0)
            + ($this->inStockOnly ? 1 : 0)
            + ($this->onSaleOnly ? 1 : 0);
    }
}
