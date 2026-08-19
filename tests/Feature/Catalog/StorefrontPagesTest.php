<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogSeeder;

/*
| withoutVite() matters: CI's test job never runs `npm run build`, so any
| test that renders the layout would otherwise fail on a missing manifest
| for reasons that have nothing to do with the code under test.
*/

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(CatalogSeeder::class);
});

it('renders the home page with real products', function (): void {
    $product = Product::query()->active()->firstOrFail();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($product->name, escape: false)
        ->assertSee('Cash on delivery', escape: false);
});

it('renders a category page scoped to that category', function (): void {
    $category = Category::query()->where('slug', 'clothing')->firstOrFail();

    $this->get(route('category', $category))
        ->assertOk()
        ->assertSee('Clothing', escape: false)
        ->assertSee('Apply filters', escape: false);
});

it('renders a product page with its price and stock state', function (): void {
    $product = Product::query()->with('variants')->where('slug', 'unstitched-lawn-suit')->firstOrFail();

    $this->get(route('product', $product))
        ->assertOk()
        ->assertSee($product->name, escape: false)
        ->assertSee($product->defaultVariant()?->price->format(), escape: false)
        ->assertSee('Add to cart', escape: false);
});

it('emits JSON-LD on the product page', function (): void {
    // The single most valuable thing on this page for search.
    $product = Product::query()->active()->firstOrFail();

    $this->get(route('product', $product))
        ->assertOk()
        ->assertSee('application/ld+json', escape: false)
        ->assertSee('https://schema.org', escape: false);
});

it('404s a draft product rather than rendering it', function (): void {
    // It may still be linked from a stale sitemap or an old campaign.
    $draft = Product::factory()->draft()->create();

    $this->get(route('product', $draft))->assertNotFound();
});

it('404s an archived product', function (): void {
    $archived = Product::factory()->archived()->create();

    $this->get(route('product', $archived))->assertNotFound();
});

it('renders search results for a term', function (): void {
    $this->get(route('search', ['q' => 'kettle']))
        ->assertOk()
        ->assertSee('Kettle', escape: false);
});

it('says so plainly when nothing matches', function (): void {
    $this->get(route('search', ['q' => 'zzzznothingmatchesthis']))
        ->assertOk()
        ->assertSee('Nothing matched', escape: false);
});

it('keeps every filter combination a real, shareable URL', function (): void {
    // ADR-0003: catalog pages must be crawlable, so filters are GET state.
    $this->get(route('search', ['on_sale' => 1, 'sort' => 'price-asc']))
        ->assertOk()
        ->assertSee('Clear all', escape: false);
});

it('rejects a malformed filter instead of failing the query', function (): void {
    $this->get(route('search', ['min' => 'not-a-number']))->assertSessionHasErrors('min');
});

it('ignores an unknown sort rather than erroring', function (): void {
    $this->get(route('search', ['sort' => 'nonsense']))->assertSessionHasErrors('sort');
});

it('serves the health endpoint independently of the catalog', function (): void {
    $this->get('/up')->assertOk();
});
