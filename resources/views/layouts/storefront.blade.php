{{--
    Storefront layout for SEO-critical Blade pages: home, category, product,
    search. These never boot Inertia — see docs/adr/0003.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ config('brand.theme.default_mode') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('brand.name'))</title>
    <meta name="description" content="@yield('description', 'Cash on delivery across Pakistan.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    @stack('schema') {{-- JSON-LD injected per page --}}

    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <x-brand-theme />
</head>
<body>
    @include('components.announcement-bar')

    <x-site-header :categories="$categories ?? []" />

    <main>
        @yield('content')
    </main>

    <script>
        // Theme is a cookie-free, no-flash preference: read before paint in
        // the head would be better still — that lands with the layout work.
        document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
            const root = document.documentElement;
            const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next;
            localStorage.setItem('theme', next);
        });
        const saved = localStorage.getItem('theme');
        if (saved) document.documentElement.dataset.theme = saved;
    </script>

    {{-- Interactive islands mount here; the page itself stays static HTML. --}}
    <div id="cart-drawer" data-island="cart-drawer"></div>
</body>
</html>
