@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

	@include('Layouts.nav-creation')
    
@endsection

@section('content')
    
    <vue-hosting :organizer="{{$organizer}}" :user="user" />

@endsection

@section('footer')
    
@endsection 