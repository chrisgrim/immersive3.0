@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

	@include('Layouts.nav-creation')
    
@endsection

@section('content')
    
    <vue-getting-started :user="user" />

@endsection

@section('footer')
    
@endsection 