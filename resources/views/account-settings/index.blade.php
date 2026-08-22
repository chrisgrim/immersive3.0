@extends('layouts.master-container')

@section('meta')
@endsection

@section('nav')
    @include('nav.nav-limited')
@endsection

@section('content')
    <div>
        <vue-account-settings-index></vue-account-settings-index>
    </div>
@endsection

@section('footer')
@endsection
