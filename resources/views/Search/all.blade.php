@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')
    @if (Browser::isMobile())
        @include('Nav.index-mobile')
    @else
        @include('Nav.index-desktop')
    @endif
@endsection

@section('content')
    <vue-search-all 
        :searched-events='@json($searchedEvents)'
        :categories='@json($categories)'
        :tags='@json($tags)'
        :searched-categories='@json($searchedCategories)'
        :searched-tags='@json($searchedTags)'
        :max-price="{{ $maxprice }}"
    ></vue-search-all>
@endsection

@section('footer')

@endsection 