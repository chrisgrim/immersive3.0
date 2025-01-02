@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

    @include('Nav.creation-desktop')
    
@endsection

@section('content')
    
    <vue-getting-started :user="user" />

@endsection

@section('footer')
    
@endsection 