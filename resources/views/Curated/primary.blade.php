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
    
    // Get URL security attributes
    $urlAttrs = get_url_security_attributes($url);

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
    
    // If no element or no content, don't render the dock
    $shouldRender = $element && ($imagePath || $element->name);
@endphp

@if($shouldRender)
    <div class="px-10 lg-air:px-16 2xl-air:px-32">
        <div class="flex flex-col md:flex-row min-h-[70vh]">
            <!-- Text Content - Left Half -->
            <div class="w-full md:w-1/2 flex flex-col justify-center py-16 md:py-24 md:pr-16">
                @if($name)
                    <h2 class="text-5xl text-white mb-8 text-left">
                        {{ $name }}
                    </h2>
                @endif
                
                @if($element && isset($element->blurb) && $element->blurb)
                    <div class="text-lg md:text-xl text-white mb-8 leading-relaxed [&_*]:text-white [&_p]:text-white [&_p]:text-xl [&_h1]:text-white [&_h2]:text-white [&_h3]:text-white [&_h4]:text-white [&_h5]:text-white [&_h6]:text-white [&_span]:text-white [&_div]:text-white">
                        {!! $element->blurb !!}
                    </div>
                @endif
                
                <div class="text-left">
                    <a 
                        href="{{ $url }}"
                        @if($urlAttrs['target']) target="{{ $urlAttrs['target'] }}" @endif
                        @if($urlAttrs['rel']) rel="{{ $urlAttrs['rel'] }}" @endif
                        class="bg-transparent text-white border-2 border-[#f7653b] px-8 py-4 text-lg rounded-full font-semibold transition-colors duration-300 hover:bg-[#f7653b] uppercase">
                        @if($element && isset($element->button_text) && $element->button_text)
                            {{ $element->button_text }}
                        @else
                            Explore Now
                        @endif
                    </a>
                </div>
            </div>
            
            <!-- Image - Right Half -->
            <div class="w-full md:w-1/2 relative">
                @if($imagePath)
                    <div class="min-h-[250px] md:min-h-[600px] relative overflow-hidden">
                        <picture class="absolute inset-0 w-full h-full">
                            <source 
                                type="image/webp" 
                                srcset="{{ config('app.image_url') }}{{ str_replace(['.jpg', '.jpeg', '.png'], '.webp', $imagePath) }}">
                            <img 
                                src="{{ config('app.image_url') }}{{ $imagePath }}"
                                alt="{{ $getElementName($element) }}"
                                class="w-full h-full object-cover" 
                                loading="lazy" />
                        </picture>
                    </div>
                @else
                    <!-- Fallback placeholder if no image -->
                    <div class="h-64 md:h-full md:min-h-[70vh] bg-gray-800 flex items-center justify-center">
                        <div class="text-gray-400 text-6xl">
                            {{ $getElementName($element) ? substr($getElementName($element), 0, 1) : '?' }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
