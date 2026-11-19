@extends('layouts.master-container')

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited')
    @endif
@endsection

@php
    // What venues-list.vue shows, with each row's addresses; a delete address only on a draft.
    $venueRows = collect($venues->items())->map(fn ($venue) => [
        'name' => $venue['name'],
        'slug' => $venue['slug'],
        'image' => $venue['image'],
        'owned' => (bool) $venue['owned'],
        'draft' => (bool) $venue['draft'],
        'pending' => (bool) $venue['pending'],
        'place' => $venue['place'],
        'open_url' => $venue['draft'] ? route('venues.setup', $venue['slug'], false) : route('venues.edit', $venue['slug'], false),
        'show_url' => route('venues.show', $venue['slug'], false),
        'delete_url' => $venue['draft'] ? route('venues.destroy', $venue['slug'], false) : null,
    ])->values();
@endphp
@push('after-laravel')
    <script>
        {{-- @json escapes < > & ' " so a venue's name cannot end this script. --}}
        window.Laravel.page = { venues: @json($venueRows) };
    </script>
@endpush

@section('content')
    {{-- Your venues: the same rows as the hosting events list (venues-list.vue). --}}
    <div class="flex justify-center md:justify-end">
        <div id="venues" class="px-8 md:px-16 2xl-air:px-32 transition-[padding] duration-200 w-full max-w-screen-5xl mt-8 md:mt-20 pb-40">
            <div class="flex items-center justify-between gap-6 mb-12">
                <h2 class="text-4xl md:text-5xl font-semibold text-[#222222]">{{ $venues->total() === 1 ? 'Your venue' : 'Your venues' }}</h2>
                @can('propose', \App\Models\Venue::class)
                    <a href="{{ route('hosting.venues.create') }}" class="inline-flex items-center gap-2 h-[4.4rem] px-[1.8rem] rounded-full bg-[#222222] text-white text-[1.4rem] font-semibold hover:bg-black" data-add-venue>
                        <svg width="18" height="18" class="w-[1.8rem] h-[1.8rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add a venue
                    </a>
                @endcan
            </div>
            @if(request()->boolean('added'))
                {{-- Just added through the steps: it waits for EI before it shows on the site.
                     A fixed message: nothing from the address bar is shown. --}}
                <p class="mb-10 px-6 py-4 rounded-[1.2rem] bg-[#f7f7f7] text-[1.5rem] text-[#222222] transform-none">Your venue was sent for review. It shows on the site once a moderator approves it.</p>
            @endif

            @if($venues->isEmpty())
                <p class="text-xl text-neutral-500 transform-none">No venues yet. Add one, or claim yours from its page.</p>
            @else
                {{-- The rows, the picking boxes, the side panel and the delete pop-up (venues-list.vue). --}}
                <vue-venues-list :venues="pageData.venues"></vue-venues-list>
                @if($venues->hasPages())
                    @include('projects.partials.pager', ['paginator' => $venues, 'label' => 'Venue pages'])
                @endif
            @endif
        </div>
    </div>
@endsection

@section('footer')
@endsection
