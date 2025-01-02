@extends('Layouts.master-container')

@section('meta')

    @vite(['resources/css/leaflet.css'])

@endsection 

@section('nav')
    @if (in_array($event->status, ['p', 'e']))
        @include('Nav.creation-desktop')
    @endif
@endsection


@section('content')
    @if (!in_array($event->status, ['p', 'e']))
        <vue-hosting-event :event="{{ $event }}" :user="user" />
    @else
        <vue-hosting-event-edit :event="{{ $event }}" :user="user" />
    @endif
@endsection

@section('footer')
    
@endsection 