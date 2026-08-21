{{--
    Organization + WebSite, emitted on the home page only.

    The SearchAction is what earns a sitelinks search box in Google results —
    a search box for this store rendered directly on the results page. It only
    works if the target URL is a real, crawlable search route, which is why
    /search takes its term as a plain query parameter.
--}}
@php
    $home = route('home');

    $organization = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => config('brand.legal_name'),
        'url' => $home,
        'description' => config('brand.tagline'),
        'contactPoint' => array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'telephone' => config('brand.support.phone'),
            'email' => config('brand.support.email'),
        ], static fn ($v) => $v !== null && $v !== ''),
    ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('brand.name'),
        'url' => $home,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => route('search') . '?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
@endphp

<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
<script type="application/ld+json">{!! json_encode($website, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
