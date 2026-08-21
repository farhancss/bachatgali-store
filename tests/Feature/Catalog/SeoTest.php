<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogSeeder;

/*
| Structured data and the sitemap are the two things that decide whether the
| catalog is findable at all. Both fail silently — a malformed JSON-LD block
| or a draft leaking into the sitemap costs traffic without ever raising an
| error — so they get asserted rather than eyeballed.
*/

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(CatalogSeeder::class);
});

/** @return list<array<string, mixed>> Every JSON-LD block on the page, decoded. */
function schemaBlocksIn(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(
        static fn (string $json): array => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        $matches[1],
    );
}

it('emits valid JSON-LD on every catalog page', function (string $path): void {
    $blocks = schemaBlocksIn($this->get($path)->assertOk()->getContent() ?: '');

    expect($blocks)->not->toBeEmpty();

    foreach ($blocks as $block) {
        expect($block)->toHaveKey('@context')->toHaveKey('@type');
    }
})->with(fn (): array => [
    'home' => ['/'],
    'category' => ['/c/clothing'],
    'product' => ['/p/unstitched-lawn-suit'],
]);

it('describes the product with price and availability', function (): void {
    $product = Product::query()->with('variants')->where('slug', 'unstitched-lawn-suit')->firstOrFail();
    $blocks = schemaBlocksIn($this->get("/p/{$product->slug}")->getContent() ?: '');

    $schema = collect($blocks)->firstWhere('@type', 'Product');

    expect($schema)->not->toBeNull()
        ->and($schema['name'])->toBe($product->name)
        ->and($schema['offers']['priceCurrency'])->toBe(config('bachatgali.currency.code'))
        ->and($schema['offers']['availability'])->toBe('https://schema.org/InStock')
        // A decimal string, not paisa — publishing 349000 would list the
        // product at a hundred times its price.
        ->and($schema['offers']['price'])->toBe('3490.00');
});

it('marks an out-of-stock product as such for search engines', function (): void {
    $product = Product::query()->where('slug', 'kids-cotton-pyjama-set')->firstOrFail();
    $blocks = schemaBlocksIn($this->get("/p/{$product->slug}")->getContent() ?: '');

    expect(collect($blocks)->firstWhere('@type', 'Product')['offers']['availability'])
        ->toBe('https://schema.org/OutOfStock');
});

it('emits a breadcrumb trail ending at the current page', function (): void {
    $blocks = schemaBlocksIn($this->get('/p/unstitched-lawn-suit')->getContent() ?: '');
    $crumbs = collect($blocks)->firstWhere('@type', 'BreadcrumbList');

    expect($crumbs)->not->toBeNull();

    $items = $crumbs['itemListElement'];

    expect($items[0]['name'])->toBe('Home')
        ->and($items[0]['position'])->toBe(1)
        ->and(end($items)['name'])->toBe('Unstitched Lawn Suit')
        // Positions must be 1-based and contiguous or Google discards it.
        ->and(array_column($items, 'position'))->toBe(range(1, count($items)));
});

it('offers a sitelinks search box from the home page', function (): void {
    $blocks = schemaBlocksIn($this->get('/')->getContent() ?: '');
    $website = collect($blocks)->firstWhere('@type', 'WebSite');

    expect($website)->not->toBeNull()
        ->and($website['potentialAction']['@type'])->toBe('SearchAction')
        ->and($website['potentialAction']['target']['urlTemplate'])->toContain('{search_term_string}');
});

it('names the deployment brand rather than a hardcoded store', function (): void {
    config()->set('brand.name', 'Zaiqa Mart');
    config()->set('brand.legal_name', 'Zaiqa Mart Pvt Ltd');

    $blocks = schemaBlocksIn($this->get('/')->getContent() ?: '');

    expect(collect($blocks)->firstWhere('@type', 'Organization')['name'])->toBe('Zaiqa Mart Pvt Ltd')
        ->and(collect($blocks)->firstWhere('@type', 'WebSite')['name'])->toBe('Zaiqa Mart');
});

it('writes a sitemap of published pages only', function (): void {
    $draft = Product::factory()->draft()->create(['name' => 'Unreleased', 'slug' => 'unreleased']);

    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = file_get_contents(public_path('sitemap.xml')) ?: '';

    expect($xml)->toContain('/p/unstitched-lawn-suit')
        ->and($xml)->toContain('/c/clothing')
        // A draft in the sitemap invites Google to crawl a 404.
        ->and($xml)->not->toContain($draft->slug);
});

it('lists every active product and category in the sitemap', function (): void {
    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = file_get_contents(public_path('sitemap.xml')) ?: '';
    $urls = substr_count($xml, '<loc>');

    $expected = 1 + Product::query()->active()->count() + Category::query()->where('is_active', true)->count();

    expect($urls)->toBe($expected);
});
