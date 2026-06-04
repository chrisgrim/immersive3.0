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
    @include('nav.index-desktop', [
        'searchedEvents' => $searchedEvents,
        'maxprice' => $maxprice
    ])
@endif
@endsection

@section('content')
    <vue-search-all 
        :searched-events='@json($searchedEvents)'
        :max-price="{{ $maxprice }}"
    ></vue-search-all>
@endsection

@section('footer')
    {{-- Compact footer — this is a full-screen search/map page; the tall marketing
         footer (footer-padded) collides with the fixed map. --}}
    @include('footer.footer-full')
@endsection 