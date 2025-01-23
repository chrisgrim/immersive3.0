@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

    @include('Nav.nav-full')    
    
@endsection

@section('content')
    
    <vue-getting-started :user="user" />

@endsection

@section('footer')
    
@endsection 