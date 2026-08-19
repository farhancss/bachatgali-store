{{--
    Brand tokens as inline custom properties.

    These override the defaults in tokens.css, so one deployment can change
    its accent without a rebuild. Inline rather than a stylesheet because it
    must apply before first paint — a flash of the wrong brand colour is the
    most visible possible bug in a white-labelled product.
--}}
@php
    $theme = config('brand.theme');
@endphp
<style>
    :root {
        --accent: {{ $theme['accent'] }};
        --accent-d: {{ $theme['accent_dark'] }};
        --ok: {{ $theme['success'] }};
        --r: {{ $theme['radius'] }};
    }
</style>
