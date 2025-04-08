@extends('layouts.master-container')

@php
    // Calculate total media count - include videos if gallery mode is enabled
    $videoCount = ($event->video === 'gallery' && $event->videos && count($event->videos) > 0) ? count($event->videos) : 0;
    $totalMediaCount = count($event->images) + $videoCount;
@endphp

@section('meta')
    
    <title>{{$event->name}} {{$event->tag_line ? '- ' . \Illuminate\Support\Str::limit($event->tag_line, 80) : '- ' . \Illuminate\Support\Str::limit($event->description, 80)}} </title>
    <link rel="canonical" href="{{url()->current()}}">
    <meta name="description" content="{{$event->tag_line ? $event->tag_line : $event->description}}"/>
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="event" />
    <meta property="og:title" content="{{$event->name}}" />
    <meta property="og:description" content="{{$event->tag_line ? $event->tag_line : $event->description}}" />
    <meta property="og:url" content="{{url()->current()}}" />
    <meta property="og:site_name" content="{{config('app.name')}}" />
    <meta property="article:publisher" content="https://www.everythingimmersive.com" />
    <meta property="article:section" content="Events" />
    <meta property="article:published_time" content="{{$event->created_at}}" />
    <meta property="article:modified_time" content="{{$event->updated_at}}" />
    <meta property="og:updated_time" content="{{$event->updated_at}}" />
    @foreach($event->images as $image)
        <meta property="og:image" content="{{ env('VITE_IMAGE_URL') }}{{$image->large_image_path}}" />
        <meta property="og:image:secure_url" content="{{ env('VITE_IMAGE_URL') }}{{$image->large_image_path}}" />
        <meta property="og:image:width" content="1280" />
        <meta property="og:image:height" content="720" />
        <meta name="twitter:image" content="{{ env('VITE_IMAGE_URL') . $image->large_image_path }}" />
    @endforeach
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:description" content="{{$event->tag_line ? $event->tag_line : $event->description}}" />
    <meta name="twitter:title" content="{{$event->name}}" />
    <meta name="twitter:site" content="@everythingimmersive" />
    <meta name="twitter:creator" content="@everythingimmersive" />
    <script type="application/ld+json">{"@context":"https://schema.org","@type":"Event","name":"{{$event->name}}{{$event->tag_line ? '- ' . \Illuminate\Support\Str::limit($event->tag_line, 80) : '- ' . \Illuminate\Support\Str::limit($event->description, 80)}}","startDate":"{{ $event->shows->isEmpty() ? \Carbon\Carbon::parse($event->created_at)->toIso8601String() : \Carbon\Carbon::parse($event->shows[0]->date)->toIso8601String() }}","endDate":"{{ \Carbon\Carbon::parse($event->closingDate)->toIso8601String() }}","eventStatus":"https://schema.org/EventScheduled","image":[@foreach($event->images as $image) "{{ env('VITE_IMAGE_URL') }}{{ $image->large_image_path }}"@if(!$loop->last),@endif @endforeach],"description":"{{$event->tag_line ? $event->tag_line : $event->description}}","offers":{"@type":"Offer","url":"{{$event->ticketUrl ? $event->ticketUrl : ($event->websiteUrl ? $event->websiteUrl : Request::url())}}","price":"{{$event->priceranges[0]->price}}","priceCurrency":"USD","availability":"https://schema.org/InStock","validFrom":"{{$event->priceranges[0]->created_at}}"},"organizer":{"@type":"Organization","name":"{{$event->organizer->name}}","url":"{{$event->organizer->website ? $event->organizer->website : Request::root() .'/organizer/' . $event->organizer->slug}}"}@if($event->hasLocation),"eventAttendanceMode":"https://schema.org/OfflineEventAttendanceMode","location":{"@type":"Place","name":"{{$event->location->venue ? $event->location->venue : $event->name}}","address":{"@type":"PostalAddress","streetAddress":"{{$event->location->home . ' ' . $event->location->street}}","addressLocality":"{{$event->location->city}}","postalCode":"{{$event->location->postal_code}}","addressRegion":"{{$event->location->region}}","addressCountry":"{{$event->location->country}}"}}@else,"eventAttendanceMode":"https://schema.org/OnlineEventAttendanceMode","location":{"@type":"VirtualLocation","url":"{{$event->websiteUrl ? $event->websiteUrl : ($event->ticketUrl ? $event->ticketUrl : Request::url())}}"}}@endif}</script>

    @if (Browser::isMobile())
        @foreach($event->images as $image)
            <link rel="preload" as="image" type="image/webp" imagesrcset="{{ env('VITE_IMAGE_URL') . $image->thumb_image_path }}">
        @endforeach
    @else
        @foreach($event->images as $image)
            <link rel="preload" as="image" type="image/webp" imagesrcset="{{ env('VITE_IMAGE_URL') . $image->large_image_path }}">
        @endforeach
    @endif
    @vite(['resources/css/flatpickr.css'])

    @if (Browser::isMobile())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Photo Gallery Functions
                window.showPhotoGallery = function() {
                    document.body.style.overflow = 'hidden';
                    const galleryContainer = document.createElement('div');
                    galleryContainer.id = 'photoGallery';
                    galleryContainer.innerHTML = `@include('events.show.mobile-photo-gallery')`;
                    document.body.appendChild(galleryContainer);
                };

                window.closePhotoGallery = function() {
                    document.body.style.overflow = 'auto';
                    const gallery = document.getElementById('photoGallery');
                    if (gallery) gallery.remove();
                };

                // Share Modal Functions
                window.handleShare = function() {
                    document.getElementById('shareModal').classList.remove('hidden');
                };

                window.closeShareModal = function() {
                    document.getElementById('shareModal').classList.add('hidden');
                };

                window.toggleShareModal = function() {
                    const modal = document.getElementById('shareModal');
                    if (modal.classList.contains('hidden')) {
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    } else {
                        modal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                };

                window.copyLink = function() {
                    const tempInput = document.createElement('input');
                    tempInput.value = window.location.href;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    alert('Link copied to clipboard!');
                    closeShareModal();
                };
            });
        </script>
    @else
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Share Modal Functions
                window.toggleShareModal = function() {
                    const modal = document.getElementById('shareModal');
                    if (modal.classList.contains('hidden')) {
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    } else {
                        modal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                };

                // Photo Gallery Functions for Multiple Images
                @if($totalMediaCount > 1)
                    window.addEventListener('showAllPhotos', function(e) {
                        const headerContent = document.getElementById('headerContent');
                        headerContent.innerHTML = `@include('events.show.photo-gallery')`;
                        setTimeout(checkScrollPosition, 0);
                    });

                    window.closePhotoGallery = function() {
                        const headerContent = document.getElementById('headerContent');
                        headerContent.innerHTML = `@include('events.show.header-multiple')`;
                    };

                    window.checkScrollPosition = function() {
                        const container = document.getElementById('photo-scroll-container');
                        const leftArrow = document.getElementById('leftArrow');
                        const rightArrow = document.getElementById('rightArrow');

                        if (container) {
                            leftArrow.classList.toggle('hidden', container.scrollLeft <= 0);
                            const canScrollRight = container.scrollWidth > (container.scrollLeft + container.clientWidth + 50);
                            rightArrow.classList.toggle('hidden', !canScrollRight);
                        }
                    };

                    window.scrollPhotoLeft = function() {
                        const container = document.getElementById('photo-scroll-container');
                        if (container) {
                            container.scrollBy({
                                left: -container.offsetWidth,
                                behavior: 'smooth'
                            });
                        }
                    };

                    window.scrollPhotoRight = function() {
                        const container = document.getElementById('photo-scroll-container');
                        if (container) {
                            container.scrollBy({
                                left: container.offsetWidth,
                                behavior: 'smooth'
                            });
                        }
                    };

                    // Initialize scroll position checks
                    document.addEventListener('DOMContentLoaded', function() {
                        checkScrollPosition();
                        window.addEventListener('resize', checkScrollPosition);
                    });
                @endif
            });
        </script>
    @endif
