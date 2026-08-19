<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;

it('defines variants only when the flag and the type both allow it', function (): void {
    // Flagged, but free text — you cannot pick a SKU from a text box, so the
    // type has the final say over the flag.
    $text = Attribute::factory()->variantDefining()->ofType(AttributeType::Text)->create();
    $select = Attribute::factory()->variantDefining()->create();
    $unflagged = Attribute::factory()->ofType(AttributeType::Select)->create();

    expect($text->canDefineVariants())->toBeFalse()
        ->and($select->canDefineVariants())->toBeTrue()
        ->and($unflagged->canDefineVariants())->toBeFalse();
});

it('orders its values by position', function (): void {
    $attribute = Attribute::factory()->create();
    AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'value' => 'large', 'position' => 2]);
    AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'value' => 'small', 'position' => 0]);

    expect($attribute->load('values')->values->pluck('value')->all())->toBe(['small', 'large']);
});

it('scopes to filterable attributes', function (): void {
    $shown = Attribute::factory()->create();
    $hidden = Attribute::factory()->create(['is_filterable' => false]);

    expect(Attribute::query()->filterable()->pluck('id'))
        ->toContain($shown->id)
        ->not->toContain($hidden->id);
});

it('falls back to the raw value when a value has no label', function (): void {
    $attribute = Attribute::factory()->create();
    $bare = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'value' => 'xl']);
    $labelled = AttributeValue::factory()->create([
        'attribute_id' => $attribute->id,
        'value' => 'xxl',
        'label' => 'Extra extra large',
    ]);

    expect($bare->displayLabel())->toBe('xl')
        ->and($labelled->displayLabel())->toBe('Extra extra large');
});

it('belongs to its attribute', function (): void {
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);

    expect($value->load('attribute')->attribute->id)->toBe($attribute->id);
});

it('pins a variant to the values that identify it', function (): void {
    $size = Attribute::factory()->variantDefining()->create(['name' => 'Size']);
    $small = AttributeValue::factory()->create(['attribute_id' => $size->id, 'value' => 'small']);
    $variant = ProductVariant::factory()->create();

    $variant->attributeValues()->attach($small->id);

    expect($variant->load('attributeValues')->attributeValues->pluck('value')->all())->toBe(['small'])
        ->and($small->variants()->count())->toBe(1);
});

it('keeps variant axes separate from product spec values', function (): void {
    $attribute = Attribute::factory()->create();
    $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $product->attributeValues()->attach($value->id);

    // Attaching to the product must not implicitly attach to the variant.
    expect($product->load('attributeValues')->attributeValues)->toHaveCount(1)
        ->and($variant->load('attributeValues')->attributeValues)->toHaveCount(0);
});

it('scopes brands to active ones', function (): void {
    $active = Brand::factory()->create();
    $hidden = Brand::factory()->inactive()->create();

    expect(Brand::query()->active()->pluck('id'))
        ->toContain($active->id)
        ->not->toContain($hidden->id);
});

it('walks the category relations in both directions', function (): void {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    expect($child->load('parent')->parent?->id)->toBe($root->id)
        ->and($root->load('children')->children->pluck('id')->all())->toBe([$child->id]);
});

it('routes brands and attributes by slug', function (): void {
    expect(Brand::factory()->create()->getRouteKeyName())->toBe('slug')
        ->and(Attribute::factory()->create()->getRouteKeyName())->toBe('slug');
});
