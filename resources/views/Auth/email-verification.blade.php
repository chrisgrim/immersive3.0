@extends('layouts.master-container')

@section('meta')
   

@endsection 

@section('nav')

	@include('layouts.nav-container')
    
@endsection

@section('content')

<div class="flex justify-center items-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 border border-gray-300 rounded-2xl">
        <p class="text-center">Verification email has been resent. Please check your email inbox.</p>
    </div>
</div>
@endsection

@section('footer')
    
@endsection 