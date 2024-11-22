@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')
   @include('Layouts.nav-container')
@endsection

@section('content')
    <vue-search-all 
        :searched-events='@json($searchedEvents)'
        :categories='@json($categories)'
        :tags='@json($tags)'
        :searched-categories='@json($searchedCategories)'
        :searched-tags='@json($searchedTags)'
    ></vue-search-all>
@endsection

@section('footer')

@endsection 