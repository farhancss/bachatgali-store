<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Contracts;

use App\Domain\Catalog\DataObjects\SearchHits;
use App\Domain\Catalog\DataObjects\SearchQuery;

/**
 * Every search backend implements this. The test suite runs entirely against
 * FakeSearchEngine — no test ever needs Typesense running, which is what
 * keeps CI free of service containers.
 *
 * Swapping Typesense for Meilisearch or Algolia is a new class and a config
 * entry, nothing more.
 */
interface SearchEngine
{
    public function identifier(): string;

    public function search(SearchQuery $query): SearchHits;

    /** @param array<int, int> $ids */
    public function index(array $ids): void;

    /** @param array<int, int> $ids */
    public function forget(array $ids): void;

    public function flush(): void;

    /** Facet counts for the current result set, keyed by facet then value. */
    public function supportsFacets(): bool;
}
