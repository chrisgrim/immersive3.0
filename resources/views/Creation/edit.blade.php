@extends('Layouts.master-container')

@section('meta')
    

@endsection 


@section('content')
    
    <vue-hosting-event :event="{{$event}}" :user="user" />

@endsection

@section('footer')
    
@endsection 