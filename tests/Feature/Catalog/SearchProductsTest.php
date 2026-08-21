<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SearchProducts;
use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\DataObjects\SearchHits;
use App\Domain\Catalog\DataObjects\SearchQuery;
use App\Domain\Catalog\Models\Product;
use App\Infrastructure\Search\Contracts\SearchEngine;
use App\Infrastructure\Search\Fake\FakeSearchEngine;
use Database\Seeders\CatalogSeeder;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
});

it('routes a typed term to the search engine', function (): void {
    $results = new SearchProducts(new FakeSearchEngine)
        ->handle(new ProductFilters(search: 'kettle'));

    expect($results->total())->toBe(1)
        ->and($results->getCollection()->first()?->name)->toContain('Kettle');
});

it('routes a browse with no term to the listing query', function (): void {
    // The listing query understands price, stock and sale filters that a
    // search index handles poorly.
    $results = new SearchProducts(new FakeSearchEngine)
        ->handle(new ProductFilters(onSaleOnly: true));

    expect($results->total())->toBeGreaterThan(0)
        ->and($results->getCollection()->pluck('slug'))->toContain('non-stick-frying-pan-24cm');
});

it('preserves the engine ranking rather than reverting to id order', function (): void {
    // Losing rank order looks like it works and quietly ruins search quality,
    // so the order is pinned against an engine that returns ids reversed.
    $ids = Product::query()->active()->orderByDesc('id')->pluck('id')->all();

    $engine = new readonly class($ids) implements SearchEngine
    {
        /** @param list<int> $ids */
        public function __construct(private array $ids) {}

        public function identifier(): string
        {
            return 'stub';
        }

        public function search(SearchQuery $query): SearchHits
        {
            return new SearchHits($this->ids, count($this->ids));
        }

        public function index(array $ids): void {}

        public function forget(array $ids): void {}

        public function flush(): void {}

        public function supportsFacets(): bool
        {
            return false;
        }
    };

    $results = new SearchProducts($engine)->handle(new ProductFilters(search: 'anything'));

    expect($results->getCollection()->pluck('id')->all())->toBe($ids);
});

it('survives an engine returning an id that no longer exists', function (): void {
    // The index is eventually consistent — a deleted product can linger in it
    // for a moment, and that must not produce a null in the result list.
    $engine = new class implements SearchEngine
    {
        public function identifier(): string
        {
            return 'stub';
        }

        public function search(SearchQuery $query): SearchHits
        {
            return new SearchHits([999_999], 1);
        }

        public function index(array $ids): void {}

        public function forget(array $ids): void {}

        public function flush(): void {}

        public function supportsFacets(): bool
        {
            return false;
        }
    };

    $results = new SearchProducts($engine)->handle(new ProductFilters(search: 'ghost'));

    expect($results->getCollection())->toBeEmpty();
});

it('returns an empty page for a term that matches nothing', function (): void {
    $results = new SearchProducts(new FakeSearchEngine)
        ->handle(new ProductFilters(search: 'zzzznothing'));

    expect($results->total())->toBe(0)
        ->and($results->getCollection())->toBeEmpty();
});
