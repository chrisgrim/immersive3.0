@props([
    'dock'
])

@php
    $element = null;
    $name = null;
    $url = '#';
    $imageUrl = env('VITE_IMAGE_URL', 'https://ei-prod.sfo3.digitaloceanspaces.com/public/');

    // Determine element and name based on dock type
    if (count($dock->shelves)) {
        $element = $dock->shelves[0]->publishedPosts[0] ?? null;
        $name = $dock->shelves[0]->name ?? null;
        if ($element) {
            $url = "/communities/{$element->community->slug}/posts/{$element->slug}";
        }
    } elseif (count($dock->communities)) {
        $element = $dock->communities[0] ?? null;
        if ($element) {
            $url = "/communities/{$element->slug}";
        }
    } elseif (count($dock->posts)) {
        $element = $dock->posts;
    }

    // Override name if dock name exists
    $name = $dock->name ?? $name;

    // Image handling function
    $getElementImage = function($element) {
        // Most common case first
        if ($element->largeImagePath) {
            return $element->largeImagePath;
        }

        // Featured event image check
        if ($element->featuredEventImage) {
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
            if ($imageCard && isset($imageCard->largeImagePath)) {
                return $imageCard->largeImagePath;
            }
        }

        // Fallback to images collection
        return !empty($element->images) ? $element->images->first()?->path : null;
    };

    $imagePath = $element ? $getElementImage($element) : null;
@endphp

<div class="my-8 md:mt-16 md:mb-24 px-10 lg-air:px-16 2xl-air:px-32 py-16 lg-air:py-20 2xl-air:py-24 border-y border-slate-200">
    <div class="w-full relative block overflow-hidden mb-8 rounded-xl md:flex flex-col md:flex-row">
        @if($element && $imagePath)
            <div class="rounded-2xl overflow-hidden relative inline-block bg-slate-400 md:w-3/5 mb-8 md:mb-0 order-first md:order-last">
                <div class="aspect-video">
                    <picture class="w-full h-full">
                        <source 
                            type="image/webp" 
                            srcset="{{ $imageUrl }}{{ $imagePath }}">
                        <img 
                            loading="lazy"
                            class="object-cover w-full h-full"
                            src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $imagePath) }}"
                            alt="{{ $element->name ?? '' }}">
                    </picture>
                </div>
            </div>
        @endif
        
        <div class="flex items-center justify-center md:justify-start md:w-2/5 mt-8 md:mt-0">
            <div class="w-full md:w-4/5">
                <div>
                    <p class="text-gray-500">{{ $name }}: </p>
                    <h2 class="text-6xl leading-[4.5rem] mt-8 font-medium text-black">{{ $element->name ?? '' }}</h2>
                </div>
                <a href="{{ $url }}">
                    <button class="bg-[#ff385c] text-white border-none p-6 mt-8 rounded-2xl font-bold text-xl">
                        Check it out
                    </button>
                </a>
            </div>
        </div>
    </div>
</div>
