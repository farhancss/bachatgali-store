<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Courier\Contracts\CourierGateway;
use App\Infrastructure\Courier\Fake\FakeCourierGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds domain contracts to concrete implementations.
 *
 * Every external dependency is resolved here, which is what makes the whole
 * system testable: swap one binding and the test suite runs offline.
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CourierGateway::class, function (): CourierGateway {
            /** @var string $driver */
            $driver = config('bachatgali.courier.default', 'fake');

            return match ($driver) {
                'fake' => new FakeCourierGateway,
                // 'postex'   => new PostExGateway(...),   ← phase 4
                // 'leopards' => new LeopardsGateway(...), ← phase 4
                default => throw new InvalidArgumentException(
                    "Unknown courier driver [{$driver}]."
                ),
            };
        });
    }
}
