<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

/*
| share() is asserted directly rather than through a rendered page: the root
| Blade view calls @vite, so rendering would require `npm run build` and
| would fail on a fresh clone for reasons unrelated to this middleware.
*/

function inertiaRequest(): Request
{
    $request = Request::create('/cart');
    $request->setLaravelSession(new Store('test', new ArraySessionHandler(60)));

    return $request;
}

it('shares the store settings every page needs', function (): void {
    $shared = (new HandleInertiaRequests)->share(inertiaRequest());

    expect($shared['store'])->toBe([
        'currencySymbol' => config('bachatgali.currency.symbol'),
        'freeDeliveryFrom' => config('bachatgali.delivery.free_threshold'),
        'codOnly' => true,
        'supportWhatsApp' => config('bachatgali.support.whatsapp'),
    ]);
});

it('shares the free-delivery threshold as integer paisa, not a formatted string', function (): void {
    // The client formats it; sending a string here would put currency
    // formatting in two places and let them disagree.
    $shared = (new HandleInertiaRequests)->share(inertiaRequest());

    expect($shared['store']['freeDeliveryFrom'])->toBeInt();
});

it('shares a null user for a guest', function (): void {
    $shared = (new HandleInertiaRequests)->share(inertiaRequest());

    expect($shared['auth'])->toBe(['user' => null]);
});

it('keeps the flash props lazy so an untouched session is not loaded', function (): void {
    $shared = (new HandleInertiaRequests)->share(inertiaRequest());

    expect($shared['flash']['success'])->toBeCallable()
        ->and($shared['flash']['error'])->toBeCallable();
});

it('resolves flash messages out of the session when they are there', function (): void {
    $request = inertiaRequest();
    $request->session()->put('success', 'Order placed.');

    $shared = (new HandleInertiaRequests)->share($request);

    expect(($shared['flash']['success'])())->toBe('Order placed.')
        ->and(($shared['flash']['error'])())->toBeNull();
});

it('keeps the props Inertia itself shares', function (): void {
    expect((new HandleInertiaRequests)->share(inertiaRequest()))->toHaveKey('errors');
});
