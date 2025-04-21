<div id="mainContent">
    @include('events.show.share-modal')
    <div id="bodyArea" class="show">
        <div class="show-content">
            @include('events.show.header-mobile')
            <div class="relative w-full m-auto px-10">
                <div class="">
                    <div class="flex-grow">
                        @if($totalMediaCount >= 2)
                            <div class="relative w-full m-auto py-8 border-b border-neutral-200">
                                <div class="flex gap-8 items-center">
                                    {{-- First Media Item --}}
                                    <div class="w-1/5 flex-shrink-0">
                                        <div class="relative pb-[133.33%]"> {{-- 4/3 = 133.33% --}}
                                            @if(isset($event->images[0]))
                                                <picture class="absolute inset-0">
                                                    <source 
                                                        srcset="{{ env('VITE_IMAGE_URL') . str_replace('.jpg', '.webp', $event->images[0]->large_image_path) }}"
                                                        type="image/webp"
                                                    >
                                                    <img 
                                                        src="{{ env('VITE_IMAGE_URL') . $event->images[0]->large_image_path }}"
                                                        alt="{{ $event->name }}"
                                                        class="w-full h-full object-cover rounded-lg absolute inset-0"
                                                    >
                                                </picture>
                                            @elseif($event->video === 'gallery' && $event->videos && count($event->videos) > 0)
                                                <div class="w-full h-full rounded-lg overflow-hidden bg-neutral-200 flex items-center justify-center absolute inset-0">
                                                    <svg class="w-8 h-8 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    {{-- Title and Tag Line --}}
                                    <div class="flex-1 flex flex-col justify-center">
                                        <h1 class="text-4xl font-medium text-black leading-tight">{{ $event->name }}</h1>
                                        @if($event->tag_line)
                                            <p class="text-3xl mt-4 text-neutral-700 font-medium">{{ $event->tag_line }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="relative w-full m-auto pb-8 md:pb-0 mt-16 border-b border-neutral-200">
                                <div class="flex flex-col bg-white h-full justify-center">
                                    {{-- Event Title --}}
                                    <h1 class="text-6xl font-medium text-black leading-none">{{ $event->name }}</h1>

                                    {{-- Tag Line --}}
                                    @if($event->tag_line)
                                        <p class="text-3xl mt-4 text-neutral-700 font-medium">{{ $event->tag_line }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="">
                            @include('events.show.about')
                            <vue-show-calendar-mobile :event="{{ $event }}"></vue-show-calendar-mobile>
                            @include('events.show.details')
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rest of your content --}}
            <div class="relative w-full m-auto px-10 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                @if ($event->eventreviews && count($event->eventreviews) > 0)
                    @include('events.show.reviews')
                @endif
                <vue-show-map :event="{{ $event }}"></vue-show-map>
                @include('events.show.organizer')
            </div>

            <div class="w-full relative shrink-0 md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem]">
                <vue-show-purchase-mobile
                    :event="{{ $event }}"
                    :single-image="false"
                    :user="user"
                ></vue-show-purchase-mobile>
            </div>
        </div>
    </div>
</div>