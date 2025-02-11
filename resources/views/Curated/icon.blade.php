@php
    $elements = collect();
    $name = null;

    $getPostImage = function($post) {
        // Most common case first
        if ($post->thumbImagePath) {
            return $post->thumbImagePath;
        }

        // Featured event image check
        if ($post->featuredEventImage) {
            return is_string($post->featuredEventImage) 
                ? $post->featuredEventImage 
                : ($post->featuredEventImage->thumbImagePath ?? $post->featuredEventImage->largeImagePath ?? null);
        }

        // Check cards if no featured image
        if (isset($post->cards)) {
            // Look for event card with image
            $eventCard = $post->cards->firstWhere('type', 'e');
            if ($eventCard && isset($eventCard->event) && isset($eventCard->event->thumbImagePath)) {
                return $eventCard->event->thumbImagePath;
            }

            // Look for image card
            $imageCard = $post->cards->firstWhere('type', 'i');
            if ($imageCard && isset($imageCard->thumbImagePath)) {
                return $imageCard->thumbImagePath;
            }
        }

        // Fallback to images collection
        return !empty($post->images) ? $post->images->first()?->path : null;
    };

    $getElementUrl = function($element) use ($dock) {
        if (count($dock->shelves)) {
            return '/communities/' . $element->community->slug . '/' . $element->slug;
        }
        if (count($dock->communities)) {
            return '/communities/' . $element->slug;
        }
        return '#';
    };

    $mapPost = function($post) {
        return (object)[
            'id' => $post->id,
            'name' => $post->name,
            'slug' => $post->slug,
            'event_id' => $post->event_id,
            'featuredEventImage' => $post->featuredEventImage,
            'limitedCards' => $post->limitedCards ?? collect(),
            'community' => $post->community,
            'created_at' => $post->created_at,
            'cards' => $post->cards ?? collect(),
            'images' => $post->images ?? collect(),
            'thumbImagePath' => $post->thumbImagePath ?? null
        ];
    };

    if (count($dock->shelves)) {
        $elements = collect($dock->shelves[0]->publishedPosts)->map($mapPost);
        $name = $dock->shelves[0]->name;
    } elseif (count($dock->communities)) {
        $elements = $dock->communities;
    } elseif (count($dock->posts)) {
        $elements = $dock->posts;
    }

    $name = $dock->name ?? $name;
@endphp

<div class="mt-8 mb-0 md:mt-16 md:mb-24">
    @if($name)
        <div>
            <h2 class="text-3.5xl text-black font-bold">{{ $name }}</h2>
        </div>
    @endif

    <div class="whitespace-nowrap overflow-y-hidden overflow-x-auto m-auto w-full">
        <div style="grid-template-columns: 1fr 1fr;" 
             class="w-full whitespace-nowrap overflow-visible my-8 grid md:flex gap-8">
            @foreach($elements as $element)
                <div class="w-10/12 relative inline-block md:w-3/12">
                    <div class="w-[70vw] items-center relative flex md:w-full">
                        <a href="{{ $getElementUrl($element) }}" 
                           class="block h-full absolute left-0 top-0 w-full rounded-4 z-10">
                        </a>
                        
                        @include('Curated.Components.grid-image', [
                            'element' => $element,
                            'community' => $element->community ?? null,
                            'getPostImage' => $getPostImage
                        ])

                        <div class="flex overflow-x-hidden ml-8 flex-wrap">
                            <div>
                                <p class="truncate font-medium text-2xl">{{ $element->name }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
