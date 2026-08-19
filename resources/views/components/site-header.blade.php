{{--
    Storefront header. Markup and classes come from the approved prototype;
    the only change is that links are real routes instead of go() calls.
--}}
<header id="hdr"><div class="wrap"><div class="hd">
    <a class="logo" href="{{ route('home') }}">
        <span class="lm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </span>
        {{ config('brand.name') }}
    </a>

    <form class="search" action="{{ route('search') }}" method="get" role="search">
        <input name="q" value="{{ request('q') }}" placeholder="Search for earbuds, kurta, kettle…" aria-label="Search">
        <button type="submit" aria-label="Search"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
    </form>

    <div class="hact">
        <button type="button" class="ib" data-theme-toggle title="Theme" aria-label="Toggle theme">
            <svg class="sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </button>
        <span class="ib" title="Account"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span>
        <span class="ib" title="Cart" id="cart-button">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </span>
    </div>
</div></div></header>

@if (! empty($categories))
    <nav class="navstrip"><div class="wrap">
        @foreach ($categories as $navCategory)
            <a href="{{ route('category', $navCategory) }}"
               @class(['on' => request()->routeIs('category') && request()->route('category')?->is($navCategory)])>{{ $navCategory->name }}</a>
        @endforeach
    </div></nav>
@endif
