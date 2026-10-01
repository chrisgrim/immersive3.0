@extends('layouts.master-container')

@section('meta')
    @include('search.meta')
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
            :searched-events="pageDataCopy('searchedEvents')"
            :pins='@json($mapPins)'
        ></vue-search-location-mobile>
    @else
        <vue-search-location
            :searched-events="pageDataCopy('searchedEvents')"
            :pins='@json($mapPins)'
        ></vue-search-location>
    @endif
@endsection

@section('footer')
    @include('footer.footer-full')
@endsection 

@push('after-laravel')
    {{-- The first page of results, serialized once: the nav and the results
         list both bind pageDataCopy('searchedEvents') (resources/js/bladeBridge.js).
         They used to inline it as an attribute each, doubling the page. --}}
    <script>
        window.Laravel.page = { searchedEvents: {!! Js::from($searchedEvents) !!} };
    </script>
@endpush
