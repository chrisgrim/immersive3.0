@extends('layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

    @include('nav.nav-full')    
    
@endsection

@section('content')
    
    <vue-getting-started :user="user" />

@endsection

@section('footer')
    
@endsection 