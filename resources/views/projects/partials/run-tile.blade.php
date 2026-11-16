{{-- One run as a wide card, Airbnb's services list made quieter: the picture,
     the run's name exactly as typed (its city when it has no name of its own),
     where and when, and who presents it only when that is another company than
     the show's maker. A small arrow on the right. Never a street: see RunRow. --}}
@php($title = $run['name'] ?: $run['place'])
@php($showPresenter = $run['presenter'] && ! ($hidePresenter ?? false) && $run['presenter']->id !== ($makerId ?? null))
<a href="{{ $run['url'] }}" class="group flex items-center gap-[2rem] p-[0.8rem] pr-[1.6rem] rounded-[2rem] lg:rounded-[2.4rem] bg-white border border-[#ebebeb] hover:border-[#dddddd] hover:shadow-[0_6px_20px_rgba(0,0,0,0.08)] transition min-w-0">
    <div class="w-[6.4rem] lg:w-[8.4rem] aspect-[3/4] flex-shrink-0 rounded-[1.4rem] overflow-hidden bg-neutral-100">
        @if($run['large_image'] ?? $run['image'])
            <picture>
                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $run['large_image'] ?? $run['image'] }}">
                <img
                    loading="lazy"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]"
                    src="{{ config('app.image_url') }}{{ substr($run['large_image'] ?? $run['image'], 0, -4) }}jpg"
                    alt="{{ $run['name'] ?: $run['place'] }}"
                >
            </picture>
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-[1.6rem] leading-[2rem] font-semibold text-[#222222] break-words transform-none">{{ $title }}</p>
        <p class="mt-1 text-[1.4rem] leading-[1.8rem] text-[#6a6a6a] break-words transform-none">
            {{ implode(' · ', array_filter([$title !== $run['place'] ? $run['place'] : null, $run['dates']])) }}
        </p>
        @if($showPresenter)
            <p class="mt-2 text-[1.3rem] leading-[1.6rem] text-[#6a6a6a] break-words transform-none">Presented by <span class="font-medium text-[#222222]">{{ $run['presenter']->name }}</span></p>
        @endif
    </div>
    <svg width="16" height="16" class="w-[1.6rem] h-[1.6rem] flex-shrink-0 text-[#b0b0b0] group-hover:text-[#222222] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
</a>
