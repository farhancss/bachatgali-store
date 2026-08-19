<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DataObjects;

/**
 * What a search returns: ids in relevance order, a total, and facet counts.
 *
 * Ids rather than models on purpose — hydration is the caller's decision, and
 * it keeps the engine free of Eloquent.
 */
final readonly class SearchHits
{
    /**
     * @param  list<int>  $ids  Product ids, in engine-ranked order.
     * @param  array<string, array<string, int>>  $facets  Facet name => value => count.
     */
    public function __construct(
        public array $ids = [],
        public int $total = 0,
        public array $facets = [],
        public bool $usedFallback = false,
    ) {}

    public static function empty(bool $usedFallback = false): self
    {
        return new self(usedFallback: $usedFallback);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    /** @return array<string, int> */
    public function facet(string $name): array
    {
        return $this->facets[$name] ?? [];
    }
}
