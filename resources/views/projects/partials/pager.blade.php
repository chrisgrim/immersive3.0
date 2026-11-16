{{-- Airbnb's pager: round arrows either side of the page numbers, the current one filled. --}}
<nav aria-label="{{ $label ?? 'Past runs pages' }}" class="mt-10 flex items-center justify-center gap-2">
    @if($paginator->onFirstPage())
        <span class="w-14 h-14 rounded-full flex items-center justify-center text-neutral-300" aria-hidden="true">
            <svg width="15" height="15" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" aria-label="Previous page" class="w-14 h-14 rounded-full flex items-center justify-center text-[#222222] hover:bg-[#f7f7f7]">
            <svg width="15" height="15" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </a>
    @endif

    @foreach(range(1, $paginator->lastPage()) as $number)
        @if($number === $paginator->currentPage())
            <span aria-current="page" class="w-14 h-14 rounded-full flex items-center justify-center bg-[#222222] text-white text-[1.4rem] font-semibold">{{ $number }}</span>
        @elseif($number === 1 || $number === $paginator->lastPage() || abs($number - $paginator->currentPage()) <= 1)
            <a href="{{ $paginator->url($number) }}" class="w-14 h-14 rounded-full flex items-center justify-center text-[#222222] text-[1.4rem] hover:bg-[#f7f7f7]">{{ $number }}</a>
        @elseif(abs($number - $paginator->currentPage()) === 2)
            <span class="w-8 text-center text-[#222222]" aria-hidden="true">…</span>
        @endif
    @endforeach

    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" aria-label="Next page" class="w-14 h-14 rounded-full flex items-center justify-center text-[#222222] hover:bg-[#f7f7f7]">
            <svg width="15" height="15" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </a>
    @else
        <span class="w-14 h-14 rounded-full flex items-center justify-center text-neutral-300" aria-hidden="true">
            <svg width="15" height="15" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </span>
    @endif
</nav>
