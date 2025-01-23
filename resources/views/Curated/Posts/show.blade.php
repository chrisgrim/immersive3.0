@extends('Layouts.master-container')

@php
    $imageUrl = config('app.image_url', 'https://your-default-url.com/');
@endphp

@section('meta')
@endsection 

@section('nav')
    @if (Browser::isMobile())
        @include('Nav.index-mobile')
    @else
        @include('Nav.nav-limited-search')
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
<div class="max-w-screen-2xl-air mx-auto py-36 relative">
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
    <div class="w-full lg:w-1/2 mx-auto px-8">
        {{-- Post Header --}}
        <div class="mb-8 md:mb-12">
            <h1 class="font-semibold text-4xl md:text-6xl text-black leading-[3rem] md:leading-[5rem]">{{ $post->name }}</h1>
        </div>
        <div class="mb-8 md:mb-12">
            <p class="text-1xl">
                by <span class="font-semibold">{{ $post->user->name }}</span> 
                <span class="mx-2">·</span> 
                {{ \Carbon\Carbon::parse($post->updated_at)->format('F j, Y') }}
            </p>
            
            {{-- Social Share Links --}}
            <div class="flex items-center space-x-10 mt-6">
                {{-- Facebook Share --}}
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" 
                   target="_blank"
                   class="text-black hover:text-blue-600 transition-colors">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                    </svg>
                </a>

                {{-- X (Twitter) Share --}}
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->name) }}" 
                   target="_blank"
                   class="text-black hover:text-black transition-colors">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>

                {{-- Pinterest Share --}}
                <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(request()->url()) }}&media={{ urlencode($imageUrl . $imagePath) }}&description={{ urlencode($post->name) }}" 
                   target="_blank"
                   class="text-black hover:text-red-600 transition-colors">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/>
                    </svg>
                </a>

                {{-- Copy Link --}}
                <button 
                    onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))"
                    class="text-black hover:text-gray-900 transition-colors">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <div class="m-auto w-full lg:w-8/12 px-8 my-16">
            <div class="relative aspect-[16/9]">
                <img 
                    src="{{ $imageUrl . $imagePath }}" 
                    class="w-full h-full object-cover rounded-2xl" 
                    alt="{{ $post->name }}"
                />
            </div>
        </div>
    @endif

    {{-- Cards Section - Narrow --}}
    <div class="relative w-full">
        {{-- Sticky Social Share Sidebar --}}
        <div class="absolute h-full top-0 left-12 z-10 hidden md:block">
            <div class="sticky top-48">
                <div class="flex flex-col space-y-12">
                    {{-- Facebook Share --}}
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" 
                    target="_blank"
                    class="text-black hover:text-blue-600 transition-colors">
                        <svg class="w-9 h-9" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                        </svg>
                    </a>

                    {{-- X (Twitter) Share --}}
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->name) }}" 
                    target="_blank"
                    class="text-black hover:text-black transition-colors">
                        <svg class="w-9 h-9" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>

                    {{-- Pinterest Share --}}
                    <a href="https://pinterest.com/pin/create/button/?url={{ urlencode(request()->url()) }}&media={{ urlencode($imageUrl . $imagePath) }}&description={{ urlencode($post->name) }}" 
                    target="_blank"
                    class="text-black hover:text-red-600 transition-colors">
                        <svg class="w-9 h-9" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/>
                        </svg>
                    </a>

                    {{-- Copy Link --}}
                    <button 
                        onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))"
                        class="text-black hover:text-gray-900 transition-colors">
                        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="relative w-full md:w-2/3 lg:w-[70rem] mx-auto px-8">
            <div class="relative space-y-4">
                @foreach($post->cards as $card)
                    <div class="card-wrapper mb-4">
                        @if($card->type === 't')
                            {{-- Text Card --}}
                            <div class="mt-4 mb-12 md:mb-16">
                                <div class="card-blurb leading-relaxed">
                                    {!! $card->blurb !!}
                                </div>
                            </div>

                        @elseif($card->type === 'i')
                            {{-- Image Card --}}
                            <div class="relative aspect-[16/9] mb-12 md:mb-16">
                                <img 
                                    src="{{ $imageUrl . ($card->images->first()?->large_image_path ?? $card->thumbImagePath) }}" 
                                    class="w-full h-full object-cover rounded-2xl" 
                                    alt="{{ $card->name ?? '' }}"
                                />
                            </div>

                        @elseif($card->type === 'e')
                            {{-- Event Card --}}
                            <div class="border rounded-2xl mb-12 md:mb-16 p-12 rounded-2xl overflow-hidden">
                                @if($card->event)
                                    <div class="flex flex-col md:flex-row gap-16 items-center">
                                        {{-- Event Image --}}
                                        @if($card->event->thumbImagePath)
                                            <div class="md:w-[35%] overflow-hidden rounded-2xl">
                                                <div class="aspect-[3/4] w-full">
                                                    <img 
                                                        src="{{ $imageUrl . $card->event->thumbImagePath }}" 
                                                        class="w-full h-full object-cover" 
                                                        alt="{{ $card->event->name }}"
                                                    />
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Event Content --}}
                                        <div class="space-y-6 md:w-[65%] my-auto">
                                            {{-- Event Title --}}
                                            <h3 class="text-4xl font-bold mt-0">{{ $card->event->name }}</h3>

                                            @if(Str::of($card->blurb)->stripTags()->trim()->isNotEmpty())
                                                <p class="text-2xl mt-0">{!! $card->blurb !!}</p>
                                            @endif

                                            {{-- Event Dates --}}
                                            <p class="text-gray-600 text-xl">
                                                Booking Through: {{ \Carbon\Carbon::parse($card->event->end_date)->format('l, F j Y') }}
                                            </p>

                                            {{-- Check it out Button --}}
                                            <div>
                                                <a 
                                                    href="/events/{{ $card->event->slug }}" 
                                                    class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                                    Check it out
                                                </a>
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
@endsection 