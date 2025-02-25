@extends('layouts.master-container')

@section('meta')


@endsection 

@section('nav')
    @if (Browser::isMobile())
    @else
        @if (in_array($event->status, ['p', 'e', 'n']) || (auth()->user()->isAdmin() && $event->status === 'r'))
            @include('nav.nav-full')
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