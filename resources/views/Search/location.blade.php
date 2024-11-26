@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')
@if (Browser::isMobile())
    @include('Nav.index-mobile')
@else
    @include('Nav.search-desktop')
@endif
@endsection

@section('content')
    <vue-search-location 
        :searched-events='@json($searchedEvents)'
        :categories='@json($categories)'
        :tags='@json($tags)'
        :in-person-categories='@json($inPersonCategories)'
        :max-price="{{ $maxprice }}"
    ></vue-search-location>
@endsection

@section('footer')

@endsection 