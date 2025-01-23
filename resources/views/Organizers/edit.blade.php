@extends('Layouts.master-container')

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-full')
    @endif
    
@endsection

@section('content')
    <div>
        <vue-organizer-edit
            :organizer="{{ $organizer }}"
            :user="user"
        ></vue-organizer-edit>
    </div>
@endsection

@section('footer')
    <vue-footer></vue-footer>
@endsection 
