@push('head')
    {{-- Add DNS prefetch and preconnect --}}
    <link rel="dns-prefetch" href="{{ parse_url(config('app.image_url'), PHP_URL_HOST) }}">
    <link rel="preconnect" href="{{ config('app.image_url') }}" crossorigin>

    @if(count($event->images) === 0)
        <link 
            rel="preload" 
            as="image" 
            href="{{ config('app.image_url') }}{{ $event->mediumImagePath ?? $event->largeImagePath }}" 
            type="image/webp" 
            fetchpriority="high"
            imagesizes="(max-width: 768px) 100vw, 768px"
            imagesrcset="{{ config('app.image_url') }}{{ $event->smallImagePath ?? $event->largeImagePath }} 400w,
                         {{ config('app.image_url') }}{{ $event->mediumImagePath ?? $event->largeImagePath }} 768w,
                         {{ config('app.image_url') }}{{ $event->largeImagePath }} 1200w">
    @elseif(count($event->images) === 1)
        <link 
            rel="preload" 
            as="image" 
            href="{{ config('app.image_url') }}{{ $event->images[0]->medium_image_path ?? $event->images[0]->large_image_path }}" 
            type="image/webp" 
            fetchpriority="high"
            imagesizes="(max-width: 768px) 100vw, 768px"
            imagesrcset="{{ config('app.image_url') }}{{ $event->images[0]->small_image_path ?? $event->images[0]->large_image_path }} 400w,
                         {{ config('app.image_url') }}{{ $event->images[0]->medium_image_path ?? $event->images[0]->large_image_path }} 768w,
                         {{ config('app.image_url') }}{{ $event->images[0]->large_image_path }} 1200w">
    @elseif(count($event->images) > 1)
        <link 
            rel="preload" 
            as="image" 
            href="{{ config('app.image_url') }}{{ $event->images[1]->medium_image_path ?? $event->images[1]->large_image_path }}" 
            type="image/webp" 
            fetchpriority="high"
            imagesizes="(max-width: 768px) 100vw, 768px"
            imagesrcset="{{ config('app.image_url') }}{{ $event->images[1]->small_image_path ?? $event->images[1]->large_image_path }} 400w,
                         {{ config('app.image_url') }}{{ $event->images[1]->medium_image_path ?? $event->images[1]->large_image_path }} 768w,
                         {{ config('app.image_url') }}{{ $event->images[1]->large_image_path }} 1200w">
    @endif

    {{-- Add critical CSS inline --}}
    <style>
        .hero-image {
            content-visibility: auto;
            contain-intrinsic-size: 400px;
        }
    </style>
@endpush

<div class="relative">
    {{-- No Images Case --}}
    @if(count($event->images) === 0)
        <div class="w-full aspect-[4/3] hero-image">
            <picture>
                <source 
                    type="image/webp" 
                    srcset="{{ config('app.image_url') }}{{ $event->smallImagePath ?? $event->largeImagePath }} 400w,
                            {{ config('app.image_url') }}{{ $event->mediumImagePath ?? $event->largeImagePath }} 768w,
                            {{ config('app.image_url') }}{{ $event->largeImagePath }} 1200w"
                    sizes="(max-width: 768px) 100vw, 768px">
                <img 
                    class="w-full h-full object-cover"
                    src="{{ config('app.image_url') }}{{ substr($event->mediumImagePath ?? $event->largeImagePath, 0, -4) }}jpg"
                    alt="{{ $event->name }} Immersive Event"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    width="768"
                    height="576"
                >
            </picture>
        </div>

    {{-- Single Image Case --}}
    @elseif(count($event->images) === 1)
        <div class="w-full mx-auto aspect-[3/4]">
            <picture>
                <source 
                    type="image/webp" 
                    srcset="{{ config('app.image_url') }}{{ $event->images[0]->medium_image_path ?? $event->images[0]->large_image_path }}"
                    sizes="(max-width: 768px) 100vw, 768px">
                <img 
                    class="w-full h-full object-cover rounded-b-2xl"
                    src="{{ config('app.image_url') }}{{ substr($event->images[0]->medium_image_path ?? $event->images[0]->large_image_path, 0, -4) }}jpg"
                    alt="{{ $event->name }} Immersive Event"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    width="768"
                    height="1024"
                >
            </picture>
        </div>

    {{-- Multiple Images Case --}}
    @else
        <div class="w-full aspect-[4/3] relative">
            <picture>
                <source 
                    type="image/webp" 
                    srcset="{{ config('app.image_url') }}{{ $event->images[1]->medium_image_path ?? $event->images[1]->large_image_path }}"
                    sizes="(max-width: 768px) 100vw, 768px">
                <img 
                    class="w-full h-full object-cover"
                    src="{{ config('app.image_url') }}{{ substr($event->images[1]->medium_image_path ?? $event->images[1]->large_image_path, 0, -4) }}jpg"
                    alt="{{ $event->name }} Immersive Event"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    width="768"
                    height="576"
                >
            </picture>
            
            @if(count($event->images) > 2)
                <button 
                    onclick="showPhotoGallery()"
                    class="absolute bottom-8 right-10 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-lg text-sm font-medium">
                    See all photos
                </button>
            @endif
        </div>
    @endif

    {{-- Navigation Icons --}}
    <div class="absolute top-10 left-10 right-10 flex justify-between">
        <button 
            onclick="window.history.back()"
            class="w-14 h-14 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg"
            aria-label="Go back"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>

        <button 
            onclick="handleShare()"
            class="w-14 h-14 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg"
            aria-label="Share"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12"/>
            </svg>
        </button>
    </div>
</div>
