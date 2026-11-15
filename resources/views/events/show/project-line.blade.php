{{-- Under a listing's facts: the show it is part of, and that show's other
     runs on now or coming up as a row of small cards that scrolls sideways. --}}
@if($projectLine ?? null)
    <div class="pb-12 md:pb-16" data-project-line>
        <div class="flex items-start justify-between gap-6">
            <div class="min-w-0">
                <h2 class="text-3xl font-medium text-black">
                    Part of <a href="{{ $projectLine['project']->publicPath() }}" class="underline hover:text-neutral-600">{{ $projectLine['project']->name }}</a>
                </h2>
                {{-- One price, as on the show's page: dollars when a run sells in them, else the first. --}}
                @php($prices = $projectLine['from'] ?? [])
                @php($headline = $prices ? preg_replace('/\.00\b/', '', collect($prices)->first(fn ($p) => str_starts_with($p, 'From $')) ?? $prices[0]) : null)
                @php($summary = array_filter([$projectLine['range'] ?? null, $headline, count($prices) > 1 ? 'Prices vary by city' : null]))
                @if($summary)
                    <p class="text-xl text-neutral-600 mt-2">{{ implode(' · ', $summary) }}</p>
                @endif
            </div>

            {{-- Arrows only when there is more than fits; plain onclick (no Vue) scrolls the row a page at a time. --}}
            @if(($projectLine['runs'] ?? collect())->count() > 4)
                <div class="hidden md:flex gap-3 flex-shrink-0">
                    <button type="button" aria-label="Scroll left"
                        onclick="var r=this.closest('[data-project-line]').querySelector('[data-runs-row]');r.scrollBy({left:-r.clientWidth,behavior:'smooth'})"
                        class="w-12 h-12 rounded-full border border-neutral-300 bg-white flex items-center justify-center hover:shadow-custom-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" aria-label="Scroll right"
                        onclick="var r=this.closest('[data-project-line]').querySelector('[data-runs-row]');r.scrollBy({left:r.clientWidth,behavior:'smooth'})"
                        class="w-12 h-12 rounded-full border border-neutral-300 bg-white flex items-center justify-center hover:shadow-custom-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            @endif
        </div>

        @if(($projectLine['runs'] ?? collect())->isNotEmpty())
            {{-- Four across on a computer, a little over two on a phone; swipe or arrows for the rest. --}}
            <div data-runs-row class="flex gap-6 mt-6 py-2 overflow-x-auto overflow-y-hidden overscroll-x-contain snap-x snap-mandatory scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach($projectLine['runs'] as $run)
                    <div class="snap-start flex-shrink-0 w-[42%] md:w-[calc((100%-4.5rem)/4)]">
                        @include('projects.partials.run-card', ['small' => true, 'noDates' => true])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
