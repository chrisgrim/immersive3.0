@extends('layouts.master-container')

@php
    $imageUrl = config('app.image_url', 'https://your-default-url.com/');
@endphp

@section('meta')
    {{-- Basic Meta --}}
    <title>{{ $post->name }} - {{ $community->name }}</title>
    <link rel="canonical" href="{{ url('/communities/' . $community->slug . '/posts/' . $post->slug) }}" />
    <meta name="description" content="{{ Str::limit(strip_tags($post->blurb ?? ''), 160) }}"/>
    
    {{-- Open Graph Meta --}}
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="{{ $post->name }}" />
    <meta property="og:description" content="{{ Str::limit(strip_tags($post->blurb ?? ''), 160) }}" />
    <meta property="og:url" content="{{ url('/communities/' . $community->slug . '/posts/' . $post->slug) }}" />
    <meta property="og:site_name" content="EverythingImmersive" />
    <meta property="article:publisher" content="https://www.everythingimmersive.com" />
    <meta property="article:section" content="Communities" />
    <meta property="article:published_time" content="{{ $post->created_at->toIso8601String() }}" />
    <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}" />
    <meta property="og:updated_time" content="{{ $post->updated_at->toIso8601String() }}" />

    {{-- Image Meta --}}
    @php
        $imagePath = '';
        
        if ($post->event_id && $post->featuredEventImage) {
            $imagePath = $post->featuredEventImage->largeImagePath ?? $post->featuredEventImage->thumbImagePath;
        } elseif ($post->images && $post->images->isNotEmpty()) {
            $imagePath = $post->images[0]->large_image_path ?? $post->images[0]->thumb_image_path;
        } else {
            $imagePath = $post->largeImagePath ?? $post->thumbImagePath;
        }
    @endphp
    
    @if($imagePath)
        <meta property="og:image" content="{{ $imageUrl . $imagePath }}" />
        <meta property="og:image:secure_url" content="{{ $imageUrl . $imagePath }}" />
        <meta property="og:image:alt" content="{{ $post->name }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:image" content="{{ $imageUrl . $imagePath }}" />
    @else
        <meta property="og:image" content="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" />
        <meta property="og:image:secure_url" content="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:image" content="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" />
    @endif
    
    {{-- Twitter Meta --}}
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($post->blurb ?? ''), 160) }}" />
    <meta name="twitter:title" content="{{ $post->name }}" />
    <meta name="twitter:site" content="@everythingimmersive" />
    <meta name="twitter:creator" content="@everythingimmersive" />
    
    {{-- Schema.org JSON-LD --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "{{ $post->name }}",
        "description": "{{ Str::limit(strip_tags($post->blurb ?? ''), 160) }}",
        "image": [
            @if($imagePath)
                "{{ $imageUrl . $imagePath }}"
            @else
                "{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}"
            @endif
        ],
        "datePublished": "{{ $post->created_at->toIso8601String() }}",
        "dateModified": "{{ $post->updated_at->toIso8601String() }}",
        "author": {
            "@type": "Person",
            "name": "{{ $post->user->name }}"
        },
        "publisher": {
            "@type": "Organization",
            "name": "Everything Immersive",
            "logo": {
                "@type": "ImageObject",
                "url": "{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}"
            }
        },
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": "{{ url('/communities/' . $community->slug . '/posts/' . $post->slug) }}"
        }
    }
    </script>
@endsection 

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-padded')
    @endif
@endsection

@section('styles')
<style>
    .card-blurb p {
        font-size: 1.8rem;
        line-height: 2.8rem;
        font-weight: 400;
    }
    .card-blurb h3 {
        color: black;
        line-height: 3rem;
    }
    .card-blurb h4 {
        color: black;
        line-height: 2.5rem;
    }
    .card-blurb strong {
        color: black;
    }
    .card-blurb a {
        color: rgb(37, 99, 235);  /* text-blue-600 equivalent */
        text-decoration: none;
    }
    .card-blurb a strong {
        color: rgb(37, 99, 235);  /* text-blue-600 equivalent */
    }
    .card-blurb a:hover {
        text-decoration: underline;
    }
</style>
@endsection

