@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @if (Browser::isMobile())
        @include('Nav.index-mobile')
    @else
        @include('Nav.nav-full')   
    @endif
    
@endsection

@section('content')

    <vue-post-edit
        :value="{{ $post->toJson() }}"
        :user="{{ $user->toJson() }}"
        :curator="{{ json_encode($curator) }}"
        :community="{{ $community->toJson() }}"
        :shelves="{{ $shelves->toJson() }}">
    </vue-post-edit>

@endsection

@section('footer')
    
@endsection 