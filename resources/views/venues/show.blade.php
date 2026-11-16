@extends('layouts.master-container')

@php
    $url = url($venue->publicPath());
    $image = $venue->largeImagePath ? config('app.image_url').$venue->largeImagePath : null;
    $street = trim(implode(' ', array_filter([$venue->home !== $venue->street ? $venue->home : null, $venue->street])));
    $locality = implode(', ', array_filter([$venue->city, trim($venue->region.' '.$venue->postal_code)]));
    $description = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $venue->description)), 157);
@endphp

@section('meta')
    <title>{{ $venue->name }}</title>
    <link rel="canonical" href="{{ $url }}" />
    @if($description)
        <meta name="description" content="{{ $description }}" />
    @endif
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $venue->name }}" />
    <meta property="og:url" content="{{ $url }}" />
    <meta property="og:site_name" content="Everything Immersive" />
    @if($image)
        <meta property="og:image" content="{{ $image }}" />
    @endif
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Place",
        "name": @json($venue->name),
        "address": {
            "@type": "PostalAddress",
            "streetAddress": @json($street),
            "addressLocality": @json($venue->city),
            "addressRegion": @json($venue->region),
            "postalCode": @json($venue->postal_code),
            "addressCountry": @json($venue->country_long ?: $venue->country)
        },
        "url": @json($url)
    }
    </script>
@endsection

@push('after-laravel')
    <script>
        window.Laravel.page = { venue: {!! Js::from($venue->only(['name', 'latitude', 'longitude'])) !!}, claim: {!! Js::from($claim) !!}, amenities: {!! Js::from(\App\Support\Projects\VenueAmenities::clean($venue->amenities)) !!} };
    </script>
@endpush

{{-- Header stays put while the page scrolls, as on Airbnb. --}}
@push('head')
    <style>#header{position:sticky;top:0;z-index:50;background:#fff}</style>
@endpush

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited-search', ['scrollAway' => true])
    @endif
@endsection

