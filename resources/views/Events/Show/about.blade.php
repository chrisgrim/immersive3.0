<section>
    <div class="border-b border-neutral-200">
        <div class="py-8 md:py-16 flex items-center gap-4 border-b">
            <a href="{{ route('organizers.show', $event->organizer->slug) }}" class="flex items-center gap-8">
                @if($event->organizer->thumbImagePath)
                    <div class="w-16 h-16 rounded-full overflow-hidden">
                        <picture>
                            <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->organizer->thumbImagePath }}">
                            <img 
                                class="w-full h-full object-cover" 
                                src="{{ config('app.image_url') }}{{ substr($event->organizer->thumbImagePath, 0, -4) }}jpg" 
                                alt="Logo of {{ $event->organizer->name }}"
                            >
                        </picture>
                    </div>
                @else
                    <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center">
                        <span class="text-2xl font-bold text-gray-400">
                            {{ Str::upper(Str::substr($event->organizer->name, 0, 1)) }}
                        </span>
                    </div>
                @endif
                
                <div class="flex flex-col">
                    <p class="text-3xl md:text-1xl font-semibold font-medium">Hosted by {{ $event->organizer->name }}</p>
                    <p class="text-2xl md:text-xl font-medium text-neutral-500">{{ count($event->organizer->events) }} events</p>
                </div>
            </a>
        </div>
        
        <div class="py-8 md:py-16">
            <vue-show-more text="{{ $event['description']}}" :limit="70" />
        </div>
    </div>
    
    @if($event->video)
        <div class="w-full p-8 md:py-16 md:px-0">
            <vue-video-player
                alt="{{$event->name . ' Immersive Event'}}"
                src="{{$event->video}}"
            />
        </div>
    @endif
</section>
