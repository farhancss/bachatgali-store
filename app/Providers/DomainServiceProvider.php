<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Cod\DataObjects\CodLimits;
use App\Domain\Cod\DataObjects\RiskWeights;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Courier\Contracts\CourierGateway;
use App\Infrastructure\Courier\Fake\FakeCourierGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds domain contracts and tunables to concrete values.
 *
 * This is the only place configuration crosses into the domain layer, which
 * is what lets every Action be unit-tested without booting the framework.
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindCourier();
        $this->bindCodTunables();
    }

    private function bindCourier(): void
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

    private function bindCodTunables(): void
    {
        $this->app->singleton(RiskWeights::class, function (): RiskWeights {
            /** @var array<string, int> $weights */
            $weights = config('bachatgali.cod.risk_weights', []);

            return RiskWeights::fromArray($weights);
        });

        $this->app->singleton(CodLimits::class, function (): CodLimits {
            return new CodLimits(
                maxOrderValue: Money::fromPaisa(
                    (int) config('bachatgali.cod.max_order_value', 5_000_000)
                ),
                maxOrderValueNewCustomer: Money::fromPaisa(
                    (int) config('bachatgali.cod.max_order_value_new', 1_500_000)
                ),
            );
        });
    }
}
