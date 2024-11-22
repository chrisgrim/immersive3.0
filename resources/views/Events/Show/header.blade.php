<header class="min-h-[200px] relative w-full">
    <div @class([
        'relative w-full m-auto p-0' => true,
        'md:px-12 lg:px-32 lg:max-w-screen-xl' => count($event->images) > 1
    ])>
        <div class="grid gap-4">
            @if(count($event->images) === 0 || count($event->images) === 1)
                {{-- Single Image or Empty Images Array --}}
                <div class="aspect-[3/2] w-full overflow-hidden md:rounded-xl">
                    <picture>
                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ count($event->images) === 0 ? $event->largeImagePath : $event->images[0]->large_image_path }}">
                        <img 
                            class="w-full h-full object-cover" 
                            src="{{ config('app.image_url') }}{{ substr(count($event->images) === 0 ? $event->largeImagePath : $event->images[0]->large_image_path, 0, -4) }}jpg" 
                            alt="{{ $event['name'] }} Immersive Event - Main Image"
                        >
                    </picture>
                </div>
            @else
                {{-- Multiple Images --}}
                <div class="grid gap-2 md:rounded-xl overflow-hidden grid-cols-2"
                    @class([
                        'grid-cols-2' => count($event->images) === 2 || count($event->images) >= 4,
                        'grid-cols-[2fr_1fr]' => count($event->images) === 3
                    ])>
                    {{-- Left Side --}}
                    @if(count($event->images) >= 4)
                        <div class="grid gap-2 overflow-hidden">
                            <div class="aspect-[3/2]">
                                <picture>
                                    <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                                    <img 
                                        class="w-full h-full object-cover" 
                                        src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg" 
                                        alt="{{ $event['name'] }} Immersive Event - Main Image"
                                    >
                                </picture>
                            </div>
                            @if(count($event->images) === 4)
                                <div class="aspect-[3/2]">
                                    <picture>
                                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[1]->large_image_path }}">
                                        <img 
                                            class="w-full h-full object-cover" 
                                            src="{{ config('app.image_url') }}{{ substr($event->images[1]->large_image_path, 0, -4) }}jpg" 
                                            alt="{{ $event['name'] }} Immersive Event - Image 2"
                                        >
                                    </picture>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="aspect-[3/2]">
                            <picture>
                                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[0]->large_image_path }}">
                                <img 
                                    class="w-full h-full object-cover" 
                                    src="{{ config('app.image_url') }}{{ substr($event->images[0]->large_image_path, 0, -4) }}jpg" 
                                    alt="{{ $event['name'] }} Immersive Event - Main Image"
                                >
                            </picture>
                        </div>
                    @endif

                    {{-- Right Side --}}
                    @if(count($event->images) === 2)
                        <div class="aspect-[3/2]">
                            <picture>
                                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->images[1]->large_image_path }}">
                                <img 
                                    class="w-full h-full object-cover" 
                                    src="{{ config('app.image_url') }}{{ substr($event->images[1]->large_image_path, 0, -4) }}jpg" 
                                    alt="{{ $event['name'] }} Immersive Event - Image 2"
                                >
                            </picture>
                        </div>
                    @elseif(count($event->images) === 3)
                        <div class="grid gap-2">
                            @foreach($event->images->slice(1) as $key => $image)
                                <div class="aspect-[3/2]">
                                    <picture>
                                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
                                        <img 
                                            class="w-full h-full object-cover" 
                                            src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg" 
                                            alt="{{ $event['name'] }} Immersive Event - Image {{ $key + 2 }}"
                                        >
                                    </picture>
                                </div>
                            @endforeach
                        </div>
                    @elseif(count($event->images) === 4)
                        <div class="grid gap-2">
                            @foreach($event->images->slice(2) as $key => $image)
                                <div class="aspect-[3/2]">
                                    <picture>
                                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
                                        <img 
                                            class="w-full h-full object-cover" 
                                            src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg" 
                                            alt="{{ $event['name'] }} Immersive Event - Image {{ $key + 3 }}"
                                        >
                                    </picture>
                                </div>
                            @endforeach
                        </div>
                    @elseif(count($event->images) === 5)
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($event->images->slice(1, 4) as $key => $image)
                                <div>
                                    <picture>
                                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
                                        <img 
                                            class="w-full h-full object-cover" 
                                            src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg" 
                                            alt="{{ $event['name'] }} Immersive Event - Image {{ $key + 2 }}"
                                        >
                                    </picture>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</header>