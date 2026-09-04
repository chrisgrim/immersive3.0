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
        {{-- $mapPins rides separately from $searchedEvents on purpose: the nav
             partials above @json the events object a second time, and the
             pin list would be embedded in the page twice. --}}
        <vue-search-location-mobile
            :searched-events='@json($searchedEvents)'
            :pins='@json($mapPins)'
        ></vue-search-location-mobile>
    @else
        <vue-search-location
            :searched-events='@json($searchedEvents)'
            :pins='@json($mapPins)'
        ></vue-search-location>
    @endif
@endsection

@section('footer')
    @include('footer.footer-full')
@endsection 