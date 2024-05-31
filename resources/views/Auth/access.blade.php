@extends('Layouts.master-container')

@section('meta')
   

@endsection 

@section('nav')

	@include('Layouts.nav-container')
    
@endsection

@section('content')

<vue-user-login v-cloak />  

@endsection

@section('footer')
    
@endsection 