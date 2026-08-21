<?php

declare(strict_types=1);

namespace App\Http\CacheProfiles;

use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\BaseCacheProfile;
use Spatie\ResponseCache\CacheProfiles\CacheProfile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the SEO-critical catalog pages are cached, and only for guests.
 *
 * The rule that matters: anything personalised must never enter a shared
 * cache. Serving one customer's cart or account page to another is the
 * single worst bug a full-page cache can produce, so this is an allowlist of
 * route names rather than a denylist of things to skip — a new personalised
 * route added later is excluded by default instead of leaking by default.
 */
final class CatalogPages extends BaseCacheProfile implements CacheProfile
{
    /** Route names safe to serve from a shared cache. */
    private const array CACHEABLE_ROUTES = [
        'home',
        'category',
        'product',
    ];

    public function shouldCacheRequest(Request $request): bool
    {
        if ($request->ajax()) {
            return false;
        }

        // isMethodCacheable() covers GET and HEAD. Laravel routes HEAD to
        // the GET action, so excluding it would leave every HEAD request
        // (monitors, link checkers, some crawlers) uncached for no reason.
        if (! $request->isMethodCacheable()) {
            return false;
        }

        // Authenticated responses are personalised by definition.
        if ($request->user() !== null) {
            return false;
        }

        // Search results vary by query string and go stale immediately;
        // caching them fills the store with near-duplicate entries.
        return in_array($request->route()?->getName(), self::CACHEABLE_ROUTES, strict: true);
    }

    public function shouldCacheResponse(Response $response): bool
    {
        // 2xx only. A cached 404 outlives the typo that caused it, and a
        // cached 500 turns a transient outage into a persistent one.
        return $response->isSuccessful();
    }

    /**
     * Cache entries are keyed per URL. The default already includes the full
     * URI; adding the theme keeps a dark-mode render from being served to a
     * light-mode visitor once server-side theming lands.
     */
    public function useCacheNameSuffix(Request $request): string
    {
        $theme = $request->cookie('theme');

        // cookie() can hand back an array for a malformed request; anything
        // that is not a plain string falls back to the default bucket rather
        // than becoming an unbounded set of cache keys.
        return is_string($theme) && $theme !== '' ? $theme : 'default';
    }
}
