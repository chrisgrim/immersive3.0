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
        <vue-admin :user="user" />
    </div>

@endsection

@section('footer')
    
@endsection 