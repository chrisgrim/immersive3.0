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

@php
    // Calculate total media count for the gallery button
    $videoCount = ($event->video === 'gallery' && $event->videos && count($event->videos) > 0) ? count($event->videos) : 0;
    $totalMediaCount = count($event->images) + $videoCount;
    
    // For the main display area
    $displayMainVideo = false;
    
    // If there's only one image and there are videos, show a video in the main area
    if (count($event->images) === 1 && $videoCount > 0 && $event->video === 'gallery') {
        $displayMainVideo = true;
    }
    
    // Prepare media arrays differently based on context
    // 1. For main display - prioritize videos if we only have one image
    $mainMediaItems = [];
    
    if ($displayMainVideo) {
        // Start with videos for main display when we have 1 image + videos
        if ($event->videos && count($event->videos) > 0) {
            foreach ($event->videos as $video) {
                $mainMediaItems[] = [
                    'type' => 'video',
                    'platform' => $video->platform,
                    'platform_video_id' => $video->platform_video_id,
                    'url' => $video->url
                ];
            }
        }
        
        // Then add images
        foreach ($event->images as $image) {
            $mainMediaItems[] = $image->toArray();
        }
    } else {
        // Default behavior - add images first
        foreach ($event->images as $image) {
            $mainMediaItems[] = $image->toArray();
        }
        
        // Then add videos
        if ($event->video === 'gallery' && $event->videos && count($event->videos) > 0) {
            foreach ($event->videos as $video) {
                $mainMediaItems[] = [
                    'type' => 'video',
                    'platform' => $video->platform,
                    'platform_video_id' => $video->platform_video_id,
                    'url' => $video->url
                ];
            }
        }
    }
    
    // 2. For gallery display - always show images first, then videos
    $galleryMediaItems = [];
    
    // Images first for gallery
    foreach ($event->images as $image) {
        $galleryMediaItems[] = $image->toArray();
    }
    
    // Then videos
    if ($event->video === 'gallery' && $event->videos && count($event->videos) > 0) {
        foreach ($event->videos as $video) {
            $galleryMediaItems[] = [
                'type' => 'video',
                'platform' => $video->platform,
                'platform_video_id' => $video->platform_video_id,
                'url' => $video->url
            ];
        }
    }
    
    // Share the galleryMediaItems with the JavaScript context
    $galleryMediaItemsJson = json_encode($galleryMediaItems);
@endphp

<div class="relative">
    {{-- No Images or Videos Case --}}
    @if($totalMediaCount === 0)
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

    {{-- Single Media Case --}}
    @elseif($totalMediaCount === 1)
        @if(isset($mainMediaItems[0]['type']) && $mainMediaItems[0]['type'] === 'video')
            <div class="w-full aspect-[3/4]">
                @if($mainMediaItems[0]['platform'] === 'youtube')
                    <div class="relative pt-[56.25%] w-full overflow-hidden">
                        <iframe 
                            class="absolute top-0 left-0 w-full h-full"
                            src="https://www.youtube.com/embed/{{ $mainMediaItems[0]['platform_video_id'] }}"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                @elseif($mainMediaItems[0]['platform'] === 'tiktok')
                    <div class="relative pt-[56.25%] w-full overflow-hidden">
                        <iframe 
                            class="absolute top-0 left-0 w-full h-full"
                            src="https://www.tiktok.com/player/v1/{{ $mainMediaItems[0]['platform_video_id'] }}?music_info=1&description=1&autoplay=0&controls=1"
                            allow="fullscreen" 
                            frameborder="0"
                        ></iframe>
                    </div>
                @endif
            </div>
        @else
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
        @endif

    {{-- Multiple Media Case --}}
    @else
        <div class="w-full aspect-[4/3] relative">
            @if($displayMainVideo && isset($mainMediaItems[0]['type']) && $mainMediaItems[0]['type'] === 'video')
                @if($mainMediaItems[0]['platform'] === 'youtube')
                    <div class="relative w-full h-full overflow-hidden">
                        <iframe 
                            class="absolute top-0 left-0 w-full h-full"
                            src="https://www.youtube.com/embed/{{ $mainMediaItems[0]['platform_video_id'] }}"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                @elseif($mainMediaItems[0]['platform'] === 'tiktok')
                    <div class="relative w-full h-full overflow-hidden">
                        <iframe 
                            class="absolute top-0 left-0 w-full h-full"
                            src="https://www.tiktok.com/player/v1/{{ $mainMediaItems[0]['platform_video_id'] }}?music_info=1&description=1&autoplay=0&controls=1"
                            allow="fullscreen" 
                            frameborder="0"
                        ></iframe>
                    </div>
                @endif
            @else
                <picture>
                    <source 
                        type="image/webp" 
                        srcset="{{ config('app.image_url') }}{{ isset($event->images[1]) ? $event->images[1]->medium_image_path ?? $event->images[1]->large_image_path : ($event->images[0]->medium_image_path ?? $event->images[0]->large_image_path) }}"
                        sizes="(max-width: 768px) 100vw, 768px">
                    <img 
                        class="w-full h-full object-cover"
                        src="{{ config('app.image_url') }}{{ substr(isset($event->images[1]) ? $event->images[1]->medium_image_path ?? $event->images[1]->large_image_path : ($event->images[0]->medium_image_path ?? $event->images[0]->large_image_path), 0, -4) }}jpg"
                        alt="{{ $event->name }} Immersive Event"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                        width="768"
                        height="576"
                    >
                </picture>
            @endif
            
            @if($totalMediaCount > 2)
                <button 
                    onclick="showPhotoGallery()"
                    class="absolute bottom-8 right-10 bg-white/90 backdrop-blur-sm px-6 py-4 rounded-lg text-2xl font-medium">
                    See gallery
                </button>
            @endif
        </div>
    @endif

    {{-- Navigation Icons --}}
    <div class="absolute top-10 left-10 right-10 flex justify-between">
        <button 
            onclick="window.history.back()"
            class="w-20 h-20 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg"
            aria-label="Go back"
        >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>

        <button 
            onclick="handleShare()"
            class="w-20 h-20 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg"
            aria-label="Share"
        >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12"/>
            </svg>
        </button>
    </div>
</div>

<script>
    // Pass the gallery media items to JavaScript
    const galleryMediaItems = @json($galleryMediaItems);
    
    function showPhotoGallery() {
        // Your existing gallery code, but use galleryMediaItems which has images first, then videos
        if (typeof openPhotoGallery === 'function') {
            openPhotoGallery(galleryMediaItems);
        }
    }
</script>
