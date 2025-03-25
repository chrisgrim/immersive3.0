@extends('layouts.master-container')

@section('meta')

@endsection 

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile', [
        'searchedEvents' => $searchedEvents,
        'maxprice' => $maxprice
    ])
@else
    @include('nav.nav-full-search', [
        'searchedEvents' => $searchedEvents,
        'maxprice' => $maxprice
    ])
@endif
@endsection

@section('content')
    @if (Browser::isMobile())
        <vue-search-location-mobile
            :searched-events='@json($searchedEvents)'
        ></vue-search-location-mobile>
    @else
        <vue-search-location
            :searched-events='@json($searchedEvents)'
        ></vue-search-location>
    @endif
@endsection

@section('footer')
    @include('footer.footer-full')
@endsection 