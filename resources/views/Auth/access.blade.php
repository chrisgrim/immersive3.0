@extends('Layouts.master-container')

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
    @include('Nav.index-mobile')
@else
    @include('Nav.index-desktop')
@endif
@endsection

@section('content')
    <div class="bg-white flex flex-row justify-center items-center min-h-[calc(100vh-16rem)] m-auto">
        <vue-user-login 
            :flash-data="{{ json_encode(session()->all()) }}"
        />
    </div>
@endsection

@section('footer')
@endsection 