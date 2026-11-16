@extends('layouts.master-container')

{{-- Add a venue: its own steps, full screen, like creating an event. --}}
@section('nav')
@endsection

@push('after-laravel')
    <script>
        window.Laravel.page = { venue: {!! Js::from($pageVenue) !!} };
    </script>
@endpush

@section('content')
    <vue-venue-create :venue="pageData.venue"></vue-venue-create>
@endsection

@section('footer')
@endsection
