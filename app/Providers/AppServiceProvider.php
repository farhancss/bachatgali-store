<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Observers\FlushesCatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Cached catalog pages must not outlive the data behind them.
        foreach ([Product::class, ProductVariant::class, Category::class, Brand::class] as $model) {
            $model::observe(FlushesCatalogCache::class);
        }

        // N+1 queries are a build failure, not a code-review conversation.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        // Surface slow queries in development before customers find them.
        if (! $this->app->isProduction()) {
            DB::whenQueryingForLongerThan(500, function (): void {
                logger()->warning('Slow query detected (>500ms).');
            });
        }
    }
}
