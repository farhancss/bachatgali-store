@extends('layouts.storefront')

@section('title', $filters->search ? 'Search: ' . $filters->search : 'Browse all products')
@section('description', 'Search thousands of products, all cash on delivery.')

@section('content')
    <div class="wrap">
        <x-catalog.product-grid
            :products="$products"
            :brands="$brands"
            :filters="$filters"
            :action="route('search')"
            :heading="$filters->search ? 'Results for “' . $filters->search . '”' : 'All products'" />
    </div>
@endsection
