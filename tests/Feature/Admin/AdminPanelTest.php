<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
| The panel is the tool the business actually runs on, so the checks here are
| about access and about money rendering correctly — the two ways an admin
| panel does real damage.
*/

beforeEach(function (): void {
    $this->withoutVite();
});

it('sends an anonymous visitor to the login screen', function (): void {
    get('/admin')->assertRedirect();
    get('/admin/login')->assertOk();
});

it('lets an active staff member in', function (): void {
    actingAs(User::factory()->create())
        ->get('/admin')
        ->assertSuccessful();
});

it('locks out a deactivated account while keeping the record', function (): void {
    // Someone leaving must lose the key without losing their audit trail.
    $leaver = User::factory()->deactivated()->create();

    actingAs($leaver)->get('/admin')->assertForbidden();

    expect(User::query()->find($leaver->id))->not->toBeNull();
});

it('lists every catalog resource', function (string $path): void {
    actingAs(User::factory()->create())
        ->get("/admin/{$path}")
        ->assertSuccessful();
})->with([
    'brands' => ['brands'],
    'categories' => ['categories'],
    'products' => ['products'],
    'attributes' => ['attributes'],
]);

it('opens a product for editing with its variants', function (): void {
    $this->seed(CatalogSeeder::class);
    $product = Product::query()->where('slug', 'unstitched-lawn-suit')->firstOrFail();

    actingAs(User::factory()->create())
        ->get("/admin/products/{$product->id}/edit")
        ->assertSuccessful()
        ->assertSee($product->name, escape: false);
});

it('shows prices as formatted rupees, never raw paisa', function (): void {
    // A four-figure price rendered as six-figure paisa is the kind of thing
    // that gets a product listed at a hundred times its price.
    $this->seed(CatalogSeeder::class);

    actingAs(User::factory()->create())
        ->get('/admin/products')
        ->assertSuccessful()
        ->assertSee('Rs. 2,450', escape: false)
        ->assertDontSee('245000', escape: false);
});

it('opens the create form for a new product', function (): void {
    actingAs(User::factory()->create())
        ->get('/admin/products/create')
        ->assertSuccessful();
});

it('keeps a brand reachable for editing', function (): void {
    $brand = Brand::factory()->create();

    actingAs(User::factory()->create())
        ->get("/admin/brands/{$brand->id}/edit")
        ->assertSuccessful()
        ->assertSee($brand->name, escape: false);
});
