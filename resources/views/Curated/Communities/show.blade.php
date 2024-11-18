@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

	@include('Layouts.nav-container')
    
@endsection

@section('content')

    <vue-community-show
        :curator="{{ auth()->user() ? auth()->user()->can('update', $community) ? 'true' : 'false' : 'null' }}"
        :loadshelves="{{ json_encode($shelves) }}" 
        :value="{{ $community }}" />

@endsection

@section('footer')
    
@endsection 