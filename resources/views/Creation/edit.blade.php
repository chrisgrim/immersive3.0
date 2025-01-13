@extends('Layouts.master-container')

@section('meta')

    @vite(['resources/css/leaflet.css'])

@endsection 

@section('nav')
    @if (in_array($event->status, ['p', 'e', 'n']) || (auth()->user()->isAdmin() && $event->status === 'r'))
        @include('Nav.creation-desktop')
    @endif
@endsection


@section('content')
    @if (in_array($event->status, ['p', 'e', 'n']) || (auth()->user()->isAdmin() && $event->status === 'r'))
        <vue-hosting-event-edit :event="{{ $event }}" :user="user" />
    @else
        <vue-hosting-event :event="{{ $event }}" :user="user" />
    @endif
@endsection

@section('footer')
    
@endsection 