@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')
@if (Browser::isMobile())
    @include('Nav.index-mobile')
@else
    @include('Nav.nav-full-search')
@endif
@endsection

@section('content')
    @if (Browser::isMobile())
        <vue-search-location-mobile 
        :searched-events='@json($searchedEvents)'
        :categories='@json($categories)'
        :tags='@json($tags)'
        :in-person-categories='@json($inPersonCategories)'
            :max-price="{{ $maxprice }}"
        ></vue-search-location-mobile>
    @else
        <vue-search-location 
        :searched-events='@json($searchedEvents)'
        :categories='@json($categories)'
        :tags='@json($tags)'
        :in-person-categories='@json($inPersonCategories)'
            :max-price="{{ $maxprice }}"
        ></vue-search-location>
    @endif
@endsection

@section('footer')

@endsection 