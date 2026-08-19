<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Turns listing-page query strings into a ProductFilters object.
 *
 * The query layer never sees a Request — this class is the only place the two
 * meet, which is what keeps ProductListQuery unit-testable.
 */
final class ProductListRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
            'brand' => ['nullable', 'array', 'max:20'],
            'brand.*' => ['string', 'max:120'],
            'attr' => ['nullable', 'array', 'max:20'],
            'attr.*' => ['string', 'max:120'],
            // Bounds arrive in whole rupees; paisa is an implementation detail.
            'min' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'in_stock' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
        ];
    }

    public function toFilters(): ProductFilters
    {
        /** @var list<string> $brands */
        $brands = array_values(array_filter((array) $this->validated('brand', [])));

        /** @var list<string> $attributes */
        $attributes = array_values(array_filter((array) $this->validated('attr', [])));

        $min = $this->validated('min');
        $max = $this->validated('max');

        return new ProductFilters(
            brandSlugs: $brands,
            attributeValues: $attributes,
            minPrice: is_numeric($min) ? Money::fromRupees((int) $min) : null,
            maxPrice: is_numeric($max) ? Money::fromRupees((int) $max) : null,
            inStockOnly: (bool) $this->validated('in_stock', false),
            onSaleOnly: (bool) $this->validated('on_sale', false),
            sort: ProductSort::fromRequest(is_string($s = $this->validated('sort')) ? $s : null),
            search: is_string($q = $this->validated('q')) ? $q : null,
        );
    }
}
