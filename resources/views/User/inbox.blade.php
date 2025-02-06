@extends('Layouts.master-container')

@section('meta')
@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-full')
    @endif
    
@endsection

@section('content')

<div>
    <vue-inbox 
        :conversations="{{ json_encode($conversations) }}"
        :user="user"
    ></vue-inbox>
</div>
@endsection

@section('footer')
@endsection 