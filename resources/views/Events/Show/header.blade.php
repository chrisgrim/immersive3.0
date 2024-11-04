<header class="min-h-[200px] relative w-full">
    @if(count($event->images) === 1)
        {{-- Only show image for single image layout --}}
        <div class="relative flex flex-col bg-white md:rounded-[1rem] overflow-hidden">
            <div class="flex bg-white overflow-hidden">
                <picture>
                    <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                    <img 
                        class="h-[43rem] w-auto object-cover" 
                        src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg" 
                        alt="{{ $event['name'] }} Immersive Event - Main Image"
                    >
                </picture>
            </div>
        </div>
    @else
        {{-- Existing layout for multiple images --}}
        <div class="relative w-full m-auto p-0 md:px-12 lg:px-32 lg:max-w-screen-xl">
            <div class="grid grid-cols-1 gap-4 md:rounded-xl overflow-hidden {{ count($event->images) <= 3 ? 'md:grid-cols-[2fr_1fr]' : 'md:grid-cols-2' }}">
                {{-- Left side - Main image --}}
                @if(count($event->images) > 0)
                    <picture class="h-full">
                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                        <img 
                            class="w-full h-[43vh] md:h-[43rem] object-cover" 
                            src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg" 
                            alt="{{ $event['name'] }} Immersive Event - Main Image"
                        >
                    </picture>
                @endif

                {{-- Right side - Different layouts based on image count --}}
                @if(count($event->images) > 1)
                    @if(count($event->images) <= 3)
                        {{-- 2-3 images: Vertical stack in 1/3 column --}}
                        <div class="grid grid-cols-1 gap-4 h-full">
                            @foreach($event->images->slice(1) as $key => $image)
                                <picture>
                                    <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
                                    <img 
                                        class="w-full h-[21rem] object-cover"
                                        src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg" 
                                        alt="{{ $event['name'] }} Immersive Event - Image {{ $key + 2 }}"
                                    >
                                </picture>
                            @endforeach
                        </div>
                    @else
                        {{-- 4-5 images: 2x2 grid in equal column --}}
                        <div class="grid grid-cols-2 gap-4 h-full">
                            @foreach($event->images->slice(1) as $key => $image)
                                <picture>
                                    <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
                                    <img 
                                        class="w-full h-[21rem] object-cover"
                                        src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg" 
                                        alt="{{ $event['name'] }} Immersive Event - Image {{ $key + 2 }}"
                                    >
                                </picture>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</header>