@section('content')
<div class="max-w-screen-5xl px-10 md:px-16 lg:px-40 mx-auto py-16 md:py-36 relative">
    @php
        $imagePath = '';
        
        if ($post->event_id && $post->featuredEventImage) {
            $imagePath = $post->featuredEventImage->largeImagePath ?? $post->featuredEventImage->thumbImagePath;
        } elseif ($post->images && $post->images->isNotEmpty()) {
            $imagePath = $post->images[0]->large_image_path ?? $post->images[0]->thumb_image_path;
        } else {
            $imagePath = $post->largeImagePath ?? $post->thumbImagePath;
        }
    @endphp

    {{-- Header Section - Narrow --}}
    <div class="relative w-full 2xl-air:w-[calc(50%-12px)] md:w-[calc(66.666667%-12px)] mx-auto">
        {{-- Mobile Back Button --}}
        @if (Browser::isMobile())
        <div class="relative bg-white mb-8">
            <div class="flex items-center gap-4">
                <button 
                    onclick="window.history.back()"
                    class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                >
                    <svg 
                        class="w-10 h-10" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <path d="M19 12H5"/>
                        <path d="M12 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>
        </div>
        @endif
        
        {{-- Post Header --}}
        <div class="mb-8 md:mb-12">
            <div class="flex items-center gap-4">
                <h1 class="font-semibold text-4xl md:text-6xl text-black leading-[3rem] md:leading-[5rem]">{{ $post->name }}</h1>
                
                @if (auth()->check() && (auth()->user()->isAdmin || auth()->user()->isModerator || auth()->user()->can('update', $community)))
                    <a 
                        href="/communities/{{ $post->community->slug }}/posts/{{ $post->slug }}/edit" 
                        class="inline-flex items-center justify-center p-4 ml-4 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
                    >
                        <svg 
                            style="width: 1.5rem; height: 1.5rem;"
                            viewBox="0 0 24 24" 
                            fill="none" 
                            stroke="currentColor" 
                            stroke-width="2" 
                            stroke-linecap="round" 
                            stroke-linejoin="round"
                        >
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>
        <div class="mb-8 md:mb-12">
            <p class="text-1xl">
                by <a 
                    href="/communities/{{ $community->slug }}"
                    class="font-semibold hover:underline"
                >{{ $post->user->name }}</a> 
                <span class="mx-2">·</span> 
                {{ \Carbon\Carbon::parse($post->updated_at)->format('F j, Y') }}
            </p>
            
            {{-- Social Share Links --}}
            <div class="flex items-center space-x-10 mt-6">
                {{-- Facebook Share --}}
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" 
                   target="_blank"
                   class="text-black hover:text-blue-600 transition-colors">
                    <svg style="width: 2.5rem; height: 2.5rem;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                    </svg>
                </a>

                {{-- X (Twitter) Share --}}
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->name) }}" 
                   target="_blank"
                   class="text-black hover:text-black transition-colors">
                    <svg style="width: 2.5rem; height: 2.5rem;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>

                {{-- Pinterest Share --}}
                <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(request()->url()) }}&media={{ urlencode($imageUrl . $imagePath) }}&description={{ urlencode($post->name) }}" 
                   target="_blank"
                   class="text-black hover:text-red-600 transition-colors">
                    <svg style="width: 2.5rem; height: 2.5rem;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/>
                    </svg>
                </a>

                {{-- Copy Link --}}
                <button 
                    onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))"
                    class="text-black hover:text-gray-900 transition-colors">
                    <svg style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Post Blurb --}}
        @if($post->blurb)
        <div class="mt-4 mb-12 md:mb-16">
            <div class="card-blurb leading-relaxed">
                {!! $post->blurb !!}
            </div>
        </div>
        @endif
    </div>

    {{-- Featured Image Section - Wide --}}
    @if($imagePath && $post->type !== 'h')
        <div class="m-auto w-full md:w-[83%] xl:w-8/12 my-16">
            <div class="relative aspect-[16/9]">
                <picture>
                    <source 
                        srcset="{{ $imageUrl . str_replace(['.jpg', '.jpeg', '.png'], '.webp', $imagePath) }}"
                        type="image/webp"
                    >
                    <img 
                        src="{{ $imageUrl . $imagePath }}" 
                        class="w-full h-full object-cover rounded-2xl" 
                        alt="{{ $post->name }}"
                    />
                </picture>
            </div>
        </div>
    @endif

    {{-- Cards Section - Narrow --}}
    <div class="relative w-full mt-24">
        {{-- Sticky Social Share Sidebar --}}
        <div class="absolute h-full top-0  lg:left-[-2rem] z-10 hidden md:block">
            <div class="sticky top-48">
                <div class="flex flex-col space-y-12">
                    {{-- Facebook Share --}}
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" 
                    target="_blank"
                    class="text-black hover:text-blue-600 transition-colors">
                        <svg style="width: 2.25rem; height: 2.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                        </svg>
                    </a>

                    {{-- X (Twitter) Share --}}
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->name) }}" 
                    target="_blank"
                    class="text-black hover:text-black transition-colors">
                        <svg style="width: 2.25rem; height: 2.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>

                    {{-- Pinterest Share --}}
                    <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(request()->url()) }}&media={{ urlencode($imageUrl . $imagePath) }}&description={{ urlencode($post->name) }}" 
                    target="_blank"
                    class="text-black hover:text-red-600 transition-colors">
                        <svg style="width: 2.25rem; height: 2.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/>
                        </svg>
                    </a>

                    {{-- Copy Link --}}
                    <button 
                        onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))"
                        class="text-black hover:text-gray-900 transition-colors">
                        <svg style="width: 2.25rem; height: 2.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="relative w-full 2xl-air:w-[calc(50%-12px)] md:w-[calc(66.666667%-12px)] mx-auto">
            <div class="relative space-y-4">
                @foreach($post->cards as $card)
                    <div class="card-wrapper mb-4">
                        @if($card->type === 'i')
                            {{-- Image Card - No border, full width --}}
                            <div class="relative aspect-[16/9] mb-12 md:mb-16">
                                <img 
                                    src="{{ $imageUrl . ($card->images->first()?->large_image_path ?? $card->thumbImagePath) }}" 
                                    class="w-full h-full object-cover rounded-2xl" 
                                    alt="{{ $card->name ?? '' }}"
                                />
                            </div>

                        @elseif($card->type === 't' || $card->type === 'h')
                            {{-- Text Card - Border only if has URL/button --}}
                            @php
                                // Get card image for text cards first
                                $cardImage = null;
                                if ($card->images && $card->images->count() > 0) {
                                    $cardImage = $card->images->first()->large_image_path ?? $card->images->first()->thumbImagePath;
                                } else {
                                    $cardImage = $card->largeImagePath ?? $card->thumbImagePath;
                                }
                                
                                // Determine if card should have border
                                $hasBorder = !empty($card->url) || !empty($card->button_text) || $card->event_id || ($cardImage && $card->type !== 'i' && $card->type !== 'h');
                            @endphp
                            
                            <div class="{{ $hasBorder ? 'border-t md:border border-neutral-400 md:rounded-2xl py-12 md:mb-16 md:p-12 overflow-hidden' : 'mt-4 mb-12 md:mb-16' }}">
                                @if($cardImage && $card->type !== 'h')
                                    {{-- Card with border and image layout --}}
                                    <div class="flex flex-col md:flex-row md:gap-16">
                                        {{-- Image Section --}}
                                        <div class="flex gap-10 w-full md:w-[35%] mb-6 md:mb-0">
                                            <div class="w-1/5 md:w-full">
                                                <div class="aspect-[3/4] w-full rounded-2xl overflow-hidden">
                                                    <img 
                                                        src="{{ $imageUrl . $cardImage }}" 
                                                        class="w-full h-full object-cover" 
                                                        alt="{{ $card->name ?? 'Card image' }}"
                                                    />
                                                </div>
                                            </div>
                                            {{-- Mobile-only title --}}
                                            @if($card->name)
                                                <div class="w-4/5 flex items-center md:hidden">
                                                    <h3 class="text-4xl font-bold mt-0">{{ $card->name }}</h3>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Content Section --}}
                                        <div class="md:w-[65%] md:my-auto">
                                            {{-- Desktop-only title --}}
                                            @if($card->name)
                                                <h3 class="text-4xl font-bold mt-0 mb-6 hidden md:block">{{ $card->name }}</h3>
                                            @endif

                                            <div class="md:mt-6 space-y-6">
                                                {{-- Blurb --}}
                                                @if(Str::of($card->blurb)->stripTags()->trim()->isNotEmpty())
                                                    <div class="card-blurb leading-relaxed">
                                                        {!! $card->blurb !!}
                                                    </div>
                                                @endif

                                                {{-- Button --}}
                                                @if($card->url)
                                                    <div>
                                                        <a 
                                                            href="{{ $card->url }}" 
                                                            target="_blank"
                                                            class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                                            {{ !empty($card->button_text) ? $card->button_text : 'Read More' }}
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{-- Simple text card or text card with hidden image --}}
                                    @if($card->name)
                                        <h3 class="text-4xl font-bold mt-0 mb-6">{{ $card->name }}</h3>
                                    @endif
                                    
                                    <div class="card-blurb leading-relaxed">
                                        {!! $card->blurb !!}
                                    </div>
                                    
                                    @if($card->url)
                                        <div class="mt-6">
                                            <a 
                                                href="{{ $card->url }}" 
                                                target="_blank"
                                                class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                                {{ !empty($card->button_text) ? $card->button_text : 'Read More' }}
                                            </a>
                                        </div>
                                    @endif
                                @endif
                            </div>

                        @elseif($card->type === 'e')
                            {{-- Event Card --}}
                            <div class="border-t md:border border-neutral-400 md:rounded-2xl py-12 md:mb-16 md:p-12 overflow-hidden">
                                @if($card->event)
                                    <div class="flex flex-col md:flex-row md:gap-16">
                                        {{-- Event Image and Mobile Title --}}
                                        @php
                                            // Priority: Card's own image > Event's images > Event's direct image
                                            $cardImage = null;
                                            
                                            // Debug what we have
                                            $debugInfo = [
                                                'card_has_images' => $card->images ? $card->images->count() : 0,
                                                'event_has_images' => $card->event && $card->event->images ? $card->event->images->count() : 0,
                                                'event_largeImagePath' => $card->event->largeImagePath ?? 'null',
                                                'event_thumbImagePath' => $card->event->thumbImagePath ?? 'null',
                                            ];
                                            
                                            // First check if card has its own uploaded image
                                            if ($card->images && $card->images->count() > 0) {
                                                $cardImage = $card->images->first()->large_image_path ?? $card->images->first()->thumbImagePath;
                                                $debugInfo['source'] = 'card_image';
                                            }
                                            // If no card image, check event's images collection
                                            elseif ($card->event && $card->event->images && $card->event->images->count() > 0) {
                                                $eventImg = $card->event->images->first();
                                                $cardImage = $eventImg->large_image_path ?? $eventImg->thumb_image_path ?? $eventImg->largeImagePath ?? $eventImg->thumbImagePath;
                                                $debugInfo['source'] = 'event_images_collection';
                                            }
                                            // Finally fallback to event's direct image properties
                                            elseif ($card->event) {
                                                $cardImage = $card->event->largeImagePath ?? $card->event->thumbImagePath;
                                                $debugInfo['source'] = 'event_direct';
                                            }
                                            
                                            $debugInfo['final_image'] = $cardImage ?? 'null';
                                            
                                            // Temporarily show debug info
                                            // dd($debugInfo);
                                        @endphp

                                        @if($cardImage && $card->type !== 'h')
                                            <div class="flex gap-10 w-full md:w-[35%] mb-6 md:mb-0">
                                                <div class="w-1/5 md:w-full">
                                                    <div class="aspect-[3/4] w-full rounded-2xl overflow-hidden">
                                                        <img 
                                                            src="{{ $imageUrl . $cardImage }}" 
                                                            class="w-full h-full object-cover" 
                                                            alt="{{ $card->name ?? $card->event->name }}"
                                                        />
                                                    </div>
                                                </div>
                                                {{-- Mobile-only title --}}
                                                <div class="w-4/5 flex items-center md:hidden">
                                                    <a href="{{ $card->url ?? '/events/' . $card->event->slug }}">
                                                        <h3 class="text-4xl font-bold mt-0">{{ $card->name ?? $card->event->name }}</h3>
                                                    </a>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Mobile-only title when image is hidden --}}
                                        @if($card->type === 'h')
                                            <div class="mb-6 md:hidden">
                                                <a href="{{ $card->url ?? '/events/' . $card->event->slug }}">
                                                    <h3 class="text-4xl font-bold mt-0">{{ $card->name ?? $card->event->name }}</h3>
                                                </a>
                                            </div>
                                        @endif

                                        {{-- Event Content - Right side on desktop --}}
                                        <div class="{{ ($cardImage && $card->type !== 'h') ? 'md:w-[65%]' : 'w-full' }} md:my-auto">
                                            {{-- Desktop-only title --}}
                                            <a href="{{ $card->url ?? '/events/' . $card->event->slug }}" class="hidden md:block">
                                                <h3 class="text-4xl font-bold mt-0">{{ $card->name ?? $card->event->name }}</h3>
                                            </a>

                                            <div class="md:mt-6 space-y-6">
                                                {{-- Blurb --}}
                                                @if(Str::of($card->blurb)->stripTags()->trim()->isNotEmpty())
                                                    {!! Str::words($card->blurb, 40, '...') !!}
                                                @endif

                                                {{-- Event Dates --}}
                                                @if($card->event)
                                                    <p class="text-gray-600 text-xl">
                                                        Booking Through: {{ \Carbon\Carbon::parse($card->event->closingDate)->format('l, F j Y') }}
                                                    </p>
                                                @endif

                                                {{-- Check it out Button --}}
                                                <div>
                                                    <a 
                                                        href="{{ $card->url ?? '/events/' . $card->event->slug }}" 
                                                        class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                                        {{ !empty($card->button_text) ? $card->button_text : 'Read More' }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection 