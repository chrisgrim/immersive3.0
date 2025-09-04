@php
    $elements = collect();
    $name = null;
    $count = $number === 'four' ? 4 : 3;

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
        if (!$element) return '';
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
    
    if ($dock->type === 't') {
        $number = 'three'; // Force three images for type 't'
    }

    $name = $dock->name ?? $name;
    $elements = $elements->take($count);
    
    // If no elements to display, don't render the dock
    $shouldRender = $elements && count($elements) > 0;

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
@endphp

@if($shouldRender)
<div class="whitespace-nowrap overflow-y-hidden overflow-x-auto m-auto w-full">
    <div>
        @if($name)
            <div class="mt-12 mb-0">
                <div>
                    <h2 class="text-3.5xl text-black font-bold">{{ $name }}</h2>
                </div>
            </div>
        @endif

        <div class="block md:flex w-full scroll-p-7 overflow-auto mt-8 scroll-smooth gap-8 md:whitespace-nowrap whitespace-normal" 
             style="scroll-snap-type: x mandatory;">
            @foreach($elements as $element)
                <div class="relative snap-start snap-always {{ $widthClass }} mb-8 md:mb-0">
                    <div class="flex md:block w-full gap-10 md:gap-0 overflow-hidden relative">
                        <a href="{{ $getElementUrl($element) }}" 
                           class="block h-full absolute w-full rounded-2xl top-0 left-0 z-10">
                        </a>
                        
                        <div class="w-1/2 md:w-full">
                            <div class="aspect-[16/9] w-full rounded-2xl overflow-hidden">
                                @php 
                                    $imagePath = $getElementImage($element);
                                    $elementName = $getElementName($element);
                                    $firstLetter = $getFirstLetter($elementName);
                                @endphp
                                @if($imagePath)
                                    <picture>
                                        <source 
                                            srcset="{{ config('app.image_url') }}{{ str_replace(['.jpg', '.jpeg', '.png'], '.webp', $imagePath) }}"
                                            type="image/webp"
                                        >
                                        <img src="{{ config('app.image_url') }}{{ $imagePath }}"
                                             alt="{{ $elementName }}"
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
                        </div>

                        <div class="w-1/2 md:w-full md:mb-8 md:mt-4">
                            <div class="font-medium whitespace-normal">
                                <p class="text-3xl md:text-4xl leading-tight text-black">{{ $elementName }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
