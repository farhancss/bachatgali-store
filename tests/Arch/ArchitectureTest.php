<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
| These run in under five seconds and prevent the slow structural rot that
| code review does not reliably catch at month six. If you find yourself
| wanting to weaken one of these, that is the signal to have a design
| conversation — not to edit the rule.
|
| Run with: composer run test:arch
*/

arch('domain layer never depends on HTTP')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate\Http',
        'App\Http',
        'Inertia\Inertia',
        'Illuminate\Support\Facades\Request',
    ]);

arch('domain layer never reaches for the service container or config')
    ->expect('App\Domain')
    ->not->toUse(['app', 'config', 'resolve', 'request', 'session', 'auth']);

arch('domain layer never depends on infrastructure implementations')
    ->expect('App\Domain')
    ->not->toUse([
        'App\Infrastructure\Courier\PostEx',
        'App\Infrastructure\Courier\Fake',
    ]);

arch('controllers never query Eloquent directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Support\Facades\DB',
    ]);

arch('actions are final, readonly and expose a single entry point')
    ->expect('App\Domain\Cod\Actions')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveMethod('handle');

arch('value objects are final and readonly')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('no floating point arithmetic anywhere near money')
    ->expect(['App\Domain\Pricing', 'App\Domain\Cod', 'App\Domain\Shared\ValueObjects'])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil']);

arch('enums are string backed so they survive a database round trip')
    ->expect('App\Domain')
    ->enums()->toBeStringBackedEnums();

arch('contracts are interfaces')
    ->expect('App\Infrastructure\Courier\Contracts')
    ->toBeInterfaces();

arch('strict types are declared everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging statements survive review')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('laravel preset')
    ->preset()->laravel();

arch('security preset')
    ->preset()->security();
