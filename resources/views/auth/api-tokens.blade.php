@extends('layouts.master-container')

@section('meta')
    <title>API Tokens - Everything Immersive</title>
@endsection

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-full')
    @endif
@endsection

@section('content')
    <vue-api-tokens v-cloak></vue-api-tokens>
@endsection

@section('footer')
@endsection
