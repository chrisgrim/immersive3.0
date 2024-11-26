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

    <vue-post-show
        :value="{{ $post->toJson() }}"
        :user="{{ $user ? $user->toJson() : 'null' }}"
        :curator="{{ json_encode($curator) }}"
        :community="{{ $community->toJson() }}">
    </vue-post-show>

@endsection

@section('footer')
    
@endsection 