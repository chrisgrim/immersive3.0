@extends('layouts.master-container')

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited')
    @endif
@endsection

@section('content')
    {{-- Your venues: the same long rows as the hosting list. Each row opens the venue's edit page. --}}
    <div class="flex justify-center md:justify-end">
        <div id="venues" class="px-8 lg-air:px-16 2xl-air:px-32 w-full max-w-screen-5xl mt-8 md:mt-20 pb-40">
            <h2 class="text-4xl md:text-5xl font-semibold text-[#222222] mb-12">{{ $venues->total() === 1 ? 'Your venue' : 'Your venues' }}</h2>

            @if($venues->isEmpty())
                <p class="text-xl text-neutral-500 transform-none">No venues yet. A venue you add with an event, or claim, shows up here.</p>
            @else
                <div class="hidden md:grid gap-8 py-4 h-36 items-center md:grid-cols-[16rem_30%_auto_auto_auto]">
                    <h5 class="font-medium">Venues</h5>
                    <div></div>
                    <h5 class="font-medium">Status</h5>
                    <h5 class="font-medium text-center">City</h5>
                </div>
                @foreach($venues as $venue)
                    <a href="{{ route('venues.edit', $venue['slug']) }}" class="group relative grid gap-8 px-0 md:px-4 p-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[6rem_auto] md:grid-cols-[16rem_30%_auto_auto_auto]">
                        <div class="h-24 aspect-[3/2] overflow-hidden rounded-2xl bg-gray-300">
                            @if($venue['image'])
                                <img src="{{ config('app.image_url').$venue['image'] }}" alt="{{ $venue['name'] }}" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div>
                            <p class="text-3xl md:text-2xl font-medium text-black break-words hyphens-auto transform-none">{{ $venue['name'] }}</p>
                            @if($venue['owned'])
                                <p class="text-lg md:text-md leading-4 text-gray-500 transform-none">Yours</p>
                            @endif
                        </div>
                        <div class="hidden md:flex flex-row items-center">
                            <div class="w-4 h-4 rounded-full {{ $venue['pending'] ? 'bg-yellow-300' : 'bg-green-500' }}"></div>
                            <p class="text-lg font-medium text-gray-500 ml-4 transform-none">{{ $venue['pending'] ? 'Waiting for EI' : 'Live' }}</p>
                        </div>
                        <div class="hidden md:flex justify-center items-center">
                            @if($venue['place'])
                                <div class="bg-gray-100 px-4 py-2 rounded-full font-medium">{{ $venue['place'] }}</div>
                            @else
                                <div class="text-gray-400">-</div>
                            @endif
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="hidden md:block md:absolute right-6 top-1/2 -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                @endforeach
                @if($venues->hasPages())
                    @include('projects.partials.pager', ['paginator' => $venues, 'label' => 'Venue pages'])
                @endif
            @endif
        </div>
    </div>
@endsection

@section('footer')
@endsection
