@extends('layouts.master-container')

@section('meta')
    <title>Login or Sign Up - Everything Immersive</title>
    <script>
        window.Laravel = {
            flash: @json(session()->all())
        };
        console.log('Laravel Flash Data:', window.Laravel); // Debug line
    </script>
@endsection 

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
@endif
@endsection

@section('content')
    <div class="bg-white flex flex-row justify-center md:items-center min-h-[calc(100vh-16rem)] m-auto">
        <vue-user-login 
            :flash-data="{{ json_encode(session()->all()) }}"
        />
    </div>
@endsection

    @section('footer')
        @include('footer.footer-padded')
    @endsection 