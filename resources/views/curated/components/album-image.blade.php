@php
    $hasFeatured = null;
    $cardImages = [];
    $imageUrl = env('VITE_IMAGE_URL', 'https://ei-prod.sfo3.digitaloceanspaces.com/public/');

    // Handle featured image
    if (isset($element->featured_event_image)) {
        $hasFeatured = $element->featured_event_image->largeImagePath ?? null;
    }

    // Handle card images from limited_cards
    if (isset($element->limited_cards) && !empty($element->limited_cards)) {
        $cardImages = collect($element->limited_cards)
            ->map(function($card) {
                if (isset($card->event) && isset($card->event->thumbImagePath)) {
                    return $card->event->thumbImagePath;
                }
                return $card->thumbImagePath ?? null;
            })
            ->filter()
            ->values()
            ->toArray();
    }
@endphp

<div class="relative overflow-hidden grid rounded-xl {{ !$hasFeatured ? 'grid-image' : '' }}">
    @if($hasFeatured)
        <div class="w-full relative pt-[100%]">
            <picture>
                <source type="image/webp" srcset="{{ $imageUrl }}{{ $hasFeatured }}">
                <img class="h-full w-full absolute object-cover align-bottom inset-0"
                     loading="lazy"
                     src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $hasFeatured) }}"
                     alt="{{ $element->name }}">
            </picture>
        </div>
    @else
        <div class="w-full relative pt-[100%]">
            @if(!empty($cardImages[2]))
                <picture>
                    <source type="image/webp" srcset="{{ $imageUrl }}{{ $cardImages[2] }}">
                    <img class="h-full w-full absolute object-cover align-bottom inset-0 ml-0 rounded-xl"
                         style="object-fit:cover"
                         loading="lazy"
                         src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $cardImages[2]) }}"
                         alt="{{ $element->name }}">
                </picture>
            @endif

            @if(!empty($cardImages[1]))
                <picture>
                    <source type="image/webp" srcset="{{ $imageUrl }}{{ $cardImages[1] }}">
                    <img class="h-full w-full absolute object-cover align-bottom inset-0 rounded-xl border-2 border-white
                              {{ !empty($cardImages[2]) ? 'ml-[-15%]' : '' }}"
                         style="object-fit:cover"
                         loading="lazy"
                         src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $cardImages[1]) }}"
                         alt="{{ $element->name }}">
                </picture>
            @endif

            @if(!empty($cardImages[0]))
                <picture>
                    <source type="image/webp" srcset="{{ $imageUrl }}{{ $cardImages[0] }}">
                    <img class="h-full w-full absolute object-cover align-bottom inset-0 rounded-xl border-2 border-white
                              {{ !empty($cardImages[2]) && !empty($cardImages[1]) ? 'ml-[-30%]' : '' }}
                              {{ !empty($cardImages[1]) ? 'ml-[-15%]' : '' }}"
                         style="object-fit:cover"
                         loading="lazy"
                         src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $cardImages[0]) }}"
                         alt="{{ $element->name }}">
                </picture>
            @endif
        </div>
    @endif
</div>