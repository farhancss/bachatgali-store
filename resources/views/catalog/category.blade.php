@extends('layouts.storefront')

@section('title', $category->name . ' — ' . config('brand.name'))
@section('description', $category->description ?? 'Shop ' . $category->name . ' with cash on delivery across Pakistan.')
@section('canonical', route('category', $category))

@push('schema')
    <x-catalog.breadcrumb-schema :crumbs="[
        ['name' => 'Home', 'url' => route('home')],
        ['name' => $category->name, 'url' => route('category', $category)],
    ]" />
@endpush

@section('content')
    <div class="wrap">
        <nav class="crumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span>/</span>
            <span>{{ $category->name }}</span>
        </nav>

        @if ($children->isNotEmpty())
            <div class="sec">
                <div class="rail">
                    @foreach ($children as $child)
                        <a class="rcat" href="{{ route('category', $child) }}">{{ $child->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-catalog.product-grid
            :products="$products"
            :brands="$brands"
            :filters="$filters"
            :action="route('category', $category)"
            :heading="$category->name" />
    </div>
@endsection
