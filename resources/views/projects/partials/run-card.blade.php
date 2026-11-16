{{-- One run as a card, the same look as a search result: picture, place, name,
     dates, presenter. Never a street: see RunRow. Pass $small for the tighter
     version under a listing's facts. --}}
@php($small = $small ?? false)
<a href="{{ $run['url'] }}" class="relative flex flex-col group w-full min-w-0">
    <div class="relative overflow-hidden rounded-2xl bg-gray-100 transition-transform duration-200 ease-in-out group-hover:scale-[1.02]">
        <div class="pb-[133.33%]"></div>
        @if($run['large_image'] ?? $run['image'])
            <picture class="absolute inset-0">
                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $run['large_image'] ?? $run['image'] }}">
                <img
                    loading="lazy"
                    class="h-full w-full object-cover"
                    src="{{ config('app.image_url') }}{{ substr($run['large_image'] ?? $run['image'], 0, -4) }}jpg"
                    alt="{{ $run['name'] ?: $run['place'] }}"
                >
            </picture>
        @endif
    </div>

    <p class="{{ $small ? 'mt-4' : 'mt-6' }} uppercase text-md font-medium break-words">{{ $run['place'] }}</p>
    @if($run['name'])
        <h3 class="mt-2 {{ $small ? 'text-2xl' : 'text-2.5xl sm:text-3xl' }} text-black font-medium">
            <span class="line-clamp-2 break-words">{{ $run['name'] }}</span>
        </h3>
    @endif
    @unless($noDates ?? false)
        <p class="mt-2 {{ $small ? 'text-lg' : 'text-xl' }} text-neutral-600">{{ $run['dates'] }}</p>
    @endunless
    @if($run['presenter'] && ! $small)
        <p class="{{ $small ? 'text-lg' : 'text-xl' }} text-neutral-500 break-words">Presented by {{ $run['presenter']->name }}</p>
    @endif
</a>
