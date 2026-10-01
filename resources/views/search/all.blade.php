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
    @include('nav.index-desktop', [
        'searchedEvents' => $searchedEvents,
        'maxprice' => $maxprice,
        'searchedRemoteLocation' => $searchedRemoteLocation ?? null
    ])
@endif
@endsection

@section('content')
    <vue-search-all
        :searched-events="pageDataCopy('searchedEvents')"
        :max-price="{{ $maxprice }}"
        :searched-remote-location='@json($searchedRemoteLocation ?? null)'
    ></vue-search-all>
@endsection

@section('footer')
    {{-- Compact footer — this is a full-screen search/map page; the tall marketing
         footer (footer-padded) collides with the fixed map. --}}
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
