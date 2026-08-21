<?php

declare(strict_types=1);

use App\Http\CacheProfiles\CatalogPages;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

/*
| The profile is tested directly rather than through the middleware, because
| the thing worth protecting is the DECISION: what may enter a shared cache.
| Serving one customer's personalised page to another is the worst bug a
| full-page cache can produce.
*/

function profile(): CatalogPages
{
    return new CatalogPages;
}

function requestFor(string $routeName, string $method = 'GET'): Request
{
    $request = Request::create('/whatever', $method);
    $route = new Route([$method], '/whatever', []);
    $route->name($routeName);
    $request->setRouteResolver(fn (): Route => $route);

    return $request;
}

it('caches the SEO-critical catalog pages', function (string $route): void {
    expect(profile()->shouldCacheRequest(requestFor($route)))->toBeTrue();
})->with(['home', 'category', 'product']);

it('never caches search results', function (): void {
    // They vary by query string and go stale immediately; caching them fills
    // the store with near-duplicate entries.
    expect(profile()->shouldCacheRequest(requestFor('search')))->toBeFalse();
});

it('excludes an unknown route by default rather than caching it', function (): void {
    // The allowlist matters: a personalised route added later must be
    // excluded by default, not leak by default.
    expect(profile()->shouldCacheRequest(requestFor('cart')))->toBeFalse()
        ->and(profile()->shouldCacheRequest(requestFor('checkout')))->toBeFalse()
        ->and(profile()->shouldCacheRequest(requestFor('account')))->toBeFalse();
});

it('never caches a response for a signed-in visitor', function (): void {
    $request = requestFor('product');
    $request->setUserResolver(fn (): User => User::factory()->make());

    expect(profile()->shouldCacheRequest($request))->toBeFalse();
});

it('caches HEAD as well as GET', function (): void {
    // Laravel routes HEAD to the GET action; excluding it would leave every
    // monitor and link checker uncached for no reason.
    expect(profile()->shouldCacheRequest(requestFor('product', 'HEAD')))->toBeTrue();
});

it('never caches a write', function (string $method): void {
    expect(profile()->shouldCacheRequest(requestFor('product', $method)))->toBeFalse();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('stores only successful responses', function (int $status, bool $cacheable): void {
    // A cached 404 outlives the typo that caused it; a cached 500 turns a
    // transient outage into a persistent one.
    expect(profile()->shouldCacheResponse(new Response('', $status)))->toBe($cacheable);
})->with([
    'ok' => [200, true],
    'redirect' => [302, false],
    'not found' => [404, false],
    'server error' => [500, false],
]);
