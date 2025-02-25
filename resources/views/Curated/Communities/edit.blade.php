@extends('layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-full')
    @endif
    
@endsection

@section('content')

<div>
    <vue-community-edit :community="{{ $community->toJson() }}" />
</div>

@endsection

@section('footer')
    
@endsection 