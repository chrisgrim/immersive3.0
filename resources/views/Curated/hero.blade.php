@php
    $elements = collect();
    $name = null;
    $imageUrl = "https://ei-prod.sfo3.digitaloceanspaces.com/public/";

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

    $getFirstLetter = function($name) {
        if (!$name) return '?';
        $firstChar = $name[0];
        if (preg_match('/[A-Za-z]/', $firstChar)) return strtoupper($firstChar);
        if (preg_match('/[0-9]/', $firstChar)) return $firstChar;
        return '?';
    };

    $getElementUrl = function($element) use ($dock) {
        if (count($dock->shelves)) {
            return '/communities/' . $element->community->slug . '/posts/' . $element->slug;
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
    $cardWidth = 'width: ' . (Browser::isMobile() ? '85' : '27') . 'vw';
@endphp

<div class="md:mb-24 pb-16 bg-slate-100">
    <div class="justify-between items-center h-40 flex px-8 lg-air:px-16 2xl-air:px-32">
        @if($name)
            <div class="">
                <div>
                    <h2 class="text-5xl text-black font-bold">{{ $name }}</h2>
                </div>
            </div>
        @endif
        
        <div class="inline-flex items-end gap-2 invisible md:visible">
            <button 
                aria-label="Scroll Left"
                class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                onclick="window.scrollDockLeft()">
                <svg class="w-2/4 h-full m-auto">
                    <use href="/storage/website-files/icons.svg#ri-arrow-left-s-line" />
                </svg>
            </button>
            <button 
                aria-label="Scroll Right"
                class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                onclick="window.scrollDockRight()">
                <svg class="w-2/4 h-full m-auto">
                    <use href="/storage/website-files/icons.svg#ri-arrow-right-s-line" />
                </svg>
            </button>
        </div>
    </div>

    <div class="overflow-y-hidden overflow-x-auto whitespace-nowrap scrollbar-hide">
        <div id="dock-scroll-container" 
             class="overflow-x-auto flex scroll-p-10 lg:scroll-p-32 scrollbar-hide" 
             style="scroll-snap-type: x mandatory;">
            @foreach($elements as $element)
                <div class="ml-10 first:ml-0 snap-start snap-always first:pl-10 last:pr-10 lg-air:first:pl-16 lg-air:last:pr-16 2xl-air:first:pl-32 2xl-air:last:pr-32">
                    <a href="{{ $getElementUrl($element) }}" 
                       class="block w-full pb-16">
                        <div class="rounded-2xl overflow-hidden h-full border border-gray-300 w-[75vw] md:w-[25vw]">
                            <div class="w-full aspect-square">
                                @php
                                    $imagePath = $getPostImage($element);
                                    // Get first element's image for preload
                                    $isFirstElement = $loop->first;
                                @endphp
                                @if($imagePath)
                                    @if($isFirstElement)
                                        {{-- Preload first image in head --}}
                                        @push('head')
                                        <link 
                                            rel="preload" 
                                            as="image" 
                                            href="{{ $imageUrl }}{{ $imagePath }}"
                                            type="image/webp"
                                            fetchpriority="high">
                                        @endpush
                                    @endif
                                    <picture>
                                        <source 
                                            type="image/webp" 
                                            srcset="{{ $imageUrl }}{{ $imagePath }}">
                                        <img 
                                            class="h-full w-full object-cover"
                                            loading="{{ $isFirstElement ? 'eager' : 'lazy' }}"
                                            fetchpriority="{{ $isFirstElement ? 'high' : 'auto' }}"
                                            decoding="async"
                                            src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $imagePath) }}"
                                            alt="{{ $element->name }}"
                                            width="400"
                                            height="400">
                                    </picture>
                                @else
                                    <div class="w-full h-full flex items-center justify-center" 
                                         style="background-color: #c69669">
                                        <span class="text-6xl font-bold text-white">
                                            {{ $getFirstLetter($element->name) }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="text-left bg-white p-8">
                                <h3 class="text-3.5xl font-medium text-black">{{ $element->name }}</h3>
                                
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>