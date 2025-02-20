@extends('Layouts.master-container')

@section('meta')

    @vite(['resources/css/leaflet.css'])

@endsection 

@section('nav')
    @if (Browser::isMobile())
    @else
        @if (in_array($event->status, ['p', 'e', 'n']) || (auth()->user()->isAdmin() && $event->status === 'r'))
            @include('Nav.nav-full')
        @endif
    @endif
@endsection


@section('content')
    @if (in_array($event->status, ['p', 'e', 'n']) || (auth()->user()->isAdmin() && $event->status === 'r'))
    <div>
        <vue-hosting-event-edit :event="{{ $event }}" :user="user" />
    </div>
    @else
        <vue-hosting-event :event="{{ $event }}" :user="user" />
    @endif
@endsection

@section('footer')
    
@endsection 