@php
    $hasFeatured = null;
    $cardImages = [];
    $imageTotal = null;
    $imageUrl = env('VITE_IMAGE_URL', 'https://ei-prod.sfo3.digitaloceanspaces.com/public/');

    // Handle featured image with null checks
    if ($element->event_id && isset($element->featured_event_image) && $element->featured_event_image) {
        $hasFeatured = $element->featured_event_image->thumbImagePath ?? null;
    } else {
        $hasFeatured = $element->largeImagePath ?? null;
    }

    // Handle card images with null checks
    if (isset($element->limited_cards) && $element->limited_cards) {
        $cardImages = collect($element->limited_cards)
            ->map(function($e) {
                if ($e->event && !$e->thumbImagePath) {
                    return $e->event->thumbImagePath ?? null;
                }
                return $e->thumbImagePath ?? null;
            })
            ->filter()
            ->values()
            ->toArray();
    } elseif (isset($element->cards) && $element->cards) {
        $cardImages = collect($element->cards)
            ->map(function($e) {
                if ($e->event && !$e->thumbImagePath) {
                    return $e->event->thumbImagePath ?? null;
                }
                return $e->thumbImagePath ?? null;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    // Determine image total
    if (!empty($cardImages[2])) {
        $imageTotal = 'three';
    } elseif (!empty($cardImages[1])) {
        $imageTotal = 'two';
    } elseif (!empty($cardImages[0])) {
        $imageTotal = 'one';
    }
@endphp

<div class="relative overflow-hidden grid rounded-xl w-28 h-28 {{ !$hasFeatured ? 'grid-image' : '' }}">
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
        @if($imageTotal === 'three')
            <div class="third {{ $imageTotal }}">
                <picture>
                    <source type="image/webp" srcset="{{ $imageUrl }}{{ $cardImages[2] }}">
                    <img style="object-fit:cover"
                         loading="lazy"
                         src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $cardImages[2]) }}"
                         alt="{{ $element->name }}">
                </picture>
            </div>
        @endif

        @if($imageTotal === 'two' || $imageTotal === 'three')
            <div class="second {{ $imageTotal }}">
                <picture>
                    <source type="image/webp" srcset="{{ $imageUrl }}{{ $cardImages[1] }}">
                    <img style="object-fit:cover"
                         loading="lazy"
                         src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $cardImages[1]) }}"
                         alt="{{ $element->name }}">
                </picture>
            </div>
        @endif

        @if($imageTotal === 'one' || $imageTotal === 'two' || $imageTotal === 'three')
            <div class="first {{ $imageTotal }}">
                @if(!empty($cardImages[0]))
                    <picture>
                        <source type="image/webp" srcset="{{ $imageUrl }}{{ $cardImages[0] }}">
                        <img style="object-fit:cover"
                             loading="lazy"
                             src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $cardImages[0]) }}"
                             alt="{{ $element->name }}">
                    </picture>
                @endif
            </div>
        @endif
    @endif
</div> 