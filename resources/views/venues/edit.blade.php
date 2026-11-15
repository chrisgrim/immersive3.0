@extends('layouts.master-container')

@section('nav')
    @if (Browser::isMobile())
    @else
        @include('nav.nav-full')
    @endif
@endsection

@push('after-laravel')
    <script>
        window.Laravel.page = { venue: {!! Js::from($pageVenue) !!} };
    </script>
@endpush

@section('content')
    <div>
        <vue-venue-edit :venue="pageData.venue" :user="user"></vue-venue-edit>
    </div>
@endsection

@section('footer')
@endsection
