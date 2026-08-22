@extends('layouts.master-container')

@section('meta')
@endsection

@section('nav')
    @include('nav.nav-limited')
@endsection

@section('content')
    <div>
        <vue-notifications-index></vue-notifications-index>
    </div>
@endsection

@section('footer')
@endsection
