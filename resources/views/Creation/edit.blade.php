@extends('Layouts.master-container')

@section('meta')

    @vite(['resources/css/leaflet.css'])

@endsection 


@section('content')
    
    <vue-hosting-event :event="{{$event}}" :user="user" />

@endsection

@section('footer')
    
@endsection 