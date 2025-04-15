@extends('layouts.master-container')

@section('meta')
    <title>Maintenance Mode - {{ config('app.name') }}</title>
    <meta name="description" content="Our site is currently in maintenance mode. Creation features are temporarily disabled.">
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="h-[calc(100vh-8rem)] min-h-[60vh] flex items-center justify-center px-6 py-12">
        <div class="text-center">
            <h1 class="text-9xl font-bold text-[#ff385c] mb-4">503</h1>
            <h2 class="text-3xl font-semibold text-gray-800 mb-6">Limited Functionality</h2>
            <p class="text-xl text-gray-600 mb-8 max-w-lg mx-auto">
                We're currently performing maintenance on our creation features.
                You can still browse events and content, but creating or editing is temporarily disabled.
            </p>
            <div class="space-x-4">
                <a href="{{ url('/') }}" 
                   class="inline-block bg-[#ff385c] text-white px-8 py-4 rounded-xl font-semibold hover:bg-opacity-90 transition-all">
                    Return Home
                </a>
                <a href="javascript:history.back()" 
                   class="inline-block border-2 border-gray-300 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:border-gray-400 transition-all">
                    Go Back
                </a>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <vue-footer></vue-footer>
@endsection 