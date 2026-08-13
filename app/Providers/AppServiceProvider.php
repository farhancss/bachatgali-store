<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
