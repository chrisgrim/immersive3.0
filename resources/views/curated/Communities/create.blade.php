@extends('layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @if (Browser::isMobile())
        @include('nav.index-mobile')
    @else
        @include('nav.nav-limited')
    @endif
    
@endsection

@section('content')

    <vue-community-create />

@endsection

@section('footer')
    
@endsection 