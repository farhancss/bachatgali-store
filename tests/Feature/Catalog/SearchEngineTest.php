<?php

declare(strict_types=1);

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\DataObjects\SearchQuery;
use App\Domain\Catalog\Models\Product;
use App\Infrastructure\Search\Contracts\SearchEngine;
use App\Infrastructure\Search\Fake\FakeSearchEngine;
use Database\Seeders\CatalogSeeder;

/*
| Search runs against the database engine here — no test ever needs Typesense
| running, which is what keeps CI free of service containers.
|
| Case sensitivity is asserted deliberately: Postgres LIKE is case-sensitive
| and SQLite's is not, so a plain `like` passes this suite and then finds
| nothing in production. These tests only prove the *intent* is declared;
| the real dialect check belongs in a Postgres CI job.
*/

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->engine = new FakeSearchEngine;
});

it('is the engine the container binds by default', function (): void {
    expect(app(SearchEngine::class))->toBeInstanceOf(SearchEngine::class)
        ->and(app(SearchEngine::class))->toBe(app(SearchEngine::class));
});

it('finds a product by name regardless of case', function (string $term): void {
    $hits = $this->engine->search(new SearchQuery(term: $term));

    expect($hits->total)->toBe(1)
        ->and($hits->isEmpty())->toBeFalse();
})->with(['kettle', 'Kettle', 'KETTLE', 'KeTtLe']);

it('finds a product by SKU', function (): void {
    $sku = Product::query()->with('variants')->firstOrFail()->defaultVariant()?->sku;

    expect($this->engine->search(new SearchQuery(term: (string) $sku))->total)->toBe(1);
});

it('returns everything active when there is no term', function (): void {
    $hits = $this->engine->search(new SearchQuery);

    expect($hits->total)->toBe(Product::query()->active()->count());
});

it('excludes drafts from results', function (): void {
    Product::factory()->draft()->create(['name' => 'Secret Unreleased Thing']);

    expect($this->engine->search(new SearchQuery(term: 'Secret'))->total)->toBe(0);
});

it('reports nothing found rather than erroring', function (): void {
    $hits = $this->engine->search(new SearchQuery(term: 'zzzznope'));

    expect($hits->isEmpty())->toBeTrue()
        ->and($hits->total)->toBe(0)
        ->and($hits->ids)->toBe([]);
});

it('flags that it fell back, so the outage is visible in logs', function (): void {
    // A silent degradation is one nobody fixes.
    expect($this->engine->search(new SearchQuery(term: 'kettle'))->usedFallback)->toBeTrue();
});

it('returns brand facet counts', function (): void {
    $facets = $this->engine->search(new SearchQuery)->facet('brand');

    expect($facets)->not->toBeEmpty()
        ->and(array_sum($facets))->toBeGreaterThan(0);
});

it('pages without overlapping', function (): void {
    $first = $this->engine->search(new SearchQuery(page: 1, perPage: 2))->ids;
    $second = $this->engine->search(new SearchQuery(page: 2, perPage: 2))->ids;

    expect($first)->toHaveCount(2)
        ->and(array_intersect($first, $second))->toBeEmpty();
});

it('escapes wildcards so a stray percent is not a match-all', function (): void {
    expect($this->engine->search(new SearchQuery(term: '%'))->total)->toBe(0);
});

it('tracks what has been indexed and forgotten', function (): void {
    $this->engine->index([1, 2, 3]);
    expect($this->engine->indexed)->toHaveCount(3);

    $this->engine->forget([2]);
    expect($this->engine->indexed)->toHaveCount(2)->not->toHaveKey(2);

    $this->engine->flush();
    expect($this->engine->indexed)->toBeEmpty()->and($this->engine->flushed)->toBeTrue();
});

it('builds a query straight from listing filters', function (): void {
    $query = SearchQuery::fromFilters(
        new ProductFilters(brandSlugs: ['khaadi'], search: 'lawn'),
        page: 2,
        perPage: 10,
    );

    expect($query->term)->toBe('lawn')
        ->and($query->facetFilters)->toBe(['khaadi'])
        ->and($query->hasTerm())->toBeTrue()
        ->and($query->offset())->toBe(10);
});

it('knows an empty term is not a search', function (): void {
    expect(new SearchQuery(term: '   ')->hasTerm())->toBeFalse()
        ->and((new SearchQuery)->hasTerm())->toBeFalse();
});

it('only indexes products that should be searchable', function (): void {
    $active = Product::query()->active()->firstOrFail();
    $draft = Product::factory()->draft()->create();

    expect($active->shouldBeSearchable())->toBeTrue()
        ->and($draft->shouldBeSearchable())->toBeFalse();
});

it('flattens everything a result card needs into the search document', function (): void {
    $product = Product::query()->where('slug', 'unstitched-lawn-suit')->firstOrFail();
    $document = $product->toSearchableArray();

    expect($document)
        ->toHaveKeys(['id', 'name', 'slug', 'brand', 'categories', 'skus', 'price', 'in_stock'])
        ->and($document['price'])->toBeInt()          // paisa, so the engine can sort on it
        ->and($document['in_stock'])->toBeBool()
        ->and($document['skus'])->not->toBeEmpty();
});
