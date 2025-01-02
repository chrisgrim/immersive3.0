@extends('Layouts.master-container')

@section('meta')
    


@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.creation-desktop')
    @endif
    
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