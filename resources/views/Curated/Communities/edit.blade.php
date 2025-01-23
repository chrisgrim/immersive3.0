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

<div>
    <vue-community-edit :community="{{ $community->toJson() }}" />
</div>

@endsection

@section('footer')
    
@endsection 