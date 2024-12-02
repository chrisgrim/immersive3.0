@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @if (Browser::isMobile())
        @include('Nav.index-mobile')
    @else
        @include('Nav.event-desktop')
    @endif
    
@endsection

@section('content')

    <vue-community-index
        :communities="{{ $communities->toJson() }}"
        :user="{{ $user ? $user->toJson() : 'null' }}" />

@endsection

@section('footer')
    
@endsection 