@endsection


@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited-search')
    @endif
    
@endsection

@section('content')
    @if (Browser::isMobile())
        @include('events.show-mobile')
    @else
        <div id="mainContent">
            <div id="bodyArea" class="show">
                <div class="show-content">
                    @if($totalMediaCount === 0)
                        {{-- Title section spans full width for both layouts --}}
                        <div class="relative w-full m-auto px-10 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            <div class="flex flex-col justify-center bg-white">
                                <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        {{-- Event Title --}}
                                        <h1 class="text-5xl font-medium text-black leading-tight">{{ $event->name }}</h1>

                                        {{-- Tag Line --}}
                                        @if($event->tag_line)
                                            <p class="text-1xl mt-4 text-neutral-700 font-medium">{{ $event->tag_line }}</p>
                                        @endif
                                    </div>
                                    
                                    <div class="flex items-center gap-8 mt-4">
                                        <button 
                                            onclick="toggleShareModal()" 
                                            class="p-3 rounded-full border border-neutral-200 hover:bg-neutral-50 transition-colors"
                                        >
                                            <svg 
                                                xmlns="http://www.w3.org/2000/svg" 
                                                viewBox="0 0 24 24" 
                                                fill="none" 
                                                stroke="currentColor" 
                                                stroke-width="2" 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round"
                                                style="width: 20px; height: 20px;"
                                            >
                                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                                <polyline points="16 6 12 2 8 6"/>
                                                <line x1="12" y1="2" x2="12" y2="15"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- No images layout --}}
                        <div class="relative w-full m-auto px-10 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            <div class="md:flex md:gap-32">
                                <div class="flex-grow">
                                    <div>
                                        @include('events.show.header-old')
                                        @include('events.show.about')
                                        @include('events.show.details')
                                    </div>         
                                </div>
                                
                                <div class="w-full relative inline-block md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem]">
                                    <vue-show-purchase
                                        :event="{{ $event }}"
                                        :single-image="true"
                                        :user="user"
                                    ></vue-show-purchase>
                                </div>
                            </div>
                        </div>
                    @elseif($totalMediaCount === 1)
                        {{-- Single image layout --}}
                        
                        <div class="relative w-full m-auto px-10 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            {{-- Top section with title and image --}}
                            <div class="md:flex md:gap-40">
                                <div class="flex-grow">
                                    {{-- Title section spans full width for both layouts --}}
                                    <div class="relative w-full m-auto mt-16 mb-40 md:h-[40rem] lg:h-[50rem]">
                                        <div class="flex flex-col bg-white h-full justify-center">
                                            {{-- Location Row --}}
                                            <div class="mb-4">
                                                <p class="text-lg text-neutral-600 flex items-center gap-2">
                                                    <svg 
                                                        xmlns="http://www.w3.org/2000/svg" 
                                                        viewBox="0 0 24 24" 
                                                        fill="none" 
                                                        stroke="currentColor" 
                                                        stroke-width="2" 
                                                        stroke-linecap="round" 
                                                        stroke-linejoin="round"
                                                        style="width: 14px; height: 14px;"
                                                    >
                                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                        <circle cx="12" cy="10" r="3"/>
                                                    </svg>
                                                    @if(!$event->hasLocation)
                                                        {{ ucfirst($event->remoteLocations->first()?->name ?? 'Remote Event') }}
                                                    @else
                                                        @if($event->location->country === 'United States')
                                                            {{ ucfirst($event->location->city) }}, {{ $event->location->region }}
                                                        @else
                                                            {{ ucfirst($event->location->city) }}, {{ $event->location->country }}
                                                        @endif
                                                    @endif
                                                </p>
                                            </div>

                                            {{-- Event Title --}}
                                            <h1 class="text-5xl font-medium text-black leading-tight">{{ $event->name }}</h1>

                                            {{-- Tag Line --}}
                                            @if($event->tag_line)
                                                <p class="text-1xl mt-4 text-neutral-700 font-medium">{{ $event->tag_line }}</p>
                                            @endif

                                            {{-- Ticket Button --}}
                                            <div class="mt-8">
                                                <a 
                                                    href="{{ $event->ticketUrl ?: ($event->websiteUrl ?: $event->organizer->website) }}"
                                                    onclick="axios.post('/track/event/click', { event: {{ $event->id }} })"
                                                    rel="noreferrer noopener" 
                                                    target="_blank"
                                                >
                                                    <button class="font-medium py-6 px-20 rounded-2xl border-none text-white bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1 whitespace-nowrap inline-block">
                                                        @if(count($event->shows) > 0)
                                                            Get Tickets from ${{ number_format($event->priceranges->min('price'), 2) }}
                                                        @else
                                                            View Event
                                                        @endif
                                                    </button>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="w-full relative inline-block md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem] pt-16">
                                    @include('events.show.header')
                                </div>
                            </div>

                            {{-- Bottom section with about/details and purchase --}}
                            <div class="md:flex md:gap-40 pt-16 border-t border-neutral-200">
                                <div class="flex-grow">
                                    <div class="">
                                        @include('events.show.about')
                                        @include('events.show.details')
                                    </div>
                                </div>
                                
                                <div class="w-full relative inline-block md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem]">
                                    <vue-show-purchase
                                        :event="{{ $event }}"
                                        :single-image="true"
                                        :user="user"
                                    ></vue-show-purchase>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Multiple images layout --}}
                        <div class="relative w-full m-auto px-10 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            <div class="relative w-full m-auto mt-16">
                                <div class="flex flex-row justify-between items-center bg-white">
                                    <h1 class="text-4xl font-medium text-black leading-tight">{{ $event->name }}</h1>
                                    <div class="flex items-center gap-8 mt-4">
                                        <button 
                                            onclick="toggleShareModal()" 
                                            class="p-3 rounded-full border border-neutral-200 hover:bg-neutral-50 transition-colors"
                                        >
                                            <svg 
                                                xmlns="http://www.w3.org/2000/svg" 
                                                viewBox="0 0 24 24" 
                                                fill="none" 
                                                stroke="currentColor" 
                                                stroke-width="2" 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round"
                                                style="width: 20px; height: 20px;"
                                            >
                                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                                <polyline points="16 6 12 2 8 6"/>
                                                <line x1="12" y1="2" x2="12" y2="15"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-12">
                            <div id="headerContent">
                                @include('events.show.header-multiple')
                            </div>
                        </div>
                        <div class="relative w-full m-auto p-8 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            <div class="md:flex md:gap-20 lg:gap-36 border-b">
                                <div class="flex-1 min-w-0">
                                {{-- Tag Line --}}
                                    @if($event->tag_line)
                                        <p class="text-1xl mt-4 text-neutral-700 font-medium border-b border-neutral-200 pb-12">{{ $event->tag_line }}</p>
                                    @endif
                                    @include('events.show.about')
                                    @include('events.show.details')
                                </div>

                                <div class="w-full relative shrink-0 md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem]">
                                    <vue-show-purchase
                                        :event="{{ $event }}"
                                        :single-image="false"
                                        :user="user"
                                    ></vue-show-purchase>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Rest of your content --}}
                    <div class="relative w-full m-auto px-10 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        @if ($event->eventreviews && count($event->eventreviews) > 0)
                            @include('events.show.reviews')
                        @endif
                        <vue-show-map :event="{{ $event }}"></vue-show-map>
                        @include('events.show.organizer')
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Share Modal -->
    <div 
        id="shareModal" 
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center"
        onclick="if(event.target === this) toggleShareModal()"
    >
        <div class="w-full md:w-[40rem] mx-4 md:mx-auto bg-white border border-neutral-200 rounded-3xl p-14 relative">
            <!-- Header -->
            <div class="text-center">
                <h2 class="text-4xl font-bold bg-gradient-to-r from-[#E41E53] to-[#FF4E85] bg-clip-text text-transparent">
                    Share this event
                </h2>
            </div>

            <!-- Share Buttons -->
            <div class="flex justify-evenly items-center mt-16">
                @include('events.show.share-buttons')
            </div>

            <!-- Close Button -->
            <button 
                onclick="toggleShareModal()" 
                class="absolute top-6 right-6 text-neutral-500 hover:text-neutral-700"
            >
                <svg 
                    xmlns="http://www.w3.org/2000/svg" 
                    viewBox="0 0 24 24" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                    style="width: 24px; height: 24px;"
                >
                    <path d="M18 6L6 18"/>
                    <path d="M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
@endsection

@section('footer')
    @include('footer.footer-limited')
@endsection

