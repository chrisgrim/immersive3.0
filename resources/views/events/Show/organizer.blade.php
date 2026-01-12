<section class="py-16">
    <div class="flex flex-col md:flex-row gap-8 md:gap-16">
        <!-- Left Column - Profile Card -->
        <div class="md:w-[36rem] mb-8 md:mb-0">
            <div class="flex flex-row shadow-custom-6 w-full p-8 py-16 rounded-3xl gap-8">
                <!-- Left Column - Image and Name -->
                <div class="flex flex-col items-center w-2/3">
                    <a href="/organizers/{{ $event->organizer->slug }}" class="flex flex-col items-center">
                        <!-- Profile Image -->
                        <div class="w-44 flex-shrink-0">
                            <div class="relative w-full">
                                <div class="relative w-full aspect-square">
                                    <div class="w-full h-full rounded-full overflow-hidden shadow-sm">
                                        @if($event->organizer->thumbImagePath)
                                            <picture>
                                                <source 
                                                    type="image/webp" 
                                                    srcset="{{ config('app.image_url') . $event->organizer->thumbImagePath }}"
                                                > 
                                                <img 
                                                    class="w-full h-full object-cover"
                                                    src="{{ config('app.image_url') . substr($event->organizer->thumbImagePath, 0, -4) }}jpg"
                                                    alt="{{ $event->organizer->name }} organizer"
                                                >
                                            </picture>
                                        @else
                                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                                <span class="text-6xl font-bold text-gray-400">
                                                    {{ Str::upper(Str::substr($event->organizer->name, 0, 1)) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Name -->
                        <div class="w-full flex justify-center px-4">
                            <h3 class="text-3xl text-black font-medium leading-tight mt-8 text-center break-words hyphens-auto md:max-w-[25rem] overflow-hidden">
                                {{ $event->organizer->name }}
                            </h3>
                        </div>
                    </a>
                </div>

                <!-- Right Column - Info -->
                <div class="flex-1 flex flex-col space-y-8 m-auto">
                    <!-- Stats -->
                    <a href="/organizers/{{ $event->organizer->slug }}" class="flex flex-col items-start group cursor-pointer">
                        <p class="text-5xl font-semibold text-gray-900">
                            {{ $event->organizer->events_count ?? count($event->organizer->events) }}
                        </p>
                        <p class="text-md font-bold text-gray-600">
                            Events
                        </p>
                    </a>
                    
                    <div class="w-24 h-px bg-gray-200"></div>
                    
                    <div class="flex flex-col items-start">
                        <p class="text-5xl font-semibold text-gray-900">
                            {{ ceil(\Carbon\Carbon::parse($event->organizer->created_at)->diffInYears(now())) }}
                        </p>
                        <p class="text-md font-bold text-gray-600">
                            Years on EI
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Description -->
        <div class="flex-1">
            <div class="whitespace-pre-wrap">
                <div class="flex items-center gap-8 mb-8">
                    <h3 class="text-black text-3xl font-bold leading-tight break-words hyphens-auto">
                        About {{ $event->organizer->name }}
                    </h3>
                </div>
                <div class="text-3xl mt-8 break-words hyphens-auto">
                    <vue-show-more text="{{ $event->organizer->description }}" :limit="70" />
                </div>
            </div>
        </div>
    </div>
</section> 

