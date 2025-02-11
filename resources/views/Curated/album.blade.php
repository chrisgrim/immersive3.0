@php
    $elements = collect();
    $name = null;
    $count = $number === 'four' ? 4 : 3;

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
        
        if ($dock->type === 't') {
            $number = 'three'; // Force three images for type 't'
        }
    }

    $name = $dock->name ?? $name;
    $elements = $elements->take($count);

    // After setting $count
    $widthClass = "w-[85vw] md:w-[calc(" . ($count === 3 ? '33.333%' : '25%') . "-1.5rem)]";

    $getFirstLetter = function($name) {
        if (!$name) return '?';
        $firstChar = $name[0];
        if (preg_match('/[A-Za-z]/', $firstChar)) return strtoupper($firstChar);
        if (preg_match('/[0-9]/', $firstChar)) return $firstChar;
        return '?';
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
@endphp

<div class="whitespace-nowrap overflow-y-hidden overflow-x-auto m-auto w-full">
    <div>
        @if($name)
            <div class="mt-8 mb-0 md:mt-12">
                <div>
                    <h2 class="text-3.5xl text-black font-bold">{{ $name }}</h2>
                </div>
            </div>
        @endif

        <div style="scroll-snap-type: x mandatory;" 
             class="flex w-full scroll-p-7 overflow-auto mt-8 scroll-smooth gap-8">
            @foreach($elements as $element)
                <div class="relative snap-start snap-always {{ $widthClass }}">
                    <div class="flex w-full flex-col overflow-hidden relative">
                        <a href="{{ $getElementUrl($element) }}" 
                           class="block h-full absolute w-full rounded-2xl top-0 left-0 z-10">
                        </a>
                        
                        <div class="aspect-[4/3] w-full rounded-2xl overflow-hidden">
                            @php 
                                $imagePath = $getPostImage($element);
                                $firstLetter = $getFirstLetter($element->name);
                            @endphp
                            @if($imagePath)
                                <picture>
                                    <source 
                                        srcset="{{ config('app.image_url') }}{{ str_replace(['.jpg', '.jpeg', '.png'], '.webp', $imagePath) }}"
                                        type="image/webp"
                                    >
                                    <img src="{{ config('app.image_url') }}{{ $imagePath }}"
                                         alt="{{ $element->name }}"
                                         class="w-full h-full object-cover" />
                                </picture>
                            @else
                                <div class="w-full h-full flex items-center justify-center" 
                                     style="background-color: #c69669">
                                    <span class="text-6xl font-bold text-white">
                                        {{ $firstLetter }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="mb-8 mt-2">
                            <div class="mt-4 font-medium whitespace-normal">
                                <p class="text-4xl leading-tight text-black">{{ $element->name }}</p>
                            </div>
                            <div class="mt-2 text-xl text-gray-500">
                                {{ date('F j, Y', strtotime($element->created_at)) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
