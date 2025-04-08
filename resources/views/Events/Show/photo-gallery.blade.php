@php
    // Access the combined media items from parent or create them here if they don't exist
    if (!isset($mediaItems)) {
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
    }
@endphp

<style>
    @keyframes pulse-right {
        0% { transform: translateX(0); }
        50% { transform: translateX(5px); }
        100% { transform: translateX(0); }
    }
    .pulse-right-arrow {
        animation: pulse-right 2s infinite ease-in-out;
    }
</style>

<div class="relative flex flex-col h-[45rem]">
    <div class="justify-between items-center flex px-8 lg-air:px-16 2xl-air:px-32">
        <button 
            onclick="closePhotoGallery()"
            class="absolute bottom-6 right-6 bg-white px-6 py-3 rounded-xl shadow-lg font-medium">
            Close Gallery
        </button>
    </div>

    <div class="flex-1 flex items-center overflow-y-hidden overflow-x-auto whitespace-nowrap scrollbar-hide">
        <div id="leftArrow" class="absolute left-8 lg-air:left-16 2xl-air:left-32 z-10 hidden">
            <button 
                aria-label="Previous Photo"
                class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                onclick="scrollPhotoLeft()">
                <svg class="w-2/4 h-full m-auto">
                    <use href="/storage/website-files/icons.svg#ri-arrow-left-s-line" />
                </svg>
            </button>
        </div>

        <div id="photo-scroll-container" 
             class="overflow-x-auto flex scroll-p-10 lg:scroll-p-32 scrollbar-hide" 
             style="scroll-snap-type: x mandatory;"
             onscroll="checkScrollPosition()">
            @foreach($mediaItems as $index => $media)
                <div class="ml-10 first:ml-0 snap-start snap-always first:pl-8 last:pr-8 md:first:pl-32 md:last:pr-32">
                    <div class="block w-full">
                        <div class="rounded-2xl overflow-hidden h-full border border-gray-300" style="width: {{ $index === 0 ? '33.65rem' : '67rem' }}">
                            <div class="w-full" style="aspect-ratio: {{ $index === 0 ? '3/4' : '3/2' }}">
                                @if(isset($media['type']) && $media['type'] === 'video')
                                    @if($media['platform'] === 'youtube')
                                        <iframe
                                            src="https://www.youtube.com/embed/{{ $media['platform_video_id'] }}"
                                            class="w-full h-full"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen
                                        ></iframe>
                                    @elseif($media['platform'] === 'tiktok')
                                        <iframe
                                            class="w-full h-full"
                                            src="https://www.tiktok.com/player/v1/{{ $media['platform_video_id'] }}?music_info=1&description=1&autoplay=0&controls=1"
                                            allow="fullscreen"
                                            frameborder="0"
                                        ></iframe>
                                    @endif
                                @else
                                    <picture>
                                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $media['large_image_path'] }}">
                                        <img 
                                            class="h-full w-full object-cover"
                                            loading="lazy" 
                                            src="{{ config('app.image_url') }}{{ substr($media['large_image_path'], 0, -4) }}jpg"
                                            alt="{{ $event->name }} - Photo {{ $index + 1 }}">
                                    </picture>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div id="rightArrow" class="absolute right-8 lg-air:right-16 2xl-air:right-32 z-10">
            <button 
                aria-label="Next Photo"
                class="rounded-full w-16 h-16 border border-gray-300 p-0 bg-white hover:shadow-lg transition-shadow flex items-center justify-center shadow-md" 
                onclick="scrollPhotoRight()">
                <svg class="w-8 h-8 text-neutral-800">
                    <use href="/storage/website-files/icons.svg#ri-arrow-right-s-line" />
                </svg>
            </button>
        </div>
    </div>
</div>
