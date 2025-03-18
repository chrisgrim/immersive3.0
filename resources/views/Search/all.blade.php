@extends('layouts.master-container')

@section('meta')

@endsection 

@section('nav')
    @if (Browser::isMobile())
        @include('nav.index-mobile')
    @else
        @include('nav.index-desktop')
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
    @include('footer.footer-padded')
@endsection 