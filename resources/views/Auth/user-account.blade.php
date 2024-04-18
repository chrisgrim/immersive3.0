@extends('Layouts.master-container')

@section('meta')
    


@endsection 

@section('nav')

   @include('Layouts.nav-container')
    
@endsection

@section('content')

    <vue-user-account :loaduser= "{{ auth()->user() ? auth()->user() : 'null' }}" />   

@endsection

@section('footer')
    
@endsection 