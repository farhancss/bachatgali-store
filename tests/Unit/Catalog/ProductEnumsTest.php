<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;

it('allows multiple variants only for variable products', function (): void {
    expect(ProductType::Variable->allowsMultipleVariants())->toBeTrue()
        ->and(ProductType::Simple->allowsMultipleVariants())->toBeFalse()
        ->and(ProductType::Bundle->allowsMultipleVariants())->toBeFalse();
});

it('draws bundle stock from components rather than its own ledger', function (): void {
    expect(ProductType::Bundle->tracksOwnStock())->toBeFalse()
        ->and(ProductType::Simple->tracksOwnStock())->toBeTrue()
        ->and(ProductType::Variable->tracksOwnStock())->toBeTrue();
});

it('shows and indexes only active products', function (ProductStatus $status, bool $visible): void {
    expect($status->isVisibleToCustomers())->toBe($visible)
        ->and($status->shouldBeSearchable())->toBe($visible);
})->with([
    'draft' => [ProductStatus::Draft, false],
    'active' => [ProductStatus::Active, true],
    'archived' => [ProductStatus::Archived, false],
]);

it('labels and colours every product status', function (ProductStatus $status): void {
    expect($status->label())->not->toBeEmpty()
        ->and($status->colour())->toBeIn(['gray', 'success', 'warning']);
})->with(ProductStatus::cases());

it('labels every product type', function (ProductType $type): void {
    expect($type->label())->not->toBeEmpty();
})->with(ProductType::cases());

it('lets only closed-set attribute types define a variant', function (AttributeType $type, bool $canDefine): void {
    expect($type->canDefineVariants())->toBe($canDefine);
})->with([
    'select' => [AttributeType::Select, true],
    'colour' => [AttributeType::Colour, true],
    'boolean is closed but not an axis' => [AttributeType::Boolean, false],
    'free text' => [AttributeType::Text, false],
    'number' => [AttributeType::Number, false],
]);

it('knows which attribute types have predefined values', function (): void {
    $closed = array_values(array_filter(AttributeType::cases(), fn (AttributeType $t): bool => $t->hasPredefinedValues()));

    expect($closed)->toEqualCanonicalizing([AttributeType::Boolean, AttributeType::Select, AttributeType::Colour]);
});

it('labels every attribute type', function (AttributeType $type): void {
    expect($type->label())->not->toBeEmpty();
})->with(AttributeType::cases());
