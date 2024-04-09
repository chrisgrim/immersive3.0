@extends('Layouts.master-container')

@section('meta')
    


@endsection 

@section('nav')

	@include('Layouts.nav-container')
    
@endsection

@section('content')

	@if(auth()->check() && auth()->user()->can('update', $user) && auth()->user()->email_verified_at !== null)
        <vue-user-profile :owner="user" :loaduser="{{ $user }}" v-cloak />   
    @else
       @include('Auth.user-profile-guest')
    @endif
	
@endsection

@section('footer')
    
@endsection 