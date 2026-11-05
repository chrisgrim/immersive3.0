@extends('layouts.master-container')

@section('nav')
    @if (Browser::isMobile())
    @else
        @include('nav.nav-full')
    @endif
@endsection

@push('after-laravel')
    <script>
        window.Laravel.page = { credits: {!! Js::from($pageCredits) !!} };
    </script>
@endpush

@section('content')
    <div>
        <vue-event-credits :panel="pageData.credits"></vue-event-credits>
    </div>
@endsection

@section('footer')
@endsection
