<section>
    <div class="{{ !$event->eventreviews || count($event->eventreviews) === 0 ? 'border-b border-neutral-200' : '' }}">
        <div class="pt-8 md:pt-0 pb-8 md:pb-16">
            {{-- Inner text is discarded as slot content when Vue mounts, but keeps the
                 description visible to non-JS crawlers (AI assistants don't execute JS) --}}
            <vue-show-more text="{{ $event['description']}}" :limit="70">
                <p v-pre class="text-3.5xl md:text-2.5xl leading-normal md:leading-9 whitespace-pre-wrap">{{ $event['description'] }}</p>
            </vue-show-more>
        </div>

        {{-- Facts grid — matches TodayTix's About-section pattern: icon, bold
             label, value below (linked where the value has somewhere to go). --}}
        <div class="grid grid-cols-2 gap-x-8 md:gap-x-16 gap-y-12 py-12 md:pt-0 md:pb-16">
            <div class="flex items-start gap-4">
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
                <div>
                    <p class="text-2xl md:text-1xl leading-tight font-semibold">Organizer</p>
                    <a href="{{ route('organizers.show', $event->organizer->slug) }}" class="text-xl font-medium text-neutral-500 hover:text-black">
                        {{ $event->organizer->name }}
                    </a>
                </div>
            </div>

            <div class="flex items-start gap-4">
                <svg class="w-8 h-8 flex-shrink-0 mt-1" xmlns="http://www.w3.org/2000/svg">
                    <use xlink:href="/storage/website-files/icons.svg#ri-map-pin-line" />
                </svg>
                <div>
                    <p class="text-2xl md:text-1xl leading-tight font-semibold">Venue</p>
                    @if($event->hasLocation && $event->location && $event->location->venue)
                        <p class="text-xl font-medium text-neutral-500">{{ $event->location->venue }}</p>
                    @else
                        <p class="text-xl font-medium text-neutral-500">{{ ucfirst($event->remoteLocations->first()?->name ?? 'Remote Event') }}</p>
                    @endif
                </div>
            </div>

            @if($event->category)
                <div class="flex items-start gap-4">
                    <svg class="w-8 h-8 flex-shrink-0 mt-1" xmlns="http://www.w3.org/2000/svg">
                        <use xlink:href="/storage/website-files/icons.svg#ri-price-tag-3-line" />
                    </svg>
                    <div>
                        <p class="text-2xl md:text-1xl leading-tight font-semibold">Category</p>
                        <a href="/index/search?category={{ $event->category->id }}&searchType=allEvents" class="text-xl font-medium text-neutral-500 hover:text-black">
                            {{ $event->category->name }}
                        </a>
                    </div>
                </div>
            @endif

            @if($primaryGenre)
                <div class="flex items-start gap-4">
                    <svg class="w-8 h-8 flex-shrink-0 mt-1" xmlns="http://www.w3.org/2000/svg">
                        <use xlink:href="/storage/website-files/icons.svg#ri-price-tag-3-line" />
                    </svg>
                    <div>
                        <p class="text-2xl md:text-1xl leading-tight font-semibold">Genre</p>
                        <a href="/index/search?tag={{ $primaryGenre->id }}&searchType=allEvents" class="text-xl font-medium text-neutral-500 hover:text-black">
                            {{ $primaryGenre->name }}
                        </a>
                    </div>
                </div>
            @endif

            @if($event->shows->isNotEmpty())
                <div class="flex items-start gap-4">
                    <svg class="w-8 h-8 flex-shrink-0 mt-1" xmlns="http://www.w3.org/2000/svg">
                        <use xlink:href="/storage/website-files/icons.svg#ri-calendar-line" />
                    </svg>
                    <div>
                        <p class="text-2xl md:text-1xl leading-tight font-semibold">Start date</p>
                        <p class="text-xl font-medium text-neutral-500">{{ \Illuminate\Support\Carbon::parse($event->shows->min('date'))->format('F jS, Y') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <svg class="w-8 h-8 flex-shrink-0 mt-1" xmlns="http://www.w3.org/2000/svg">
                        <use xlink:href="/storage/website-files/icons.svg#ri-calendar-line" />
                    </svg>
                    <div>
                        <p class="text-2xl md:text-1xl leading-tight font-semibold">End date</p>
                        <p class="text-xl font-medium text-neutral-500">{{ \Illuminate\Support\Carbon::parse($event->shows->max('date'))->format('F jS, Y') }}</p>
                    </div>
                </div>
            @endif
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
