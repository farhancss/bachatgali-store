<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DataObjects;

use App\Domain\Catalog\Enums\ProductSort;

/**
 * A search request in domain terms. Deliberately not a Typesense query — the
 * engine translates this into its own dialect, so the domain never learns
 * what a "filter_by" string looks like.
 */
final readonly class SearchQuery
{
    /** @param list<string> $facetFilters */
    public function __construct(
        public string $term = '',
        public array $facetFilters = [],
        public ProductSort $sort = ProductSort::Relevance,
        public int $page = 1,
        public int $perPage = 24,
    ) {}

    public static function fromFilters(ProductFilters $filters, int $page = 1, int $perPage = 24): self
    {
        return new self(
            term: $filters->search ?? '',
            facetFilters: array_merge($filters->brandSlugs, $filters->attributeValues),
            sort: $filters->sort,
            page: $page,
            perPage: $perPage,
        );
    }

    public function hasTerm(): bool
    {
        return trim($this->term) !== '';
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
