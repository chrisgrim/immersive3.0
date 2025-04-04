<header class="min-h-[200px] relative w-full">
    <div class="relative w-full m-auto p-0 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
        <div class="relative">
            <div class="gap-2 md:rounded-2xl overflow-hidden" style="display: flex;">
                <div class="aspect-[3/4]" style="height: 45rem; flex-shrink: 0;">
                    <picture>
                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                        <img 
                            class="w-full h-full object-cover" 
                            style="height: 45rem;"
                            src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg" 
                            alt="{{ $event['name'] }} Immersive Event - Main Image"
                        >
                    </picture>
                </div>
                @if(count($event->images) > 1)
                    <div style="flex-grow: 1;">
                        <picture>
                            <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[1]->large_image_path }}">
                            <img 
                                class="w-full object-cover" 
                                style="height: 45rem;"
                                src="{{ config('app.image_url') }}{{ substr($event->images[1]->large_image_path, 0, -4) }}jpg" 
                                alt="{{ $event['name'] }} Immersive Event - Image 2"
                            >
                        </picture>
                    </div>
                @endif
            </div>
            @if(count($event->images) > 2)
                <button 
                    onclick="window.dispatchEvent(new CustomEvent('showAllPhotos', { detail: {{ json_encode($event->images) }} }))"
                    class="absolute bottom-6 right-6 bg-white px-6 py-3 rounded-xl shadow-lg font-medium">
                    See all {{ count($event->images) }} photos
                </button>
            @endif
        </div>
    </div>
</header>