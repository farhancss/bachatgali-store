{{--
    Storefront layout for SEO-critical Blade pages: home, category, product,
    search. These never boot Inertia — see docs/adr/0003.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Cash on delivery across Pakistan.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    @stack('schema') {{-- JSON-LD injected per page --}}

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    @include('components.announcement-bar')

    <main>
        @yield('content')
    </main>

    {{-- Interactive islands mount here; the page itself stays static HTML. --}}
    <div id="cart-drawer" data-island="cart-drawer"></div>
</body>
</html>
