@php
    $elements = collect();
    $name = null;
    $imageUrl = "https://ei-prod.sfo3.digitaloceanspaces.com/public/";

    $getElementImage = function($element) {
        // Handle cards differently than posts
        if (isset($element->type) && in_array($element->type, ['i', 'e', 't', 'v'])) {
            // This is a card
            if (!empty($element->images) && $element->images->first()) {
                return $element->images->first()->thumb_image_path ?? $element->images->first()->large_image_path;
            }
            
            // Check if card has an event with image
            if ($element->event && $element->event->thumbImagePath) {
                return $element->event->thumbImagePath;
            }
            
            return null;
        }
        
        // This is a post - use existing logic
        // Most common case first
        if ($element->thumbImagePath) {
            return $element->thumbImagePath;
        }

        // Featured event image check
        if ($element->featuredEventImage) {
            return is_string($element->featuredEventImage) 
                ? $element->featuredEventImage 
                : ($element->featuredEventImage->thumbImagePath ?? $element->featuredEventImage->largeImagePath ?? null);
        }

        // Check cards if no featured image
        if (isset($element->cards)) {
            // Look for event card with image
            $eventCard = $element->cards->firstWhere('type', 'e');
            if ($eventCard && isset($eventCard->event) && isset($eventCard->event->thumbImagePath)) {
                return $eventCard->event->thumbImagePath;
            }

            // Look for image card
            $imageCard = $element->cards->firstWhere('type', 'i');
            if ($imageCard && isset($imageCard->thumbImagePath)) {
                return $imageCard->thumbImagePath;
            }
        }

        // Fallback to images collection
        return !empty($element->images) ? ($element->images->first()?->thumb_image_path ?? $element->images->first()?->large_image_path) : null;
    };

    $getFirstLetter = function($name) {
        if (!$name) return '?';
        $firstChar = $name[0];
        if (preg_match('/[A-Za-z]/', $firstChar)) return strtoupper($firstChar);
        if (preg_match('/[0-9]/', $firstChar)) return $firstChar;
        return '?';
    };

    $getElementUrl = function($element) use ($dock) {
        // Handle communities
        if (count($dock->communities)) {
            return '/communities/' . $element->slug;
        }
        
        // Handle cards
        if (isset($element->type) && in_array($element->type, ['i', 'e', 't', 'v'])) {
            // Card with custom URL
            if ($element->url) {
                return $element->url;
            }
            // Card linked to event
            if ($element->event && $element->event->slug) {
                return '/events/' . $element->event->slug;
            }
            // Card linked to post
            if ($element->post && $element->post->slug && $element->community) {
                return '/communities/' . $element->community->slug . '/posts/' . $element->post->slug;
            }
            return '#';
        }
        
        // Handle posts (from shelves or direct dock posts)
        if ($element->slug && $element->community) {
            return '/communities/' . $element->community->slug . '/posts/' . $element->slug;
        }
        
        return '#';
    };

    $mapElement = function($element) {
        // Handle different element types (posts vs cards)
        if (isset($element->type) && in_array($element->type, ['i', 'e', 't', 'v'])) {
            // This is a card - map to expected properties for grid-image component
            return (object)[
                'id' => $element->id,
                'name' => $element->name,
                'slug' => null, // Cards don't have slugs
                'url' => $element->url ?? null,
                'type' => $element->type,
                'post' => $element->post ?? null,
                'event' => $element->event ?? null,
                'community' => $element->post->community ?? null,
                'images' => $element->images ?? collect(),
                'button_text' => $element->button_text ?? null,
                // Add properties expected by grid-image component
                'event_id' => $element->event_id ?? null,
                'featured_event_image' => $element->event ?? null,
                'largeImagePath' => null, // Cards don't have direct image paths
                'thumbImagePath' => null, // Cards don't have direct image paths
                'limited_cards' => collect(), // Cards don't have nested cards
                'cards' => collect() // Cards don't have nested cards
            ];
        } else {
            // This is a post
            return (object)[
                'id' => $element->id,
                'name' => $element->name,
                'slug' => $element->slug,
                'event_id' => $element->event_id,
                'featuredEventImage' => $element->featuredEventImage,
                'limitedCards' => $element->limitedCards ?? collect(),
                'community' => $element->community,
                'created_at' => $element->created_at,
                'cards' => $element->cards ?? collect(),
                'images' => $element->images ?? collect(),
                'thumbImagePath' => $element->thumbImagePath ?? null
            ];
        }
    };

    // Define helper functions
    $getElementImage = function($element) {
        if (!$element) return null;
        
        // Handle cards differently than posts
        if (isset($element->type) && in_array($element->type, ['i', 'e', 't', 'v'])) {
            // This is a card - check card's images first
            if (!empty($element->images) && $element->images->first()) {
                return $element->images->first()->large_image_path ?? $element->images->first()->thumb_image_path;
            }
            
            // Check if card has an event with image
            if ($element->event) {
                if (isset($element->event->largeImagePath) && $element->event->largeImagePath) {
                    return $element->event->largeImagePath;
                }
                if (isset($element->event->thumbImagePath) && $element->event->thumbImagePath) {
                    return $element->event->thumbImagePath;
                }
            }
            
            return null;
        }
        
        // This is a post - existing logic
        if (isset($element->largeImagePath) && $element->largeImagePath) {
            return $element->largeImagePath;
        }

        if (isset($element->featuredEventImage) && $element->featuredEventImage) {
            return is_string($element->featuredEventImage) 
                ? $element->featuredEventImage 
                : ($element->featuredEventImage->largeImagePath ?? $element->featuredEventImage->thumbImagePath ?? null);
        }

        if (!empty($element->limitedCards)) {
            foreach ($element->limitedCards as $card) {
                if (!empty($card->images)) {
                    return $card->images->first()?->large_image_path ?? $card->images->first()?->thumb_image_path;
                }
            }
        }

        return !empty($element->images) ? ($element->images->first()?->large_image_path ?? $element->images->first()?->thumb_image_path) : null;
    };

    $getElementName = function($element) {
        // Handle cards - fallback to event name if card name is empty
        if (isset($element->type) && in_array($element->type, ['i', 'e', 't', 'v'])) {
            return $element->name ?: ($element->event->name ?? '');
        }
        // Handle posts and other elements
        return $element->name ?? '';
    };

    // Collect elements from different dock types
    if (count($dock->shelves)) {
        $elements = collect($dock->shelves[0]->publishedPosts)->map($mapElement);
        $name = $dock->shelves[0]->name;
    } elseif (count($dock->posts)) {
        // For posts, use the post's cards as elements instead of the post itself
        $allCards = collect();
        foreach ($dock->posts as $post) {
            if ($post->cards && count($post->cards)) {
                $allCards = $allCards->merge($post->cards->map($mapElement));
            }
        }
        // Filter out cards with no displayable content (no name and no image)
        $elements = $allCards->filter(function($card) use ($getElementImage, $getElementName) {
            $hasImage = $getElementImage($card);
            $hasName = $getElementName($card);
            return $hasImage || $hasName;
        });
    } elseif (count($dock->cards)) {
        $allCards = collect($dock->cards)->map($mapElement);
        // Filter out cards with no displayable content (no name and no image)
        $elements = $allCards->filter(function($card) use ($getElementImage, $getElementName) {
            $hasImage = $getElementImage($card);
            $hasName = $getElementName($card);
            return $hasImage || $hasName;
        });
    } elseif (count($dock->communities)) {
        $elements = $dock->communities;
    }

    $name = $dock->name ?? $name;
    $cardWidth = 'width: ' . (Browser::isMobile() ? '85' : '27') . 'vw';
    
    // If no elements to display, don't render the dock
    $shouldRender = $elements && count($elements) > 0;
