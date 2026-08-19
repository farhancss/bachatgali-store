<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * "You might also like" for the product page.
 *
 * Related-ness is shared categories, nearest first by how many they share.
 * Genuine recommendations arrive with the search engine; this is the honest
 * version that needs no model training and no extra service.
 */
final readonly class RelatedProductsQuery
{
    public function __construct(private Product $product) {}

    /** @return Collection<int, Product> */
    public function get(int $limit = 6): Collection
    {
        $categoryIds = $this->product->categories()->pluck('categories.id')->all();

        if ($categoryIds === []) {
            return $this->fallback($limit);
        }

        /** @var Collection<int, Product> $results */
        $results = Product::query()
            ->active()
            ->whereKeyNot($this->product->getKey())
            ->with(['brand', 'variants'])
            ->withMin('variants', 'price')
            ->withCount(['categories as shared_categories' => function (Builder $q) use ($categoryIds): void {
                $q->whereIn('categories.id', $categoryIds);
            }])
            ->whereHas('categories', function (Builder $q) use ($categoryIds): void {
                $q->whereIn('categories.id', $categoryIds);
            })
            ->orderByDesc('shared_categories')
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $results->isEmpty() ? $this->fallback($limit) : $results;
    }

    /**
     * An uncategorised product still needs something under it — an empty rail
     * looks broken, so fall back to the same brand, then to featured stock.
     *
     * @return Collection<int, Product>
     */
    private function fallback(int $limit): Collection
    {
        /** @var Collection<int, Product> $results */
        $results = Product::query()
            ->active()
            ->whereKeyNot($this->product->getKey())
            ->when(
                $this->product->brand_id !== null,
                fn (Builder $q): Builder => $q->orderByRaw(
                    'case when brand_id = ? then 0 else 1 end',
                    [$this->product->brand_id],
                ),
            )
            ->with(['brand', 'variants'])
            ->withMin('variants', 'price')
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $results;
    }
}
