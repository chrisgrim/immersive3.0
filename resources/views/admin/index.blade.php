@extends('layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

    @if (Browser::isMobile())
    @else
        @include('nav.nav-full')
    @endif
    
@endsection

@section('content')
    <div>
        <vue-admin :user="user" />
    </div>

@endsection

@section('footer')
    
@endsection 