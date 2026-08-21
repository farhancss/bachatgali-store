<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Cod\DataObjects\CodLimits;
use App\Domain\Cod\DataObjects\RiskWeights;
use App\Domain\Pricing\DataObjects\DeliveryRules;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Courier\Contracts\CourierGateway;
use App\Infrastructure\Courier\Fake\FakeCourierGateway;
use App\Infrastructure\Search\Contracts\SearchEngine;
use App\Infrastructure\Search\Fake\FakeSearchEngine;
use App\Infrastructure\Search\Typesense\TypesenseSearchEngine;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

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
        $this->bindSearch();
        $this->bindCodTunables();
        $this->bindPricingRules();
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
                    "Unknown courier driver [{$driver}].",
                ),
            };
        });
    }

    private function bindSearch(): void
    {
        $this->app->singleton(SearchEngine::class, function (): SearchEngine {
            /** @var string $driver */
            $driver = config('scout.driver', 'database');

            $database = new FakeSearchEngine;

            return match ($driver) {
                // Typesense wraps the database engine rather than replacing
                // it: if search is down the storefront degrades instead of
                // erroring. See TypesenseSearchEngine.
                'typesense' => new TypesenseSearchEngine(
                    $database,
                    $this->app->make(LoggerInterface::class),
                ),
                default => $database,
            };
        });
    }

    private function bindPricingRules(): void
    {
        $this->app->singleton(DeliveryRules::class, fn (): DeliveryRules => new DeliveryRules(
            freeDeliveryThreshold: Money::fromPaisa(
                (int) config('bachatgali.delivery.free_threshold', 250_000),
            ),
            standardFee: Money::fromPaisa(
                (int) config('bachatgali.delivery.default_fee', 25_000),
            ),
            codHandlingFee: Money::fromPaisa(
                (int) config('bachatgali.cod.handling_fee', 0),
            ),
        ));
    }

    private function bindCodTunables(): void
    {
        $this->app->singleton(RiskWeights::class, function (): RiskWeights {
            /** @var array<string, int> $weights */
            $weights = config('bachatgali.cod.risk_weights', []);

            return RiskWeights::fromArray($weights);
        });

        $this->app->singleton(CodLimits::class, fn (): CodLimits => new CodLimits(
            maxOrderValue: Money::fromPaisa(
                (int) config('bachatgali.cod.max_order_value', 5_000_000),
            ),
            maxOrderValueNewCustomer: Money::fromPaisa(
                (int) config('bachatgali.cod.max_order_value_new', 1_500_000),
            ),
        ));
    }
}
