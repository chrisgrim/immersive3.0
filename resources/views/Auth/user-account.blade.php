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

    <vue-user-account :loaduser= "{{ auth()->user() ? auth()->user() : 'null' }}" />   

@endsection

@section('footer')
    
@endsection 