@extends('layouts.master-container')

@section('nav')
    @if (Browser::isMobile())
    @else
        @include('nav.nav-full')
    @endif
@endsection

@push('after-laravel')
    <script>
        window.Laravel.page = { project: {!! Js::from($pageProject) !!} };
    </script>
@endpush

@section('content')
    <div>
        <vue-project-edit :project="pageData.project" :user="user"></vue-project-edit>
    </div>
@endsection

@section('footer')
@endsection
