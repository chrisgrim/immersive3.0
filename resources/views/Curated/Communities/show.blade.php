@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

    @if (Browser::isMobile())
        @include('Nav.index-mobile')
    @else
        @include('Nav.curated-desktop')
    @endif
    
@endsection

@section('content')

    <vue-community-show
        :curator="{{ auth()->user() ? auth()->user()->can('update', $community) ? 'true' : 'false' : 'null' }}"
        :loadshelves="{{ json_encode($shelves) }}" 
        :value="{{ $community }}" />

@endsection

@section('footer')
    
@endsection 