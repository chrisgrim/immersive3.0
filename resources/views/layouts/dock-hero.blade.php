@php
    $elements = collect();
    $name = null;
    $imageUrl = "https://ei-prod.sfo3.digitaloceanspaces.com/public/";

    if (count($dock->shelves)) {
        $elements = $dock->shelves[0]->dockPosts;
        $name = $dock->shelves[0]->name;
    } elseif (count($dock->communities)) {
        $elements = $dock->communities;
    } elseif (count($dock->posts)) {
        $elements = $dock->posts;
    }

    $name = $dock->name ?? $name;
    $mobile = Browser::isMobile();
@endphp



<div class="my-8 md:mt-16 md:mb-24">
    <div class="justify-between flex px-8 my-8 md:px-12 lg:px-32 lg:my-12">
        @if($name)
            <h2>{{ $name }}</h2>
        @endif
        
        <div class="inline-block invisible md:visible">
            <button 
                aria-label="Scroll Left"
                class="rounded-full w-16 h-16 border border-gray-300 p-0" 
                onclick="window.scrollDockLeft()">
                <svg class="w-2/4 h-full m-auto">
                    <use href="/storage/website-files/icons.svg#ri-arrow-left-s-line" />
                </svg>
            </button>
            <button 
                aria-label="Scroll Right"
                class="rounded-full w-16 h-16 border border-gray-300 p-0" 
                onclick="window.scrollDockRight()">
                <svg class="w-2/4 h-full m-auto">
                    <use href="/storage/website-files/icons.svg#ri-arrow-right-s-line" />
                </svg>
            </button>
        </div>
    </div>

    <div class="h-[calc(125vw-10rem)] overflow-y-hidden overflow-x-auto whitespace-nowrap md:h-128">
        <div id="dock-scroll-container" 
             class="overflow-x-auto flex h-[calc(125vw-6.25rem)] custom-h-1 scroll-p-10 md:h-auto lg:scroll-p-32" 
             style="scroll-snap-type: x mandatory;">
            @foreach($elements as $element)
                <div class="ml-6 first:ml-0 snap-start snap-always md:min-w-[48rem] md:max-w-[68rem] lg:flex-[1_0_calc(50%-9rem)] first:pl-32 last:pr-32 flex-[1_0_calc(50%-1rem)] first:ml-0">
                    <a href="{{ count($dock->shelves) 
                        ? '/communities/' . $element->community->slug . '/' . $element->slug 
                        : (count($dock->communities) 
                            ? '/communities/' . $element->slug 
                            : '#') }}" 
                       class="relative block w-full">
                        <div class="relative h-[calc(125vw-10rem)] rounded-2xl overflow-hidden md:h-[32rem]">
                            <div class="absolute inset-[-1px]">
                                <picture>
                                    @php
                                        $imagePath = $element->event_id && $element->featuredEventImage 
                                            ? $element->featuredEventImage->thumbImagePath 
                                            : $element->largeImagePath;
                                    @endphp

                                    <source 
                                        type="image/webp" 
                                        srcset="{{ $imageUrl }}{{ $imagePath }}">
                                    <img 
                                        class="h-full w-full absolute object-cover align-bottom"
                                        loading="lazy" 
                                        src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $imagePath) }}"
                                        alt="{{ $element->name }}">
                                </picture>
                                <div class="absolute inset-0 bg-gradient-to-r from-black/60 to-transparent"></div>
                            </div>
                            
                            <div class="relative content-start flex h-full p-10 flex-col w-96">
                                <div class="flex-1 md:w-[30rem] lg:w-96">
                                    <div class="overflow-hidden text-ellipsis text-white text-lg">
                                        {{ $element->blurb }}
                                    </div>
                                    <div class="text-white mt-4 whitespace-normal text-5xl font-medium">
                                        {{ $element->name }}
                                    </div>
                                </div>
                                <button class="border-none rounded-2xl p-4 text-black bg-white w-40 font-bold text-xl">
                                    Show me
                                </button>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>