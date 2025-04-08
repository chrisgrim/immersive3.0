@php
    // Calculate total media count - include videos if gallery mode is enabled
    $videoCount = ($event->video === 'gallery' && $event->videos && count($event->videos) > 0) ? count($event->videos) : 0;
    $totalMediaCount = count($event->images) + $videoCount;
    
    // Prepare a combined media array if in gallery mode
    $mediaItems = $event->images->toArray();
    if ($event->video === 'gallery' && $event->videos && count($event->videos) > 0) {
        foreach ($event->videos as $video) {
            $mediaItems[] = [
                'type' => 'video',
                'platform' => $video->platform,
                'platform_video_id' => $video->platform_video_id,
                'url' => $video->url
            ];
        }
    }
@endphp

<header class="min-h-[200px] relative w-full">
    <div class="relative w-full m-auto p-0 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
        <div class="relative">
            <div class="gap-2 md:rounded-2xl overflow-hidden" style="display: flex;">
                <div class="aspect-[3/4]" style="height: 45rem; flex-shrink: 0;">
                    @if(isset($mediaItems[0]['type']) && $mediaItems[0]['type'] === 'video')
                        @if($mediaItems[0]['platform'] === 'youtube')
                            <div class="w-full h-full">
                                <iframe
                                    src="https://www.youtube.com/embed/{{ $mediaItems[0]['platform_video_id'] }}"
                                    class="w-full h-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        @elseif($mediaItems[0]['platform'] === 'tiktok')
                            <div class="w-full h-full">
                                <iframe
                                    class="w-full h-full"
                                    src="https://www.tiktok.com/player/v1/{{ $mediaItems[0]['platform_video_id'] }}?music_info=1&description=1&autoplay=0&controls=1"
                                    allow="fullscreen"
                                    frameborder="0"
                                ></iframe>
                            </div>
                        @endif
                    @else
                        <picture>
                            <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                            <img 
                                class="w-full h-full object-cover" 
                                style="height: 45rem;"
                                src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg" 
                                alt="{{ $event['name'] }} Immersive Event - Main Image"
                            >
                        </picture>
                    @endif
                </div>
                @if(count($mediaItems) > 1)
                    <div style="flex-grow: 1;">
                        @if(isset($mediaItems[1]['type']) && $mediaItems[1]['type'] === 'video')
                            @if($mediaItems[1]['platform'] === 'youtube')
                                <div class="w-full h-full">
                                    <iframe
                                        src="https://www.youtube.com/embed/{{ $mediaItems[1]['platform_video_id'] }}"
                                        class="w-full h-full"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                    ></iframe>
                                </div>
                            @elseif($mediaItems[1]['platform'] === 'tiktok')
                                <div class="w-full h-full">
                                    <iframe
                                        class="w-full h-full"
                                        src="https://www.tiktok.com/player/v1/{{ $mediaItems[1]['platform_video_id'] }}?music_info=1&description=1&autoplay=0&controls=1"
                                        allow="fullscreen"
                                        frameborder="0"
                                    ></iframe>
                                </div>
                            @endif
                        @elseif(count($event->images) > 1)
                            <picture>
                                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[1]->large_image_path }}">
                                <img 
                                    class="w-full object-cover" 
                                    style="height: 45rem;"
                                    src="{{ config('app.image_url') }}{{ substr($event->images[1]->large_image_path, 0, -4) }}jpg" 
                                    alt="{{ $event['name'] }} Immersive Event - Image 2"
                                >
                            </picture>
                        @endif
                    </div>
                @endif
            </div>
            @if(count($mediaItems) > 2)
                <button 
                    onclick="window.dispatchEvent(new CustomEvent('showAllPhotos', { detail: {{ json_encode($mediaItems) }} }))"
                    class="absolute bottom-6 right-6 bg-white px-6 py-3 rounded-xl shadow-lg font-medium">
                    See all {{ count($mediaItems) }} items
                </button>
            @endif
        </div>
    </div>
</header>