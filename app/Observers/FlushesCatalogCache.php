<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Invalidates cached catalog pages when the catalog changes.
 *
 * This lives outside app/Domain deliberately: cache invalidation is an
 * infrastructure concern, and a domain model that reaches for a cache facade
 * stops being unit-testable without the framework.
 *
 * Invalidation is targeted rather than a full flush — forgetting every page
 * because one price moved throws away the whole warm cache, and on a large
 * catalog that is a self-inflicted traffic spike. A price change forgets the
 * product page and the home page, nothing more.
 */
final class FlushesCatalogCache
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        match (true) {
            $model instanceof Product => $this->forgetProduct($model),
            $model instanceof ProductVariant => $this->forgetVariantsProduct($model),
            $model instanceof Category => $this->forgetCategory($model),
            // A brand's name appears on every card that carries it, and there
            // is no cheap way to enumerate those pages. This is the one case
            // where a full flush is the honest option.
            $model instanceof Brand => ResponseCache::clear(),
            default => null,
        };
    }

    private function forgetProduct(Product $product): void
    {
        ResponseCache::forget([
            route('product', $product->slug, absolute: false),
            route('home', absolute: false),
        ]);

        $product->categories()->pluck('slug')->each(static function (string $slug): void {
            ResponseCache::forget(route('category', $slug, absolute: false));
        });
    }

    private function forgetVariantsProduct(ProductVariant $variant): void
    {
        $product = $variant->product()->first();

        if ($product instanceof Product) {
            $this->forgetProduct($product);
        }
    }

    private function forgetCategory(Category $category): void
    {
        ResponseCache::forget([
            route('category', $category->slug, absolute: false),
            route('home', absolute: false),
        ]);
    }
}
