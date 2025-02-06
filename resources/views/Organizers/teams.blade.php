@extends('Layouts.master-container')

@section('meta')
@endsection 

@section('nav')
    @include('Nav.nav-limited')
@endsection

@section('content')
    <div>
        <vue-organizer-index :user="user"
        ></vue-organizer-index>
    </div>
@endsection

@section('footer')
@endsection 