@props([
    'dock'
])

@php
    $element = null;
    $name = null;
    $url = '#';
    $imageUrl = env('VITE_IMAGE_URL', 'https://ei-prod.sfo3.digitaloceanspaces.com/public/');

    // Define helper function for element name
    $getElementName = function($element) {
        if (!$element) return '';
        // Handle cards - fallback to event name if card name is empty
        if (isset($element->type) && in_array($element->type, ['i', 'e', 't', 'v'])) {
            return $element->name ?: ($element->event->name ?? '');
        }
        // Handle posts and other elements
        return $element->name ?? '';
    };

    // Determine element and name based on dock type
    if (count($dock->shelves)) {
        $element = $dock->shelves[0]->publishedPosts[0] ?? null;
        $name = $dock->shelves[0]->name ?? null;
        if ($element) {
            $url = "/communities/{$element->community->slug}/posts/{$element->slug}";
        }
    } elseif (count($dock->posts)) {
        // For posts, use the actual post itself (not its cards)
        $element = $dock->posts[0] ?? null;
        if ($element) {
            $url = "/communities/{$element->community->slug}/posts/{$element->slug}";
        }
    } elseif (count($dock->cards)) {
        // Find the first card with displayable content (name or image)
        $element = null;
        foreach ($dock->cards as $card) {
            // Basic checks for displayable content
            $hasName = !empty($card->name) || (!empty($card->event) && !empty($card->event->name));
            $hasImage = (!empty($card->images) && $card->images->count() > 0) || 
                       (!empty($card->event) && (!empty($card->event->largeImagePath) || !empty($card->event->thumbImagePath)));
            
            if ($hasImage || $hasName) {
                $element = $card;
                break;
            }
        }
        if ($element) {
            // Handle card URL logic
            if ($element->url) {
                $url = $element->url;
            } elseif ($element->event && $element->event->slug) {
                $url = "/events/{$element->event->slug}";
            } elseif ($element->post && $element->post->slug && $element->post->community) {
                $url = "/communities/{$element->post->community->slug}/posts/{$element->post->slug}";
            }
        }
    } elseif (count($dock->communities)) {
        $element = $dock->communities[0] ?? null;
        if ($element) {
            $url = "/communities/{$element->slug}";
        }
    }

    // Override name if dock name exists
    $name = $dock->name ?? $name;

    // Image handling function
    $getElementImage = function($element) {
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
        
        // This is a post - use existing logic
        // Most common case first
        if (isset($element->largeImagePath) && $element->largeImagePath) {
            return $element->largeImagePath;
        }

        // Featured event image check
        if (isset($element->featuredEventImage) && $element->featuredEventImage) {
            return is_string($element->featuredEventImage) 
                ? $element->featuredEventImage 
                : ($element->featuredEventImage->largeImagePath ?? $element->featuredEventImage->thumbImagePath ?? null);
        }

        // Check cards if no featured image
        if (isset($element->cards)) {
            // Look for event card with image
            $eventCard = $element->cards->firstWhere('type', 'e');
            if ($eventCard && isset($eventCard->event) && isset($eventCard->event->largeImagePath)) {
                return $eventCard->event->largeImagePath;
            }

            // Look for image card
            $imageCard = $element->cards->firstWhere('type', 'i');
            if ($imageCard && !empty($imageCard->images) && $imageCard->images->first()) {
                return $imageCard->images->first()->large_image_path ?? $imageCard->images->first()->thumb_image_path;
            }
        }

        // Fallback to images collection
        return !empty($element->images) ? ($element->images->first()?->large_image_path ?? $element->images->first()?->thumb_image_path) : null;
    };

    $imagePath = $element ? $getElementImage($element) : null;
    
    // Get URL security attributes
    $urlAttrs = get_url_security_attributes($url);
    
    // If no element or no content, don't render the dock
    $shouldRender = $element && ($imagePath || $element->name);
@endphp

@if($shouldRender)
<div class="my-8 md:mt-16 md:mb-24 px-10 lg-air:px-16 2xl-air:px-32 py-16 lg-air:py-20 2xl-air:py-24 border-y border-slate-200">
    <div class="w-full relative block overflow-hidden mb-8 rounded-xl md:flex flex-col md:flex-row">
        @if($element && $imagePath)
            <a 
                href="{{ $url }}"
                @if($urlAttrs['target']) target="{{ $urlAttrs['target'] }}" @endif
                @if($urlAttrs['rel']) rel="{{ $urlAttrs['rel'] }}" @endif
                class="rounded-2xl overflow-hidden relative inline-block bg-slate-400 md:w-3/5 mb-8 md:mb-0 order-first md:order-last">
                <div class="aspect-video">
                    <picture class="w-full h-full">
                        <source 
                            type="image/webp" 
                            srcset="{{ $imageUrl }}{{ $imagePath }}">
                        <img 
                            loading="lazy"
                            class="object-cover w-full h-full"
                            src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $imagePath) }}"
                            alt="{{ $getElementName($element) }}">
                    </picture>
                </div>
            </a>
        @endif
        
        <div class="flex items-center justify-center md:justify-start md:w-2/5 mt-8 md:mt-0">
            <div class="w-full md:w-4/5">
                <div>
                    @if($name)
                    <p class="text-gray-500">{{ $name }}: </p>
                    @endif
                    <h2 class="text-6xl leading-[4.5rem] mt-8 font-medium text-black">{{ $getElementName($element) }}</h2>
                </div>
                <a 
                    href="{{ $url }}"
                    @if($urlAttrs['target']) target="{{ $urlAttrs['target'] }}" @endif
                    @if($urlAttrs['rel']) rel="{{ $urlAttrs['rel'] }}" @endif>
                    <button class="bg-[#ff385c] text-white border-none p-6 mt-8 rounded-2xl font-bold text-xl">
                        Check it out
                    </button>
                </a>
            </div>
        </div>
    </div>
</div>
@endif
