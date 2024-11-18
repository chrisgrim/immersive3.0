@extends('Layouts.master-container')

@section('meta')

@endsection 

@section('nav')

	@include('Layouts.nav-container')
    
@endsection

@section('content')

    <vue-post-edit
        :value="{{ $post->toJson() }}"
        :user="{{ $user->toJson() }}"
        :curator="{{ json_encode($curator) }}"
        :community="{{ $community->toJson() }}"
        :shelves="{{ $shelves->toJson() }}">
    </vue-post-edit>

@endsection

@section('footer')
    
@endsection 