@section('content')
    @if($venue->status !== \App\Models\Venue::APPROVED)
        {{-- A preview for its keeper and staff: the public cannot see it yet. --}}
        <div class="bg-[#f7f7f7] border-b border-[#ebebeb]">
            <div class="max-w-screen-xl mx-auto px-8 py-3 flex items-center justify-between gap-4">
                @can('update', $venue)
                    <p class="text-[1.4rem] text-[#222222] transform-none">{{ $venue->status === \App\Models\Venue::DRAFT ? 'A draft: only you can see this page. Finish adding it to send it to EI.' : 'Waiting for EI to approve it: only you can see this page for now.' }}</p>
                    <a href="{{ $venue->status === \App\Models\Venue::DRAFT ? route('venues.setup', $venue) : route('venues.edit', $venue) }}" class="text-[1.4rem] font-semibold underline whitespace-nowrap">{{ $venue->status === \App\Models\Venue::DRAFT ? 'Finish adding it' : 'Edit' }}</a>
                @else
                    {{-- Reached from Add a venue's "we already know a venue here". --}}
                    <p class="text-[1.4rem] text-[#222222] transform-none">In review: this venue is not public yet. If it is yours, you can still claim it below.</p>
                @endcan
            </div>
        </div>
    @endif
    {{-- Airbnb's experience page, flipped: the details on the left (405px), a 50px gap,
         and the photos on the right (759px square, 5px apart, 36px outer corners).
         No photos: the map takes their place, as rounded but shorter. --}}
    @php($photos = $venue->images()->orderBy('rank')->pluck('large_image_path')->filter()->values())
    @php($photos = $photos->isEmpty() && $venue->largeImagePath ? collect([$venue->largeImagePath]) : $photos->take(4))
    @php($hasMap = $venue->latitude && $venue->longitude)
    @php($showCount = $current->count() + $before->count())
    @php($tabBar = Browser::isMobile())
    @php($corner = ['rounded-tl-[3.6rem]', 'rounded-tr-[3.6rem]', 'rounded-bl-[3.6rem]', 'rounded-br-[3.6rem]'])

    {{-- Same width as an organizer's page: max-w-screen-xl, 32 padding, a 36rem left column, gap-16. --}}
    <div class="m-auto w-full px-[2.4rem] {{ $tabBar ? 'pb-[12rem]' : 'pb-[4.8rem]' }} md:px-12 lg:px-32 lg:max-w-screen-xl lg:pt-24">
        {{-- A grid on a computer: photos top right, the details pinned on the left beside both rows,
             and the map and shows straight under the photos (no gap when the left side is taller).
             On a phone it reads top to bottom: photos, details, then the rest. --}}
        <div class="flex flex-col lg:grid lg:grid-cols-[36rem_minmax(0,1fr)] lg:gap-x-16 lg:items-start">
            {{-- Right on a computer, the top on a phone: photos, or the map. --}}
            <div class="min-w-0 -mx-[2.4rem] lg:mx-0 lg:col-start-2 lg:row-start-1">
                @if($photos->isNotEmpty())
                    {{-- Phone: one photo at a time, swiped. --}}
                    <div class="lg:hidden flex overflow-x-auto overflow-y-hidden snap-x snap-mandatory [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach($photos as $photo)
                            <img src="{{ config('app.image_url') }}{{ $photo }}" alt="{{ $venue->name }}" class="snap-start flex-shrink-0 w-full h-[30rem] object-cover" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                        @endforeach
                    </div>
                    {{-- Computer: Airbnb's grid. 1 fills it, 2 side by side, 3 one tall and two stacked, 4 two by two. --}}
                    @php($n = $photos->count())
                    <div class="hidden lg:grid aspect-[3/2] w-full gap-[0.5rem] {{ $n === 1 ? 'grid-cols-1' : 'grid-cols-2' }} {{ $n >= 3 ? 'grid-rows-2' : '' }}">
                        @foreach($photos as $i => $photo)
                            @php($round = match (true) {
                                $n === 1 => 'rounded-[3.6rem]',
                                $n === 2 => $i === 0 ? 'rounded-l-[3.6rem] rounded-r-[0.6rem]' : 'rounded-r-[3.6rem] rounded-l-[0.6rem]',
                                $n === 3 => ['row-span-2 rounded-l-[3.6rem] rounded-r-[0.6rem]', 'rounded-[0.6rem] rounded-tr-[3.6rem]', 'rounded-[0.6rem] rounded-br-[3.6rem]'][$i],
                                default => 'rounded-[0.6rem] '.$corner[$i],
                            })
                            <div class="overflow-hidden bg-neutral-100 {{ $round }}">
                                <img src="{{ config('app.image_url') }}{{ $photo }}" alt="{{ $venue->name }}" class="w-full h-full object-cover" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                            </div>
                        @endforeach
                    </div>
                @elseif($hasMap)
                    <vue-venue-map :venue="pageData.venue" box-class="h-[30rem] lg:h-[38rem] lg:rounded-[3.6rem]"></vue-venue-map>
                @endif
            </div>

            {{-- Left: the details. Pinned on a computer; on a phone a white panel with
                 rounded top corners slides over the photos. --}}
            <div class="relative flow-root w-full lg:w-[36rem] flex-shrink-0 bg-white -mt-[3.2rem] rounded-t-[3.2rem] lg:mt-0 lg:rounded-none lg:col-start-1 lg:row-start-1 lg:row-span-2 lg:self-start lg:sticky lg:top-[calc(var(--nav-bar-height,8rem)+6rem)]">
                <div class="text-left pt-[2.4rem] lg:pt-[1rem]">
                    <h1 class="text-[2.6rem] leading-[3rem] lg:text-[3.2rem] lg:leading-[3.6rem] font-semibold text-[#222222] tracking-[-0.096rem] break-words">{{ $venue->name }}</h1>
                    {{-- The organizer page's round edit button: the team that keeps it, and staff. --}}
                    @can('update', $venue)
                        <a href="{{ route('venues.edit', $venue) }}" aria-label="Edit this venue" class="inline-flex mt-[1.6rem] rounded-full bg-gray-100 w-20 h-20 items-center justify-center hover:bg-gray-200">
                            <svg width="20" height="20" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </a>
                    @endcan
                    @if($venue->room)
                        <p class="text-[1.6rem] leading-[2.2rem] text-[#6c6c6c] mt-2 transform-none">{{ $venue->room }}</p>
                    @endif

                    {{-- The organizer page's profile card: the venue's picture and name, and its numbers. --}}
                    @php($cardImage = $photos->first())
                    <div class="flex flex-row shadow-custom-6 w-full p-8 py-12 rounded-3xl gap-8 mt-[2.4rem] text-left">
                        <div class="flex flex-col items-center w-2/3 min-w-0">
                            <div class="w-[11rem] aspect-square rounded-full overflow-hidden shadow-sm bg-gray-200 flex items-center justify-center flex-shrink-0">
                                @if($cardImage)
                                    <img src="{{ config('app.image_url') }}{{ $cardImage }}" alt="{{ $venue->name }}" class="w-full h-full object-cover">
                                @else
                                    <svg width="40" height="40" class="w-[4rem] h-[4rem] text-gray-400" xmlns="http://www.w3.org/2000/svg"><use xlink:href="/storage/website-files/icons.svg#ri-map-pin-line" /></svg>
                                @endif
                            </div>
                            @if($locality)
                                <p class="text-[1.4rem] leading-[2rem] text-[#222222] mt-6 text-center break-words w-full transform-none">{{ $locality }}</p>
                            @endif
                        </div>
                        <div class="flex-1 flex flex-col justify-center space-y-6">
                            <div>
                                <p class="text-[3.2rem] leading-none font-semibold text-gray-900 transform-none">{{ $showCount }}</p>
                                <p class="text-md font-bold text-gray-600 mt-1 transform-none">{{ $showCount === 1 ? 'Show run' : 'Show runs' }}</p>
                            </div>
                            @if($current->isNotEmpty())
                                <div class="w-20 h-px bg-gray-200"></div>
                                <div>
                                    <p class="text-[3.2rem] leading-none font-semibold text-gray-900 transform-none">{{ $current->count() }}</p>
                                    <p class="text-md font-bold text-gray-600 mt-1 transform-none">On now</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- The description, then the icon rows, as Airbnb lists host, place and length. --}}
                <div class="mt-[3.2rem] flex flex-col gap-[2.8rem]">
                    @if($venue->description)
                        @php($about = \App\Support\VueSafe::html(nl2br(e($venue->description), false)))
                        <div class="relative">
                            <input type="checkbox" id="venue-about-more" class="peer sr-only">
                            <p class="text-[1.6rem] leading-[2.2rem] text-[#6c6c6c] transform-none break-words line-clamp-4 peer-checked:hidden">{!! $about !!}</p>
                            <p class="text-[1.6rem] leading-[2.2rem] text-[#6c6c6c] transform-none break-words hidden peer-checked:block">{!! $about !!}</p>
                            @if(mb_strlen($venue->description) > 200)
                                <label for="venue-about-more" class="inline-block mt-3 text-[1.4rem] leading-[1.8rem] font-semibold text-[#222222] underline cursor-pointer peer-checked:hidden">Show more</label>
                                <label for="venue-about-more" class="hidden mt-3 text-[1.4rem] leading-[1.8rem] font-semibold text-[#222222] underline cursor-pointer peer-checked:inline-block">Show less</label>
                            @endif
                        </div>
                    @endif

                    @if($street || $locality)
                        <div class="flex items-center gap-[2.2rem]">
                            <div class="w-[3.6rem] h-[3.6rem] flex-shrink-0 rounded-full bg-[#f7f7f7] flex items-center justify-center">
                                <svg width="20" height="20" class="w-[2rem] h-[2rem] text-[#222222]" xmlns="http://www.w3.org/2000/svg"><use xlink:href="/storage/website-files/icons.svg#ri-map-pin-line" /></svg>
                            </div>
                            <div class="min-w-0 text-left">
                                <p class="text-[1.4rem] leading-[1.8rem] font-medium text-[#222222] transform-none break-words">{{ $street ?: $locality }}</p>
                                @if($street && $locality)
                                    <p class="text-[1.4rem] leading-[1.8rem] text-[#6c6c6c] transform-none break-words">{{ $locality }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($venue->directions)
                        <a href="#how-to-get-there" class="flex items-center gap-[2.2rem] group">
                            <div class="w-[3.6rem] h-[3.6rem] flex-shrink-0 rounded-full bg-[#f7f7f7] flex items-center justify-center">
                                <svg width="20" height="20" class="w-[2rem] h-[2rem] text-[#222222]" xmlns="http://www.w3.org/2000/svg"><use xlink:href="/storage/website-files/icons.svg#ri-book-open-line" /></svg>
                            </div>
                            <div class="min-w-0 text-left">
                                <p class="text-[1.4rem] leading-[1.8rem] font-medium text-[#222222] transform-none group-hover:underline">How to get there</p>
                                <p class="text-[1.4rem] leading-[1.8rem] text-[#6c6c6c] transform-none line-clamp-1">{{ $venue->directions }}</p>
                            </div>
                        </a>
                    @endif

                    {{-- Contact links, one row each, like the rows above. --}}
                    @foreach($venue->contactLinks() as [$icon, $label, $href])
                        <a href="{{ $href }}" @if(str_starts_with($href, 'http')) target="_blank" rel="nofollow noopener noreferrer" @endif class="flex items-center gap-[2.2rem] group">
                            <div class="w-[3.6rem] h-[3.6rem] flex-shrink-0 rounded-full bg-[#f7f7f7] flex items-center justify-center">
                                <svg width="20" height="20" class="w-[2rem] h-[2rem] text-[#222222]" xmlns="http://www.w3.org/2000/svg"><use xlink:href="/storage/website-files/icons.svg#{{ $icon }}" /></svg>
                            </div>
                            <p class="min-w-0 text-left text-[1.4rem] leading-[1.8rem] font-medium text-[#222222] transform-none truncate group-hover:underline">{{ $label }}</p>
                        </a>
                    @endforeach
                </div>

                {{-- Whoever added the venue keeps it until the real venue claims it. --}}
                @if($claim)
                    <vue-venue-claim :claim="pageData.claim"></vue-venue-claim>
                @endif
            </div>

            {{-- Below the photos, in the right-hand column on a computer. --}}
            <div class="min-w-0 mt-[4.8rem] lg:col-start-2 lg:row-start-2">
            {{-- What it has, from the tiles picked when it was added or edited. --}}
            @if(\App\Support\Projects\VenueAmenities::clean($venue->amenities))
                <section class="mb-[4.8rem]">
                    <h2 class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222] mb-[2.4rem]">What's there</h2>
                    <vue-venue-amenities :keys="pageData.amenities"></vue-venue-amenities>
                </section>
            @endif
            @if($photos->isNotEmpty() && $hasMap)
                <section>
                    <h2 class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222] mb-[2.4rem]">Where it is</h2>
                    <vue-venue-map :venue="pageData.venue" box-class="h-[32rem] rounded-[2.4rem]"></vue-venue-map>
                </section>
            @endif

            @if($venue->directions)
                <section id="how-to-get-there" class="{{ $photos->isNotEmpty() && $hasMap ? 'mt-[4.8rem]' : '' }} scroll-mt-[12rem]">
                    <h2 class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222] mb-[1.6rem]">How to get there</h2>
                    {{-- Vue compiles this page and collapses newlines, so they are breaks. --}}
                    <p class="text-[1.6rem] leading-[2.2rem] text-[#222222] transform-none break-words">{!! \App\Support\VueSafe::html(nl2br(e($venue->directions), false)) !!}</p>
                </section>
            @endif

            {{-- One section per company: its logo and name, then its shows here (on now first,
                 then the ones before). Companies with something on now come first. --}}
            @php($byCompany = $current->concat($before)->filter(fn ($run) => $run['presenter'])->groupBy(fn ($run) => $run['presenter']->id)->sortBy(fn ($runs) => [$runs->contains(fn ($run) => $run['bucket'] !== 'past') ? 0 : 1, mb_strtolower($runs->first()['presenter']->name)]))
            @foreach($byCompany as $runs)
                @php($company = $runs->first()['presenter'])
                <section class="{{ $loop->first && ! ($photos->isNotEmpty() && $hasMap) && ! $venue->directions ? '' : 'mt-[4.8rem]' }}">
                    @php($companyLink = $company->status === 'p' ? $company->publicPath() : null)
                    <{{ $companyLink ? 'a' : 'div' }} @if($companyLink) href="{{ $companyLink }}" @endif class="flex items-center gap-[1.2rem] mb-[2.4rem] group w-fit">
                        <span class="w-[4.8rem] h-[4.8rem] rounded-full overflow-hidden bg-gray-200 flex-shrink-0 flex items-center justify-center">
                            @if($company->thumbImagePath)
                                <img src="{{ config('app.image_url') }}{{ $company->thumbImagePath }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-[1.8rem] font-bold text-gray-500">{{ Str::upper(Str::substr($company->name, 0, 1)) }}</span>
                            @endif
                        </span>
                        <span class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222] {{ $companyLink ? 'group-hover:underline' : '' }}">{{ $company->name }}</span>
                    </{{ $companyLink ? 'a' : 'div' }}>
                    {{-- The event page's small cards: two across on a phone, four on a computer. --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 gap-y-10">
                        @foreach($runs as $run)
                            @include('projects.partials.run-card', ['small' => true, 'noDates' => true])
                        @endforeach
                    </div>
                </section>
            @endforeach
            </div>
        </div>
    </div>
@endsection

@section('footer')
    @include('footer.footer-limited')
@endsection
