@extends('Layouts.master-container')

@section('meta')
@endsection 

@section('nav')
   @include('Layouts.nav-creation')
@endsection

@section('content')
    <vue-inbox 
        :events="{{ json_encode($conversations) }}"
        :user="user"
    ></vue-inbox>
@endsection

@section('footer')
@endsection 