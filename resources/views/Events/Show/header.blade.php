<header class="min-h-[200px] relative w-full m-auto p-0 md:px-12 lg:px-32 lg:max-w-screen-xl">
    @foreach($event->images as $image)
        <picture>
            <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
            <img class="min-h-[200px] h-[43vh] w-full object-cover md:rounded-xl md:h-[40rem]" src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg" alt="{{ $event['name'] }} Immersive Event">
        </picture>
    @endforeach
</header>