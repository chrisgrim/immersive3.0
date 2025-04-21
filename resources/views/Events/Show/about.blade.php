<section>
    <div class="border-b border-neutral-200">
        <div class="py-8 flex items-center gap-4 border-b md:pb-16 md:pt-0">
            <a href="{{ route('organizers.show', $event->organizer->slug) }}" class="flex items-center gap-8">
                @if($event->organizer->thumbImagePath)
                    <div class="w-16 h-16 rounded-full overflow-hidden flex-shrink-0">
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
                    <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
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
    
    @if($event->video === 'page' && $event->videos && count($event->videos) > 0)
        <div class="w-full p-8 md:py-16 md:px-0">
            @foreach($event->videos as $video)
                <div class="mb-8">
                    @if($video->platform === 'youtube')
                        <div class="relative pt-[56.25%] w-full overflow-hidden rounded-xl">
                            <iframe
                                class="absolute top-0 left-0 w-full h-full"
                                src="https://www.youtube.com/embed/{{ $video->platform_video_id }}"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    @elseif($video->platform === 'tiktok')
                        <div class="relative pt-[56.25%] w-full overflow-hidden rounded-xl">
                            <iframe 
                                class="absolute top-0 left-0 w-full h-full"
                                src="https://www.tiktok.com/player/v1/{{ $video->platform_video_id }}?music_info=1&description=1&autoplay=0&controls=1"
                                allow="fullscreen" 
                                frameborder="0"
                            ></iframe>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
