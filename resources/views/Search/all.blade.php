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
    @include('footer.footer-padded')
@endsection 