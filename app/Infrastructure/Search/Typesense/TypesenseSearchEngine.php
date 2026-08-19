<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Typesense;

use App\Domain\Catalog\DataObjects\SearchHits;
use App\Domain\Catalog\DataObjects\SearchQuery;
use App\Domain\Catalog\Models\Product;
use App\Infrastructure\Search\Contracts\SearchEngine;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Typesense via Laravel Scout.
 *
 * Every call is wrapped: if the search service is unreachable the storefront
 * degrades to database search rather than showing an error page. Losing typo
 * tolerance costs nothing; a 500 on the search box costs the sale.
 */
final readonly class TypesenseSearchEngine implements SearchEngine
{
    public function __construct(
        private SearchEngine $fallback,
        private LoggerInterface $logger,
    ) {}

    public function identifier(): string
    {
        return 'typesense';
    }

    public function search(SearchQuery $query): SearchHits
    {
        try {
            $paginator = Product::search($query->term)
                ->paginate($query->perPage, 'page', $query->page);

            /** @var list<int> $ids */
            $ids = array_map(
                static fn (Product $p): int => (int) $p->getKey(),
                $paginator->items(),
            );

            return new SearchHits($ids, $paginator->total());
        } catch (Throwable $e) {
            $this->logger->warning('Search backend unavailable, falling back to database.', [
                'engine' => $this->identifier(),
                'exception' => $e->getMessage(),
            ]);

            return $this->fallback->search($query);
        }
    }

    /** @param array<int, int> $ids */
    public function index(array $ids): void
    {
        $this->guard(function () use ($ids): void {
            // Per model, not Collection::searchable() — that is a runtime
            // macro, invisible to static analysis. The instance method is the
            // same code path.
            Product::query()->whereIn('id', $ids)->get()
                ->each(static function (Product $product): void {
                    $product->searchable();
                });
        }, 'index');
    }

    /** @param array<int, int> $ids */
    public function forget(array $ids): void
    {
        $this->guard(function () use ($ids): void {
            Product::query()->whereIn('id', $ids)->get()
                ->each(static function (Product $product): void {
                    $product->unsearchable();
                });
        }, 'forget');
    }

    public function flush(): void
    {
        $this->guard(static function (): void {
            Product::removeAllFromSearch();
        }, 'flush');
    }

    public function supportsFacets(): bool
    {
        return true;
    }

    /**
     * Indexing failures must never break the write that triggered them —
     * saving a product has to succeed even when search is down.
     *
     * @param  callable(): void  $operation
     */
    private function guard(callable $operation, string $what): void
    {
        try {
            $operation();
        } catch (Throwable $e) {
            $this->logger->error('Search index operation failed.', [
                'engine' => $this->identifier(),
                'operation' => $what,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
