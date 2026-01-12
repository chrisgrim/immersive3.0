@extends('layouts.master-container')

@section('nav')

    @if (Browser::isMobile())
    <!-- moved to vue file -->
    @else
        @include('nav.nav-full')
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
