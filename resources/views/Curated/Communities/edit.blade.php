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

    <vue-community-edit
        :curator="{{ json_encode($isCurator) }}"
        :loadshelves="{{ json_encode($shelves) }}"
        :loadcommunity="{{ $community->toJson() }}"
        :user="{{ $user ? $user->toJson() : 'null' }}"
        :loadowner="{{ $owner ? $owner->toJson() : 'null' }}" />

@endsection

@section('footer')
    
@endsection 