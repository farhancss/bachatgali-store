@props(['crumbs'])

{{--
    BreadcrumbList JSON-LD. Google renders this as the breadcrumb trail under
    a result instead of a raw URL, which lifts click-through on deep pages.

    $crumbs is a list of ['name' => string, 'url' => string], outermost first.

    The document is assembled in a PHP block on purpose. Blade compiles the
    schema.org context key as a directive wherever it appears in template
    body -- including inside an unescaped echo -- so writing that key inline
    emits compiled PHP into the JSON and produces structured data that
    silently fails to parse. Keep it out of the markup.
--}}
@php
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $i): array => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => $crumb['url'],
        ])->all(),
    ];
@endphp

<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
