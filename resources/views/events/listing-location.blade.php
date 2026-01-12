@extends('layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

	@include('layouts.nav-container')
    
@endsection

@section('content')
    
    <vue-event-listings-location 
        :user="user"
        :searched-events="{{ json_encode($searchedEvents) }}" 
        :in-Person-Categories="{{ $inPersonCategories }}"
        :categories="{{ $categories }}"
        :searched-Categories="{{ json_encode($searchedCategories) }}"
        :tags="{{ $tags }}" 
        :searched-Tags="{{ json_encode($searchedTags) }}">

@endsection

@section('footer')
    
@endsection 