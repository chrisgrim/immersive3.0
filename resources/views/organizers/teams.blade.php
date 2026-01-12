@extends('layouts.master-container')

@section('meta')
@endsection 

@section('nav')
    @include('nav.nav-limited')
@endsection

@section('content')
    <div>
        <vue-organizer-index :user="user"
        ></vue-organizer-index>
    </div>
@endsection

@section('footer')
@endsection 