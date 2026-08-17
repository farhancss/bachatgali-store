<?php

declare(strict_types=1);

use App\Domain\Cod\DataObjects\CodLimits;
use App\Domain\Cod\DataObjects\RiskWeights;
use App\Infrastructure\Courier\Contracts\CourierGateway;
use App\Infrastructure\Courier\Fake\FakeCourierGateway;

/*
| DomainServiceProvider is the only place configuration crosses into the
| domain layer. If a binding drifts from config/bachatgali.php, every Action
| downstream is silently scored or capped with the wrong numbers — so the
| wiring itself is tested, not just the objects it produces.
*/

it('binds the courier named by config', function (): void {
    config()->set('bachatgali.courier.default', 'fake');

    $gateway = app(CourierGateway::class);

    expect($gateway)->toBeInstanceOf(FakeCourierGateway::class)
        ->and($gateway->identifier())->toBe('fake');
});

it('keeps the courier a singleton so bookings survive within a request', function (): void {
    expect(app(CourierGateway::class))->toBe(app(CourierGateway::class));
});

it('fails loudly on an unknown courier driver rather than falling back', function (): void {
    config()->set('bachatgali.courier.default', 'not-a-courier');
    app()->forgetInstance(CourierGateway::class);

    expect(fn (): CourierGateway => app(CourierGateway::class))
        ->toThrow(InvalidArgumentException::class, 'Unknown courier driver [not-a-courier].');
});

it('builds the risk weights from config', function (): void {
    /** @var array<string, int> $configured */
    $configured = config('bachatgali.cod.risk_weights');

    expect(app(RiskWeights::class))->toEqual(RiskWeights::fromArray($configured));
});

it('builds the COD ceilings from config as Money', function (): void {
    $limits = app(CodLimits::class);

    expect($limits->maxOrderValue)->toBeMoney((int) config('bachatgali.cod.max_order_value'))
        ->and($limits->maxOrderValueNewCustomer)
        ->toBeMoney((int) config('bachatgali.cod.max_order_value_new'));
});

it('caps new customers below returning ones', function (): void {
    $limits = app(CodLimits::class);

    expect($limits->maxOrderValue->isGreaterThan($limits->maxOrderValueNewCustomer))->toBeTrue()
        ->and($limits->ceilingFor(isFirstTimeCustomer: true))->toEqual($limits->maxOrderValueNewCustomer)
        ->and($limits->ceilingFor(isFirstTimeCustomer: false))->toEqual($limits->maxOrderValue);
});
