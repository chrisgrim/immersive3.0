@php
    // Access the combined media items from parent or create them here if they don't exist
    if (!isset($mediaItems)) {
        // Prioritize videos by placing them first
        $mediaItems = [];
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
        
        // Add images after videos
        foreach ($event->images as $image) {
            $mediaItems[] = $image->toArray();
        }
    }
@endphp

<div class="fixed inset-0 bg-white z-49 overflow-y-auto">
    <!-- Header with back and share buttons -->
    <div class="sticky top-0 bg-white px-10 py-6 flex justify-between items-center">
        <!-- Back Button -->
        <button 
            onclick="closePhotoGallery()"
            class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg"
        >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>

        <h1 class="text-2xl font-semibold">Gallery</h1>

        <!-- Share Button -->
        <button 
            onclick="handleShare()"
            class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg"
        >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12"/>
            </svg>
        </button>
    </div>

    <!-- Media Grid -->
    <div class="p-10 space-y-10">
        @foreach($mediaItems as $index => $media)
            <div class="w-full">
                @if(isset($media['type']) && $media['type'] === 'video')
                    @if($media['platform'] === 'youtube')
                        <div class="relative pt-[56.25%] w-full overflow-hidden rounded-lg">
                            <iframe
                                class="absolute top-0 left-0 w-full h-full"
                                src="https://www.youtube.com/embed/{{ $media['platform_video_id'] }}"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    @elseif($media['platform'] === 'tiktok')
                        <div class="relative pt-[56.25%] w-full overflow-hidden rounded-lg">
                            <iframe
                                class="absolute top-0 left-0 w-full h-full"
                                src="https://www.tiktok.com/player/v1/{{ $media['platform_video_id'] }}?music_info=1&description=1&autoplay=0&controls=1"
                                allow="fullscreen"
                                frameborder="0"
                            ></iframe>
                        </div>
                    @endif
                @else
                    <picture>
                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $media['large_image_path'] }}">
                        <img 
                            class="w-full object-cover rounded-lg"
                            src="{{ config('app.image_url') }}{{ substr($media['large_image_path'], 0, -4) }}jpg"
                            alt="{{ $event->name }} Immersive Event - Photo {{ $index + 1 }}"
                        >
                    </picture>
                @endif
            </div>
        @endforeach
    </div>
</div>