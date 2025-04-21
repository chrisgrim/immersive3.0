<header class="min-h-[200px] relative w-full mb-16">
    <div class=" w-full aspect-[16/9] overflow-hidden md:rounded-2xl">
        <picture>
            <source type="image/webp" srcset="{{ config('app.image_url') }}{{ count($event->images) === 0 ? $event->largeImagePath : $event->images[0]->large_image_path }}">
            <img 
                class="w-full h-full object-cover" 
                src="{{ config('app.image_url') }}{{ substr(count($event->images) === 0 ? $event->largeImagePath : $event->images[0]->large_image_path, 0, -4) }}jpg" 
                alt="{{ $event['name'] }} Immersive Event - Main Image"
            >
        </picture>
    </div>
</header>