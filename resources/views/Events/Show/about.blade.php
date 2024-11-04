<section>
    <div class="px-8 md:px-0">
        <div class="py-8 flex items-center gap-4 my-4 border-b">
            @if($event->organizer->thumbImagePath)
                <a href="{{ route('Organizers.show', $event->organizer->slug) }}">
                    <picture>
                        <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $event->organizer->thumbImagePath }}">
                        <img 
                            class="w-16 h-16 rounded-full" 
                            src="{{ config('app.image_url') }}{{ substr($event->organizer->thumbImagePath, 0, -4) }}jpg" 
                            alt="Logo of {{ $event->organizer->name }}"
                        >
                    </picture>
                </a>
            @endif
            <h2 class="text-2xl font-medium">
                Experience hosted by
                <a href="{{ route('Organizers.show', $event->organizer->slug) }}" class="hover:underline">
                    {{ $event->organizer->name }}
                </a>
            </h2>
        </div>
        <div class="py-8">
            <vue-show-more text="{{ $event['description']}}" :limit="100" />
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