@endphp

@if($shouldRender)
<div class="pb-16 md:pb-24 bg-slate-100">
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
                @php
                    $elementUrl = $getElementUrl($element);
                    $elementUrlAttrs = get_url_security_attributes($elementUrl);
                @endphp
                <div class="ml-10 first:ml-0 snap-start snap-always first:pl-10 last:pr-10 lg-air:first:pl-16 lg-air:last:pr-16 2xl-air:first:pl-32 2xl-air:last:pr-32">
                    <a 
                        href="{{ $elementUrl }}"
                        @if($elementUrlAttrs['target']) target="{{ $elementUrlAttrs['target'] }}" @endif
                        @if($elementUrlAttrs['rel']) rel="{{ $elementUrlAttrs['rel'] }}" @endif
                        class="block w-full pb-16">
                        <div class="rounded-2xl overflow-hidden h-full border border-gray-300 w-[75vw] md:w-[25vw]">
                            <div class="w-full aspect-square">
                                @php
                                    $imagePath = $getElementImage($element);
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
                                            href="{{ config('app.image_url') }}{{ $imagePath }}"
                                            type="image/webp"
                                            fetchpriority="high">
                                        @endpush
                                    @endif
                                    <picture>
                                        <source 
                                            type="image/webp" 
                                            srcset="{{ config('app.image_url') }}{{ $imagePath }}">
                                        <img 
                                            class="h-full w-full object-cover"
                                            loading="{{ $isFirstElement ? 'eager' : 'lazy' }}"
                                            fetchpriority="{{ $isFirstElement ? 'high' : 'auto' }}"
                                            decoding="async"
                                            src="{{ config('app.image_url') }}{{ Str::replaceLast('webp', 'jpg', $imagePath) }}"
                                            alt="{{ $getElementName($element) }}"
                                            width="400"
                                            height="400">
                                    </picture>
                                @else
                                    <div class="w-full h-full flex items-center justify-center" 
                                         style="background-color: #c69669">
                                        <span class="text-6xl font-bold text-white">
                                            {{ $getFirstLetter($getElementName($element)) }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="text-left bg-white p-8">
                                <h3 class="text-3.5xl font-medium text-black">{{ $getElementName($element) }}</h3>
                                
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif