@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-limited')
    @endif
    
@endsection

@section('content')

<div>
    <vue-community-listings
        :loadshelves="{{ json_encode($shelves) }}"
        :loadcommunity="{{ $community->toJson() }}"
        :user="{{ $user ? $user->toJson() : 'null' }}" />
</div>
@endsection

@section('footer')
    
@endsection 