<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Fake;

use App\Domain\Catalog\DataObjects\SearchHits;
use App\Domain\Catalog\DataObjects\SearchQuery;
use App\Domain\Catalog\Models\Product;
use App\Infrastructure\Search\Contracts\SearchEngine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Database-backed search. Used by the whole test suite, by local development,
 * and — deliberately — as the production fallback when the search service is
 * unreachable.
 *
 * A storefront whose search box returns an error page loses the sale. Slightly
 * worse results do not. `usedFallback` on the result tells the caller which
 * happened so it can be logged and alerted on.
 */
final class FakeSearchEngine implements SearchEngine
{
    /** @var array<int, int> */
    public array $indexed = [];

    public bool $flushed = false;

    public function identifier(): string
    {
        return 'database';
    }

    public function search(SearchQuery $query): SearchHits
    {
        $builder = Product::query()->active();

        if ($query->hasTerm()) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($query->term)).'%';

            // whereLike(caseSensitive: false), not `like`. Postgres LIKE is
            // case-sensitive and SQLite's is not, so a plain `like` passes
            // every test and then finds nothing in production.
            $builder->where(function (Builder $q) use ($like): void {
                $q->whereLike('name', $like, caseSensitive: false)
                    ->orWhereLike('short_description', $like, caseSensitive: false)
                    ->orWhereHas('variants', fn (Builder $v): Builder => $v->whereLike('sku', $like, caseSensitive: false));
            });
        }

        if ($query->facetFilters !== []) {
            $builder->where(function (Builder $q) use ($query): void {
                $q->whereHas('brand', fn (Builder $b): Builder => $b->whereIn('slug', $query->facetFilters))
                    ->orWhereHas(
                        'variants.attributeValues',
                        fn (Builder $v): Builder => $v->whereIn('value', $query->facetFilters),
                    );
            });
        }

        $total = (clone $builder)->count();

        /** @var list<int> $ids */
        $ids = $builder
            ->orderByDesc('is_featured')
            ->orderBy('position')
            ->orderBy('id')
            ->skip($query->offset())
            ->take($query->perPage)
            ->pluck('id')
            ->all();

        return new SearchHits($ids, $total, $this->facetCounts(), usedFallback: true);
    }

    /** @param array<int, int> $ids */
    public function index(array $ids): void
    {
        foreach ($ids as $id) {
            $this->indexed[$id] = $id;
        }
    }

    /** @param array<int, int> $ids */
    public function forget(array $ids): void
    {
        foreach ($ids as $id) {
            unset($this->indexed[$id]);
        }
    }

    public function flush(): void
    {
        $this->indexed = [];
        $this->flushed = true;
    }

    public function supportsFacets(): bool
    {
        return true;
    }

    /** @return array<string, array<string, int>> */
    private function facetCounts(): array
    {
        /** @var array<string, int> $brands */
        $brands = Product::query()
            ->active()
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->selectRaw('brands.slug as slug, count(*) as total')
            ->groupBy('brands.slug')
            ->pluck('total', 'slug')
            ->map(fn (mixed $n): int => (int) $n)
            ->all();

        return ['brand' => $brands];
    }
}
