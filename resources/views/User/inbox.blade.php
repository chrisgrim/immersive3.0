@extends('Layouts.master-container')

@section('meta')
@endsection 

@section('nav')
   @include('Layouts.nav-container')
@endsection

@section('content')
    <vue-inbox 
        :events="{{ json_encode($conversations) }}"
    ></vue-inbox>
@endsection

@section('footer')
@endsection 