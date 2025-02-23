<div id="mainContent">
    @include('Events.Show.share-modal')
    <div id="bodyArea" class="show">
        <div class="show-content">
            @include('Events.Show.header-mobile')
            <div class="relative w-full m-auto px-10">
                <div class="">
                    <div class="flex-grow">
                        @if(count($event->images) >= 2)
                            <div class="relative w-full m-auto py-8 border-b border-neutral-200">
                                <div class="flex gap-8">
                                    {{-- First Image --}}
                                    <div class="w-1/5 flex-shrink-0 aspect-[3/4]">
                                        <picture>
                                            <source 
                                                srcset="{{ env('VITE_IMAGE_URL') . str_replace('.jpg', '.webp', $event->images[0]->large_image_path) }}"
                                                type="image/webp"
                                            >
                                            <img 
                                                src="{{ env('VITE_IMAGE_URL') . $event->images[0]->large_image_path }}"
                                                alt="{{ $event->name }}"
                                                class="w-full h-full object-cover rounded-lg"
                                            >
                                        </picture>
                                    </div>
                                    
                                    {{-- Title and Tag Line --}}
                                    <div class="flex-1 flex flex-col justify-center">
                                        <h1 class="text-4xl font-medium text-black leading-tight">{{ $event->name }}</h1>
                                        @if($event->tag_line)
                                            <p class="text-1xl mt-4 text-neutral-700 font-medium">{{ $event->tag_line }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="relative w-full m-auto mt-16 border-b border-neutral-200">
                                <div class="flex flex-col bg-white h-full justify-center">
                                    {{-- Event Title --}}
                                    <h1 class="text-5xl font-medium text-black leading-tight">{{ $event->name }}</h1>

                                    {{-- Tag Line --}}
                                    @if($event->tag_line)
                                        <p class="text-1xl mt-4 text-neutral-700 font-medium">{{ $event->tag_line }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="">
                            @include('Events.Show.about')
                            @include('Events.Show.details')
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rest of your content --}}
            <div class="relative w-full m-auto px-10 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                @if ($event->eventreviews && count($event->eventreviews) > 0)
                    @include('Events.Show.reviews')
                @endif
                <vue-show-map :event="{{ $event }}"></vue-show-map>
                @include('Events.Show.organizer')
            </div>
        </div>
    </div>
</div>