<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\DataObjects\SearchQuery;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Queries\ProductListQuery;
use App\Infrastructure\Search\Contracts\SearchEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Resolves a listing page to products, choosing the right tool for the job.
 *
 * A typed term goes to the search engine, which understands relevance and
 * typos. Browsing with no term goes to ProductListQuery, which understands
 * price ranges, stock and sale filters that a search index handles poorly.
 *
 * The engine returns ids in ranked order, so hydration has to preserve that
 * order — losing it silently turns relevance ranking back into id order,
 * which looks like it works and quietly ruins search quality.
 */
final readonly class SearchProducts
{
    public function __construct(private SearchEngine $engine) {}

    /** @return LengthAwarePaginator<int, Product> */
    public function handle(ProductFilters $filters, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        if (($filters->search ?? '') === '') {
            return new ProductListQuery($filters)->paginate($perPage);
        }

        $hits = $this->engine->search(SearchQuery::fromFilters($filters, $page, $perPage));

        return new Paginator(
            items: $this->hydrateInRankOrder($hits->ids),
            total: $hits->total,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  list<int>  $ids
     * @return list<Product>
     */
    private function hydrateInRankOrder(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with(['brand', 'variants'])
            ->get()
            ->keyBy('id');

        // Map back over the engine's order rather than the database's.
        return array_values(array_filter(array_map(
            static fn (int $id): ?Product => $products->get($id),
            $ids,
        )));
    }
}
