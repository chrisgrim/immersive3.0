@extends('layouts.master-container')

@section('meta')
    <title>{{ $event->name }} - Event Removed</title>
    <meta name="description" content="This event has been removed from our platform."/>
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="event" />
    <meta property="og:title" content="{{ $event->name }} - Event Removed" />
    <meta property="og:description" content="This event has been removed from our platform." />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:site_name" content="{{config('app.name')}}" />
    <meta property="article:publisher" content="https://www.everythingimmersive.com" />
    <meta property="article:section" content="Events" />
    <meta property="article:published_time" content="{{$event->created_at}}" />
    <meta property="article:modified_time" content="{{$event->updated_at}}" />
    <meta property="og:updated_time" content="{{$event->updated_at}}" />
    
    @if($event->images->isNotEmpty())
        <meta property="og:image" content="{{ config('app.image_url') . $event->images->first()->large_image_path }}" />
        <meta property="og:image:secure_url" content="{{ config('app.image_url') . $event->images->first()->large_image_path }}" />
        <meta property="og:image:alt" content="{{ $event->name }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:image" content="{{ config('app.image_url') . $event->images->first()->large_image_path }}" />
    @else
        <meta property="og:image" content="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" />
        <meta property="og:image:secure_url" content="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:image" content="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" />
    @endif
    <meta name="twitter:description" content="This event has been removed from our platform." />
    <meta name="twitter:title" content="{{ $event->name }} - Event Removed" />
    <meta name="twitter:site" content="@everythingimmersive" />
    <meta name="twitter:creator" content="@everythingimmersive" />
@endsection

@section('nav')
    @if (Browser::isMobile())
    @else
        @include('nav.nav-limited-search')
    @endif
@endsection

@section('content')
    @if (Browser::isMobile())
        <div class="min-h-screen bg-gray-100 py-12">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    @if($event->images->isNotEmpty())
                        <div class="aspect-w-16 aspect-h-9">
                            <picture>
                                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images->first()->large_image_path }}">
                                <img 
                                    class="w-full h-64 object-cover" 
                                    src="{{ config('app.image_url') }}{{ substr($event->images->first()->large_image_path, 0, -4) }}jpg" 
                                    alt="{{ $event->name }} Immersive Event - Main Image"
                                >
                            </picture>
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <h1 class="text-4.5xl font-medium text-black leading-tight mb-4">{{ $event->name }}</h1>
                        
                        <p class="text-2xl text-neutral-700 font-medium mb-8">
                            This event has been removed from our platform.
                        </p>

                        <div class="mt-8 flex justify-center">
                            <a href="/" class="font-medium py-6 px-20 rounded-2xl border-none text-white bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1 whitespace-nowrap inline-block">
                                Return Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div id="mainContent">
            <div id="bodyArea" class="show">
                <div class="show-content">
                    <div class="relative w-full m-auto px-10 mt-24 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        <div class="flex flex-col justify-center bg-white">
                            <div class="flex justify-between items-start">
                                <div class="flex-grow">
                                    <h1 class="text-4.5xl font-medium text-black leading-tight">{{ $event->name }}</h1>
                                    <p class="text-2xl text-neutral-700 font-medium mt-4">
                                        This event has been removed from our platform.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($event->images->isNotEmpty())
                        <div class="mt-12">
                            <div class="relative w-full m-auto p-0 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                                <div class="relative">
                                    <div class="gap-2 md:rounded-2xl overflow-hidden" style="display: flex;">
                                        <div class="aspect-[3/4]" style="height: 45rem; flex-shrink: 0;">
                                            <picture>
                                                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images->first()->large_image_path }}">
                                                <img 
                                                    class="w-full h-full object-cover" 
                                                    style="height: 45rem;"
                                                    src="{{ config('app.image_url') }}{{ substr($event->images->first()->large_image_path, 0, -4) }}jpg" 
                                                    alt="{{ $event->name }} Immersive Event - Main Image"
                                                >
                                            </picture>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="relative w-full m-auto px-10 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        <div class="flex justify-center">
                            <a href="/" class="font-medium py-6 px-20 rounded-2xl border-none text-white bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1 whitespace-nowrap inline-block">
                                Return Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('footer')
    @include('footer.footer-limited')
@endsection
