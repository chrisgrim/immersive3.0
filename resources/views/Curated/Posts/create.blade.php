@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @include('Nav.creation-desktop')  
    
@endsection

@section('content')

    <vue-post-create
        :community="{{ $community->toJson() }}"
        :shelves="{{ $shelves->toJson() }}">
    </vue-post-create>

@endsection

@section('footer')
    
@endsection 