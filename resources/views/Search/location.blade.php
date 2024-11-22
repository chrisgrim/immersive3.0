@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')
   @include('Layouts.nav-container')
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