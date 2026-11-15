{{-- One run as a wide card, the same as Airbnb's services list: picture on the
     left, name, where and when, then who presents it. Never a street: see RunRow. --}}
<a href="{{ $run['url'] }}" class="flex items-center gap-[2.4rem] pl-[0.8rem] pr-[2rem] py-[0.8rem] rounded-[2rem] lg:rounded-[2.4rem] bg-white shadow-[0_0_0_1px_rgba(0,0,0,0.02),0_8px_24px_rgba(0,0,0,0.1)] hover:shadow-[0_0_0_1px_rgba(0,0,0,0.04),0_8px_28px_rgba(0,0,0,0.16)] transition-shadow group min-w-0">
    <div class="w-[7.5rem] lg:w-[10.3rem] aspect-[3/4] flex-shrink-0 rounded-[1.6rem] overflow-hidden bg-neutral-100">
        @if($run['large_image'] ?? $run['image'])
            <picture>
                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $run['large_image'] ?? $run['image'] }}">
                <img
                    loading="lazy"
                    class="w-full h-full object-cover"
                    src="{{ config('app.image_url') }}{{ substr($run['large_image'] ?? $run['image'], 0, -4) }}jpg"
                    alt="{{ $run['name'] ?: $run['place'] }}"
                >
            </picture>
        @endif
    </div>
    <div class="min-w-0 py-4">
        <p class="text-[1.4rem] leading-[1.8rem] font-semibold text-[#222222] break-words">{{ $run['name'] ?: $run['place'] }}</p>
        <p class="mt-1 text-[1.4rem] leading-[1.8rem] text-[#6c6c6c] break-words">
            {{ implode(' · ', array_filter([$run['name'] ? $run['place'] : null, $run['dates']])) }}
        </p>
        @if($run['run_type'] || $run['festival'])
            <p class="mt-2 flex flex-wrap gap-2 text-[1.2rem] leading-[1.6rem] text-[#6c6c6c]">
                @if($run['run_type'])
                    <span class="px-3 py-1 rounded-full border border-neutral-300">{{ $run['run_type'] }}</span>
                @endif
                @if($run['festival'])
                    <span class="px-3 py-1 rounded-full border border-neutral-300">{{ $run['festival'] }}</span>
                @endif
            </p>
        @endif
        @if($run['presenter'] && ! ($hidePresenter ?? false))
            <p class="mt-3 text-[1.2rem] leading-[1.6rem] text-[#6c6c6c] break-words">Presented by <span class="text-[1.4rem] font-medium text-[#222222]">{{ $run['presenter']->name }}</span></p>
        @endif
    </div>
</a>
