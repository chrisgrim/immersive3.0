@extends('Layouts.master-container')

@section('meta')
@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.creation-desktop')
    @endif
    
@endsection

@section('content')
    <vue-inbox 
        :events="{{ json_encode($conversations) }}"
        :user="user"
    ></vue-inbox>
@endsection

@section('footer')
@endsection 