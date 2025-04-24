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
    
    {{-- Enhanced Structured Data for Better AI and Search Engine Discovery --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Event",
        "name": "{{$event->name}}",
        "description": "{{$event->tag_line ? $event->tag_line : $event->description}}",
        "startDate": "{{ $event->shows->isEmpty() ? \Carbon\Carbon::parse($event->created_at)->toIso8601String() : \Carbon\Carbon::parse($event->shows[0]->date)->toIso8601String() }}",
        "endDate": "{{ \Carbon\Carbon::parse($event->closingDate)->toIso8601String() }}",
        "eventStatus": "https://schema.org/EventScheduled",
        "eventAttendanceMode": "{{ $event->hasLocation ? 'https://schema.org/OfflineEventAttendanceMode' : 'https://schema.org/OnlineEventAttendanceMode' }}",
        "image": [
            @if(count($event->images) > 0)
                @foreach($event->images as $image)
                    "{{ env('VITE_IMAGE_URL') }}{{ $image->large_image_path }}"@if(!$loop->last),@endif
                @endforeach
            @elseif($event->largeImagePath)
                "{{ env('VITE_IMAGE_URL') }}{{ $event->largeImagePath }}"
            @endif
        ],
        "offers": {
            "@type": "AggregateOffer",
            "url": "{{$event->ticketUrl ? $event->ticketUrl : ($event->websiteUrl ? $event->websiteUrl : Request::url())}}",
            @php
                $hasPWYC = false;
                $hasFreeTicket = false;
                $lowestPrice = null;
                $highestPrice = null;
                $currencyCode = "USD";
                
                // Check if we have any tickets or price ranges
                if (isset($event->first_show_tickets) && count($event->first_show_tickets) > 0) {
                    foreach ($event->first_show_tickets as $ticket) {
                        // Set currency if available
                        if (isset($ticket->currency)) {
                            $currencyCode = $ticket->currency;
                        }
                        
                        // Check for PWYC tickets
                        if (isset($ticket->name) && strtoupper(trim($ticket->name)) === 'PWYC') {
                            $hasPWYC = true;
                        }
                        
                        // Check for free tickets
                        if (isset($ticket->ticket_price) && (float)$ticket->ticket_price === 0.00) {
                            $hasFreeTicket = true;
                        }
                        
                        // Track price range
                        $price = isset($ticket->ticket_price) ? (float)$ticket->ticket_price : null;
                        if ($price !== null) {
                            if ($lowestPrice === null || $price < $lowestPrice) {
                                $lowestPrice = $price;
                            }
                            if ($highestPrice === null || $price > $highestPrice) {
                                $highestPrice = $price;
                            }
                        }
                    }
                } elseif (isset($event->priceranges) && count($event->priceranges) > 0) {
                    $lowestPrice = $event->priceranges->min('price');
                    $highestPrice = $event->priceranges->max('price');
                }
                
                // Default if we couldn't find any prices
                if ($lowestPrice === null) {
                    $lowestPrice = 0;
                }
                if ($highestPrice === null) {
                    $highestPrice = $lowestPrice;
                }
            @endphp
            "lowPrice": "{{$lowestPrice}}",
            "highPrice": "{{$highestPrice}}",
            "priceCurrency": "{{$currencyCode}}",
            "availability": "https://schema.org/InStock",
            "validFrom": "{{$event->priceranges[0]->created_at}}",
            "priceSpecification": [
                @if($hasPWYC)
                {
                    "@type": "PriceSpecification",
                    "price": "0.01",
                    "priceCurrency": "{{$currencyCode}}",
                    "description": "Pay What You Can"
                }@if($hasFreeTicket || $highestPrice > $lowestPrice),@endif
                @endif
                @if($hasFreeTicket)
                {
                    "@type": "PriceSpecification",
                    "price": "0",
                    "priceCurrency": "{{$currencyCode}}",
                    "description": "Free Admission"
                }@if($highestPrice > $lowestPrice),@endif
                @endif
                @if($highestPrice > $lowestPrice)
                {
                    "@type": "PriceSpecification",
                    "price": "{{$highestPrice}}",
                    "priceCurrency": "{{$currencyCode}}",
                    "description": "Standard Admission"
                }
                @endif
            ]
        },
        "organizer": {
            "@type": "Organization",
            "name": "{{$event->organizer->name}}",
            "url": "{{$event->organizer->website ? $event->organizer->website : Request::root() .'/organizer/' . $event->organizer->slug}}"
        },
        @if($event->hasLocation)
        "location": {
            "@type": "Place",
            "name": "{{$event->location->venue ? $event->location->venue : $event->name}}",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "{{$event->location->home . ' ' . $event->location->street}}",
                "addressLocality": "{{$event->location->city}}",
                "postalCode": "{{$event->location->postal_code}}",
                "addressRegion": "{{$event->location->region}}",
                "addressCountry": "{{$event->location->country}}"
            }
        },
        @else
        "location": {
            "@type": "VirtualLocation",
            "url": "{{$event->websiteUrl ? $event->websiteUrl : ($event->ticketUrl ? $event->ticketUrl : Request::url())}}"
        },
        @endif
        "performer": {
            "@type": "PerformingGroup",
            "name": "{{$event->organizer->name}}"
        },
        "keywords": [
            @if(count($event->genres) > 0)
                @foreach($event->genres as $index => $genre)
                    "{{$genre['name']}}"@if($index < count($event->genres) - 1),@endif
                @endforeach
            @endif
        ],
        "isAccessibleForFree": {{ ($hasFreeTicket || (isset($event->priceranges[0]) && $event->priceranges[0]->price == 0)) ? 'true' : 'false' }},
        @if(!$event->advisories['wheelchairReady'])
        "accessibilityHazard": ["NoAccessibleEntrance"],
        @endif
        "typicalAgeRange": "{{ $event->age_limits ? $event->age_limits['name'] : $event->advisories['ageRestriction'] }}"
    }
    </script>

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
                        <div class="relative w-full m-auto px-10 mt-24 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            <div class="flex flex-col justify-center bg-white">
                                <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        
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
                                        <h1 class="text-5.5xl font-medium text-black leading-tight">{{ $event->name }}</h1>

                                        {{-- Tag Line --}}
                                        @if($event->tag_line)
                                            <p class="text-2xl text-neutral-700 font-medium">{{ $event->tag_line }}</p>
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
                                    <div class="relative w-full m-auto mt-16 md:h-[40rem] lg:h-[50rem]">
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
                                                            @if(isset($event->priceranges[0]) && $event->priceranges[0]->price == 0)
                                                                {{ isset($event->call_to_action) && !empty($event->call_to_action) ? $event->call_to_action : 'Free Event' }}
                                                            @elseif(isset($event->priceranges[0]) && strtolower($event->priceranges[0]->name) == 'pwyc')
                                                                {{ isset($event->call_to_action) && !empty($event->call_to_action) ? $event->call_to_action : 'PWYC Event' }}
                                                            @elseif(isset($event->first_show_tickets) && count($event->first_show_tickets) > 0)
                                                                @php
                                                                    $hasPWYCTicket = false;
                                                                    $hasFreeTicket = false;
                                                                    foreach ($event->first_show_tickets as $ticket) {
                                                                        if (isset($ticket->name) && strtoupper(trim($ticket->name)) === 'PWYC') {
                                                                            $hasPWYCTicket = true;
                                                                        }
                                                                        if (isset($ticket->ticket_price) && (float)$ticket->ticket_price === 0.00) {
                                                                            $hasFreeTicket = true;
                                                                        }
                                                                    }
                                                                @endphp
                                                                
                                                                @if($hasPWYCTicket)
                                                                    @if(isset($event->call_to_action) && !empty($event->call_to_action))
                                                                        @if(in_array($event->call_to_action, ['Get Tickets', 'Book Now', 'Buy Tickets', 'Register']))
                                                                            PWYC Tickets Available
                                                                        @else
                                                                            {{ $event->call_to_action }}
                                                                        @endif
                                                                    @else
                                                                        PWYC Tickets Available
                                                                    @endif
                                                                @elseif($hasFreeTicket)
                                                                    @if(isset($event->call_to_action) && !empty($event->call_to_action))
                                                                        @if(in_array($event->call_to_action, ['Get Tickets', 'Book Now', 'Buy Tickets', 'Register']))
                                                                            Free Tickets Available
                                                                        @else
                                                                            {{ $event->call_to_action }}
                                                                        @endif
                                                                    @else
                                                                        Free Tickets Available
                                                                    @endif
                                                                @elseif(isset($event->call_to_action) && !empty($event->call_to_action))
                                                                    @php
                                                                        $priceIncludingActions = ['Book Now', 'Get Tickets', 'Buy Tickets', 'Register', 'RSVP'];
                                                                        $showPrice = in_array($event->call_to_action, $priceIncludingActions) && 
                                                                                isset($event->priceranges[0]) && 
                                                                                $event->priceranges[0]->price > 0;
                                                                        $currency = isset($event->first_show_tickets[0]->currency) ? $event->first_show_tickets[0]->currency : '$';
                                                                    @endphp
                                                                    {{ $event->call_to_action }} {{ $showPrice ? 'from ' . $currency . number_format($event->priceranges->min('price'), 2) : '' }}
                                                                @else
                                                                    @php
                                                                        $currency = isset($event->first_show_tickets[0]->currency) ? $event->first_show_tickets[0]->currency : '$';
                                                                        $minPrice = $event->priceranges->min('price');
                                                                    @endphp
                                                                    @if($minPrice == 0)
                                                                        Free Tickets Available
                                                                    @else
                                                                        Get Tickets from {{ $currency }}{{ number_format($minPrice, 2) }}
                                                                    @endif
                                                                @endif
                                                            @elseif(isset($event->call_to_action) && !empty($event->call_to_action))
                                                                {{ $event->call_to_action }}
                                                            @else
                                                                Get Tickets
                                                            @endif
                                                        @else
                                                            {{ isset($event->call_to_action) && !empty($event->call_to_action) ? $event->call_to_action : 'View Event' }}
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
                            <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        
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
                                        <h1 class="text-5.5xl font-medium text-black leading-tight">{{ $event->name }}</h1>

                                        {{-- Tag Line --}}
                                        @if($event->tag_line)
                                            <p class="text-2xl text-neutral-700 font-medium">{{ $event->tag_line }}</p>
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
                        <div class="mt-12">
                            <div id="headerContent">
                                @include('events.show.header-multiple')
                            </div>
                        </div>
                        <div class="relative w-full m-auto p-8 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                            <div class="md:flex md:mt-8 md:gap-20 lg:gap-36 border-b">
                                <div class="flex-1 min-w-0">
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
            <div class="flex flex-col items-center gap-10 mt-12">
                <!-- First Row -->
                <div class="flex justify-evenly items-center w-full">
                    <!-- Copy Link -->
                    <button onclick="copyLink()" class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center">
                            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-medium">Copy Link</span>
                    </button>

                    <!-- WhatsApp -->
                    <a href="https://wa.me/?text={{ urlencode($event->name . ' - ' . ($event->tag_line ?? $event->description) . ' ' . url()->current()) }}" 
                    target="_blank"
                    class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-[#25D366] rounded-full flex items-center justify-center">
                            <svg style="width: 28px; height: 28px;" class="text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm.029 18.88c-1.161 0-2.305-.292-3.318-.844l-3.677.964.984-3.595c-.607-1.052-.927-2.246-.926-3.468.001-3.825 3.113-6.937 6.937-6.937 1.856.001 3.598.723 4.907 2.034 1.31 1.311 2.031 3.054 2.03 4.908-.001 3.825-3.113 6.938-6.937 6.938z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-medium">WhatsApp</span>
                    </a>

                    <!-- Bluesky -->
                    <a href="https://bsky.app/intent/compose?text={{ urlencode($event->name . ' - ' . ($event->tag_line ?? $event->description) . ' ' . url()->current()) }}" 
                    target="_blank"
                    class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-[#0085FF] rounded-full flex items-center justify-center">
                            <svg style="width: 28px; height: 28px;" class="text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.25c-5.384 0-9.75 4.366-9.75 9.75s4.366 9.75 9.75 9.75 9.75-4.366 9.75-9.75S17.384 2.25 12 2.25zm2.658 3.686l2.085 3.616c.293.507.13 1.156-.377 1.45-.198.114-.414.168-.627.168-.37 0-.73-.189-.932-.527l-.682-1.183h-4.25l-.682 1.183a1.083 1.083 0 0 1-1.45.376 1.083 1.083 0 0 1-.377-1.45l4.25-7.364c.293-.507.942-.67 1.45-.377.507.293.67.942.377 1.45l-1.554 2.694h2.428l.341-.592a1.084 1.084 0 0 1 1.45-.377c.508.293.67.943.378 1.45l-.34.59-.04.074zm-2.96 5.879a.899.899 0 0 0-.809-.5H8.013c-.496 0-.898.402-.898.898v3.92c0 .496.402.898.898.898h2.876c.496 0 .898-.402.898-.898v-.964h-2.86v-.98h2.86v-.98h-2.86v-.996h2.86v-.398zm4.503 3.92v-4.818H14.31v5.716h2.79c.496 0 .898-.402.898-.898z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-medium">Bluesky</span>
                    </a>
                </div>

                <!-- Second Row -->
                <div class="flex justify-evenly items-center w-full">
                    <!-- Threads -->
                    <a href="https://www.threads.net/intent/post?text={{ urlencode($event->name . ' - ' . ($event->tag_line ?? $event->description) . ' ' . url()->current()) }}" 
                    target="_blank"
                    class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-black rounded-full flex items-center justify-center">
                            <svg style="width: 28px; height: 28px;" class="text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.5 12.06c0-3.304.986-5.892 2.948-7.697C6.231 2.523 8.632 1.5 11.663 1.5c3.014 0 5.434 1.014 7.192 3.01 1.951 2.096 2.911 5.074 2.855 8.622-.04 4.578-1.657 7.343-2.86 8.58-1.941 2.137-4.814 2.288-6.332 2.288h-.332Zm-4.42-3.219c1.352 1.595 3.706 2.423 6.689 2.423h.33c1.088 0 3.306-.056 4.887-1.8.977-1.08 2.31-3.443 2.346-7.51.046-3.051-.717-5.556-2.276-7.241-1.433-1.656-3.365-2.357-5.929-2.357-2.455 0-4.357.764-5.902 2.276-1.547 1.399-2.314 3.448-2.314 6.121 0 2.953.707 5.251 2.169 6.938Z"/>
                                <path d="M19.237 12.476c-.159-3.577-2.408-5.595-6.395-5.595a6.664 6.664 0 0 0-1.668.214l.161.918a5.642 5.642 0 0 1 1.482-.219c3.345 0 5.209 1.493 5.409 4.342.023.192.028.530.028.84v.146c0 1.261-.512 2.421-1.45 3.273-.828.757-1.954 1.148-3.251 1.148-1.463 0-2.644-.456-3.429-1.316-.776-.85-1.163-2.078-1.163-3.658 0-1.625.418-2.891 1.24-3.766.788-.842 1.899-1.269 3.302-1.269 1.993 0 3.296.943 3.855 2.788l.916-.292c-.731-2.324-2.533-3.428-4.771-3.428-1.7 0-3.09.54-4.124 1.603-1.073 1.112-1.617 2.677-1.617 4.661 0 1.921.517 3.453 1.5 4.437.96.955 2.348 1.442 4.092 1.442 1.638 0 3.039-.481 4.111-1.442 1.177-1.067 1.802-2.595 1.802-4.406v-.145c0-.278-.004-.616-.03-.83Z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-medium">Threads</span>
                    </a>

                    <!-- LinkedIn -->
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}&title={{ urlencode($event->name) }}&summary={{ urlencode($event->tag_line ?? $event->description) }}" 
                    target="_blank"
                    class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-[#0077B5] rounded-full flex items-center justify-center">
                            <svg style="width: 28px; height: 28px;" class="text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-medium">LinkedIn</span>
                    </a>

                    <!-- Email -->
                    <a href="mailto:?subject={{ urlencode('Check out this event: ' . $event->name) }}&body={{ urlencode('I found this event and thought you might be interested: ' . $event->name . "\n\n" . ($event->tag_line ?? $event->description) . "\n\n" . url()->current()) }}" 
                    class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-[#EA4335] rounded-full flex items-center justify-center">
                            <svg style="width: 28px; height: 28px;" class="text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-medium">Email</span>
                    </a>
                </div>
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

