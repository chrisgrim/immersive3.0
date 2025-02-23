<div class="relative">
    {{-- No Images Case --}}
    @if(count($event->images) === 0)
        <div class="w-full aspect-[4/3]">
            <picture>
                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->largeImagePath }}">
                <img 
                    class="w-full h-full object-cover"
                    src="{{ config('app.image_url') }}{{ substr($event->largeImagePath, 0, -4) }}jpg"
                    alt="{{ $event->name }} Immersive Event"
                >
            </picture>
        </div>

    {{-- Single Image Case --}}
    @elseif(count($event->images) === 1)
        <div class="w-full mx-auto aspect-[3/4]">
            <picture>
                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                <img 
                    class="w-full h-full object-cover rounded-b-2xl"
                    src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg"
                    alt="{{ $event->name }} Immersive Event"
                >
            </picture>
        </div>

    {{-- Multiple Images Case --}}
    @else
        <div class="w-full aspect-[4/3] relative">
            <picture>
                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[1]->large_image_path }}">
                <img 
                    class="w-full h-full object-cover"
                    src="{{ config('app.image_url') }}{{ substr($event->images[1]->large_image_path, 0, -4) }}jpg"
                    alt="{{ $event->name }} Immersive Event"
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
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>

        <button 
            onclick="handleShare()"
            class="w-14 h-14 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-lg"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12"/>
            </svg>
        </button>
    </div>
</div>
