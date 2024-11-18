@php
    $elements = collect();
    $name = null;
    $count = $number === 'four' ? 4 : 3;

    if ($dock->type === 'f' && count($dock->shelves)) {
        $elements = collect($dock->shelves[0]->publishedPosts)->map(function($post) {
            return (object)[
                'id' => $post->id,
                'name' => $post->name,
                'slug' => $post->slug,
                'event_id' => $post->event_id,
                'featured_event_image' => $post->featured_event_image,
                'limited_cards' => $post->limitedCards,
                'community' => $post->community
            ];
        });
        $name = $dock->shelves[0]->name;
    } elseif ($dock->type === 'h' && count($dock->shelves)) {
        $elements = collect($dock->shelves[0]->publishedPosts)->map(function($post) {
            return (object)[
                'id' => $post->id,
                'name' => $post->name,
                'slug' => $post->slug,
                'event_id' => $post->event_id,
                'featured_event_image' => $post->featured_event_image,
                'limited_cards' => $post->limitedCards,
                'community' => $post->community
            ];
        });
        $name = $dock->shelves[0]->name;
    } elseif ($dock->type === 't' && count($dock->shelves)) {
        $elements = collect($dock->shelves[0]->publishedPosts)->map(function($post) {
            return (object)[
                'id' => $post->id,
                'name' => $post->name,
                'slug' => $post->slug,
                'event_id' => $post->event_id,
                'featured_event_image' => $post->featured_event_image,
                'limited_cards' => $post->limitedCards,
                'community' => $post->community
            ];
        });
        $name = $dock->shelves[0]->name;
        $number = 'three'; // Force three images for type 't'
    }

    $name = $dock->name ?? $name;
    $elements = $elements->take($count);
@endphp

@if($name)
    <div class="mt-8 mb-0 md:mt-16 md:mb-24">
        <div>
            <h2>{{ $name }}</h2>
        </div>
    </div>
@endif

<div class="whitespace-nowrap overflow-y-hidden overflow-x-auto m-auto w-full">
    <div style="scroll-snap-type: x mandatory;" 
         class="flex w-full scroll-p-7 overflow-auto mt-8 scroll-smooth gap-8">
        @foreach($elements as $element)
            <div class="relative w-full flex flex-[1_0_calc(100%-6rem)] snap-start snap-always
                {{ $number === 'three' 
                    ? 'md:flex-[0_1_50%] md:w-6/12 lg:flex-[0_1_33.3333333333%] lg:w-4/12'
                    : 'md:flex-[0_1_33.3333333333%] md:w-4/12 lg:flex-[0_1_25%] lg:w-3/12' }}">
                <div class="flex w-full flex-col overflow-hidden relative">
                    <a href="{{ count($dock->shelves) 
                            ? '/communities/' . $element->community->slug . '/' . $element->slug 
                            : (count($dock->communities) 
                                ? '/communities/' . $element->slug 
                                : '#') }}" 
                       class="block h-full absolute w-full rounded-2xl top-0 left-0 z-10">
                    </a>
                    
                    @include('Curated.Components.album-image', [
                        'element' => $element,
                        'community' => $element->community
                    ])

                    <div class="mb-8 mt-2">
                        <div class="mt-4 overflow-hidden text-ellipsis max-h-16 font-medium text-xl">
                            <p>{{ $element->name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
