<header class="min-h-[200px] relative w-full mb-32">
    <div class="relative w-full m-auto p-0">
        <div class="grid gap-4">
            <div class="aspect-[3/4] w-full max-w-2xl overflow-hidden md:rounded-2xl">
                <picture>
                    <source type="image/webp" srcset="{{ config('app.image_url') }}{{ count($event->images) === 0 ? $event->largeImagePath : $event->images[0]->large_image_path }}">
                    <img 
                        class="w-full h-full object-cover" 
                        src="{{ config('app.image_url') }}{{ substr(count($event->images) === 0 ? $event->largeImagePath : $event->images[0]->large_image_path, 0, -4) }}jpg" 
                        alt="{{ $event['name'] }} Immersive Event - Main Image"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                    >
                </picture>
            </div>
        </div>
    </div>
</header>