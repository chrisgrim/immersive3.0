@extends('layouts.master-container')

@section('meta')
    <title>Sitemap - {{config('app.name')}}</title>
    <meta name="description" content="Find everything on the Everything Immersive platform with our comprehensive sitemap.">
@endsection

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
@endif
@endsection

@section('content')
<div class="container mx-auto px-4 py-12 max-w-6xl">
    <h1 class="text-4xl font-bold mb-8">Sitemap</h1>
    
    <p class="text-xl text-neutral-600 mb-12">
        Find everything on the Everything Immersive platform with our comprehensive sitemap.
    </p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <!-- Main Pages Section -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-neutral-100">
            <h2 class="text-2xl font-semibold mb-6">Main Pages</h2>
            <ul class="space-y-3">
                <li><a href="{{ route('home') }}" class="text-blue-600 hover:underline">Home</a></li>
                <li><a href="{{ route('search') }}" class="text-blue-600 hover:underline">Search Events</a></li>
            </ul>
        </div>

        
        <!-- Legal Section -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-neutral-100">
            <h2 class="text-2xl font-semibold mb-6">Legal Information</h2>
            <ul class="space-y-3">
                <li><a href="{{ route('terms') }}" class="text-blue-600 hover:underline">Terms of Service</a></li>
                <li><a href="{{ route('privacy') }}" class="text-blue-600 hover:underline">Privacy Policy</a></li>
                <li><a href="{{ route('privacy-choices') }}" class="text-blue-600 hover:underline">Your Privacy Choices</a></li>
                <li><a href="{{ route('sitemap') }}" class="text-blue-600 hover:underline">Sitemap</a></li>
            </ul>
        </div>
        
        <!-- Authentication Section -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-neutral-100">
            <h2 class="text-2xl font-semibold mb-6">Account Access</h2>
            <ul class="space-y-3">
                <li><a href="{{ route('login') }}" class="text-blue-600 hover:underline">Login</a></li>
                <li><a href="{{ route('register') }}" class="text-blue-600 hover:underline">Sign Up</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Features requiring authentication -->
    <div class="border-t border-neutral-200 pt-8 mt-8">
        <h2 class="text-2xl font-semibold mb-6">Features Requiring Login</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6">
            <div>
                <h3 class="font-medium text-lg mb-3">User Features</h3>
                <ul class="space-y-2">
                    <li class="text-neutral-600">Profile Settings</li>
                    <li class="text-neutral-600">Messages</li>
                </ul>
            </div>
            
            <div>
                <h3 class="font-medium text-lg mb-3">Creator Features</h3>
                <ul class="space-y-2">
                    <li class="text-neutral-600">Event Hosting</li>
                    <li class="text-neutral-600">Team Management</li>
                    <li class="text-neutral-600">Create Communities</li>
                </ul>
            </div>
            
            @auth
                @if(auth()->user()->isAdmin())
                <div>
                    <h3 class="font-medium text-lg mb-3">Admin</h3>
                    <ul class="space-y-2">
                        <li><a href="{{ route('admin.index') }}" class="text-blue-600 hover:underline">Admin Dashboard</a></li>
                    </ul>
                </div>
                @endif
            @endauth
        </div>
    </div>
</div>
@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection 