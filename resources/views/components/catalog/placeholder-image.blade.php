{{--
    Neutral placeholder for products with no media yet. Inline SVG rather than
    a file so it costs no request and can never 404.
--}}
@props(['size' => 600])
@php
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'">'
        .'<rect width="'.$size.'" height="'.$size.'" fill="#ecedf1"/></svg>';
@endphp
{{ 'data:image/svg+xml;charset=utf-8,'.rawurlencode($svg) }}
