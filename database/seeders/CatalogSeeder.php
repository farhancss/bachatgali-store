<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A small but shaped catalog: a two-level category tree, real variant axes,
 * and a spread of stock states so the listing and product pages have
 * something honest to render before phase 2 starts.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brands = $this->seedBrands();
        $categories = $this->seedCategories();
        [$size, $colour] = $this->seedVariantAxes();

        $this->seedSimpleProducts($brands, $categories);
        $this->seedVariableProduct($brands, $categories, $size, $colour);
    }

    /** @return array<string, Brand> */
    private function seedBrands(): array
    {
        $brands = [];

        foreach (['Gul Ahmed', 'Khaadi', 'Servis', 'Dawlance'] as $position => $name) {
            $brands[$name] = Brand::query()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'position' => $position,
            ]);
        }

        return $brands;
    }

    /** @return array<string, Category> */
    private function seedCategories(): array
    {
        $tree = [
            'Clothing' => ['Men', 'Women', 'Kids'],
            'Home & Kitchen' => ['Cookware', 'Appliances'],
        ];

        $categories = [];
        $position = 0;

        foreach ($tree as $rootName => $childNames) {
            $root = Category::query()->create([
                'name' => $rootName,
                'slug' => Str::slug($rootName),
                'position' => $position++,
            ]);

            $categories[$rootName] = $root;

            foreach ($childNames as $childPosition => $childName) {
                $categories[$rootName.'/'.$childName] = Category::query()->create([
                    'parent_id' => $root->id,
                    'name' => $childName,
                    'slug' => Str::slug($rootName.'-'.$childName),
                    'position' => $childPosition,
                ]);
            }
        }

        return $categories;
    }

    /** @return array{0: Attribute, 1: Attribute} */
    private function seedVariantAxes(): array
    {
        $size = Attribute::query()->create([
            'name' => 'Size',
            'slug' => 'size',
            'type' => AttributeType::Select,
            'is_filterable' => true,
            'is_variant_defining' => true,
        ]);

        foreach (['S', 'M', 'L', 'XL'] as $position => $value) {
            AttributeValue::query()->create([
                'attribute_id' => $size->id,
                'value' => Str::lower($value),
                'label' => $value,
                'position' => $position,
            ]);
        }

        $colour = Attribute::query()->create([
            'name' => 'Colour',
            'slug' => 'colour',
            'type' => AttributeType::Colour,
            'is_filterable' => true,
            'is_variant_defining' => true,
        ]);

        foreach (['Black', 'White', 'Navy'] as $position => $value) {
            AttributeValue::query()->create([
                'attribute_id' => $colour->id,
                'value' => Str::lower($value),
                'label' => $value,
                'position' => $position,
            ]);
        }

        return [$size, $colour];
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Category>  $categories
     */
    private function seedSimpleProducts(array $brands, array $categories): void
    {
        $catalogue = [
            ['Non-stick Frying Pan 24cm', 'Dawlance', 'Home & Kitchen/Cookware', 2_450, 3_200, 40],
            ['Electric Kettle 1.7L', 'Dawlance', 'Home & Kitchen/Appliances', 4_999, null, 3],
            ['Kids Cotton Pyjama Set', 'Khaadi', 'Clothing/Kids', 1_890, 2_400, 0],
            ['Leather Formal Shoes', 'Servis', 'Clothing/Men', 6_750, null, 12],
        ];

        foreach ($catalogue as $position => [$name, $brand, $categoryKey, $price, $wasPrice, $stock]) {
            $product = Product::query()->create([
                'brand_id' => $brands[$brand]->id,
                'status' => ProductStatus::Active,
                'name' => $name,
                'slug' => Str::slug($name),
                'short_description' => 'Cash on delivery across Pakistan.',
                'is_featured' => $position < 2,
                'position' => $position,
                'published_at' => now(),
            ]);

            $product->categories()->attach($categories[$categoryKey]->id);

            ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => Str::upper(Str::slug($name)),
                'price' => Money::fromRupees($price),
                'compare_at_price' => $wasPrice === null ? null : Money::fromRupees($wasPrice),
                'stock_quantity' => $stock,
                'low_stock_threshold' => 5,
                'weight_grams' => 800,
                'is_default' => true,
            ]);
        }
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Category>  $categories
     */
    private function seedVariableProduct(
        array $brands,
        array $categories,
        Attribute $size,
        Attribute $colour,
    ): void {
        $product = Product::query()->create([
            'brand_id' => $brands['Gul Ahmed']->id,
            'type' => ProductType::Variable,
            'status' => ProductStatus::Active,
            'name' => 'Unstitched Lawn Suit',
            'slug' => 'unstitched-lawn-suit',
            'short_description' => 'Three-piece lawn, summer collection.',
            'position' => 10,
            'published_at' => now(),
        ]);

        $product->categories()->attach($categories['Clothing/Women']->id);

        $sizes = $size->values()->get();
        $colours = $colour->values()->get();
        $position = 0;

        foreach ($colours as $colourValue) {
            foreach ($sizes as $sizeValue) {
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'sku' => Str::upper("LAWN-{$colourValue->value}-{$sizeValue->value}"),
                    'name' => "{$colourValue->displayLabel()} / {$sizeValue->displayLabel()}",
                    'price' => Money::fromRupees(3_490),
                    'stock_quantity' => $position > 0 && $position % 5 === 0 ? 0 : 20 - $position,
                    'low_stock_threshold' => 5,
                    'weight_grams' => 450,
                    'is_default' => $position === 0,
                    'position' => $position,
                ]);

                $variant->attributeValues()->attach([$colourValue->id, $sizeValue->id]);
                $position++;
            }
        }
    }
}
