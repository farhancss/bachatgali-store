<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The listing page's one query.
 *
 * Price lives on variants, so every price filter and price sort goes through
 * an aggregate over the product's variants rather than a column on products.
 * `withMin` compiles to a correlated subquery on both PostgreSQL and SQLite,
 * which is what keeps the test suite service-free.
 */
final readonly class ProductListQuery
{
    public function __construct(private ProductFilters $filters) {}

    /** @return LengthAwarePaginator<int, Product> */
    public function paginate(int $perPage = 24): LengthAwarePaginator
    {
        return $this->builder()->paginate($perPage)->withQueryString();
    }

    /** @return Builder<Product> */
    public function builder(): Builder
    {
        $query = Product::query()
            ->active()
            ->with(['brand', 'variants'])
            ->withMin('variants', 'price');

        $this->applyCategory($query);
        $this->applyBrands($query);
        $this->applyAttributes($query);
        $this->applyPriceRange($query);
        $this->applyAvailability($query);
        $this->applySearch($query);
        $this->applySort($query);

        return $query;
    }

    /** @param Builder<Product> $query */
    private function applyCategory(Builder $query): void
    {
        if ($this->filters->categorySlug === null) {
            return;
        }

        $category = Category::query()->where('slug', $this->filters->categorySlug)->first();

        if (! $category instanceof Category) {
            // An unknown category must return nothing rather than everything.
            $query->whereRaw('1 = 0');

            return;
        }

        // The category itself plus every descendant, via the materialised path.
        $ids = Category::query()
            ->descendantsOf($category)
            ->pluck('id')
            ->push($category->id)
            ->all();

        $query->whereHas('categories', function (Builder $q) use ($ids): void {
            $q->whereIn('categories.id', $ids);
        });
    }

    /** @param Builder<Product> $query */
    private function applyBrands(Builder $query): void
    {
        if ($this->filters->brandSlugs === []) {
            return;
        }

        $query->whereHas('brand', function (Builder $q): void {
            $q->whereIn('slug', $this->filters->brandSlugs);
        });
    }

    /**
     * Values within one attribute are an OR; across attributes they are an
     * AND. "Red or blue, in medium" is the behaviour shoppers expect, and
     * intersecting everything would return almost nothing.
     *
     * @param  Builder<Product>  $query
     */
    private function applyAttributes(Builder $query): void
    {
        if ($this->filters->attributeValues === []) {
            return;
        }

        $query->whereHas('variants', function (Builder $q): void {
            $q->whereHas('attributeValues', function (Builder $values): void {
                $values->whereIn('attribute_values.value', $this->filters->attributeValues);
            });
        });
    }

    /** @param Builder<Product> $query */
    private function applyPriceRange(Builder $query): void
    {
        if ($this->filters->minPrice instanceof Money) {
            $query->whereHas('variants', function (Builder $q): void {
                $q->where('price', '>=', $this->filters->minPrice?->paisa);
            });
        }

        if ($this->filters->maxPrice instanceof Money) {
            $query->whereHas('variants', function (Builder $q): void {
                $q->where('price', '<=', $this->filters->maxPrice?->paisa);
            });
        }
    }

    /** @param Builder<Product> $query */
    private function applyAvailability(Builder $query): void
    {
        if ($this->filters->inStockOnly) {
            $query->whereHas('variants', function (Builder $q): void {
                $q->where('stock_quantity', '>', 0);
            });
        }

        if ($this->filters->onSaleOnly) {
            $query->whereHas('variants', function (Builder $q): void {
                $q->whereNotNull('compare_at_price')
                    ->whereColumn('compare_at_price', '>', 'price');
            });
        }
    }

    /** @param Builder<Product> $query */
    private function applySearch(Builder $query): void
    {
        $term = $this->filters->search;

        if ($term === null || trim($term) === '') {
            return;
        }

        // Deliberately naive: Typesense takes this over in phase 2. This
        // exists so the listing page works before the search engine does.
        $like = '%'.str_replace('%', '\%', trim($term)).'%';

        // Case-insensitive explicitly: Postgres LIKE is case-sensitive while
        // SQLite's is not, so `like` would work in tests and fail in production.
        $query->where(function (Builder $q) use ($like): void {
            $q->whereLike('name', $like, caseSensitive: false)
                ->orWhereLike('short_description', $like, caseSensitive: false);
        });
    }

    /** @param Builder<Product> $query */
    private function applySort(Builder $query): void
    {
        match ($this->filters->sort) {
            ProductSort::Newest => $query->orderByDesc('published_at')->orderByDesc('id'),
            ProductSort::PriceLowToHigh => $query->orderBy('variants_min_price')->orderBy('id'),
            ProductSort::PriceHighToLow => $query->orderByDesc('variants_min_price')->orderBy('id'),
            ProductSort::Discount => $query->orderByDesc('is_featured')->orderByDesc('published_at'),
            ProductSort::Relevance => $query->orderByDesc('is_featured')->orderBy('position')->orderBy('id'),
        };
    }
}
