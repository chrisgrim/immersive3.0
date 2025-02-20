@extends('Layouts.master-container')

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
@endsection


@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-limited-search')
    @endif
    
@endsection

@section('content')
    <div id="mainContent">
        <div id="bodyArea" class="show">
            <div class="show-content">
                @if(count($event->images) === 0)
                    {{-- Title section spans full width for both layouts --}}
                    <div class="relative w-full m-auto px-8 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
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
                                    @include('Events.Show.share-buttons')
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- No images layout --}}
                    <div class="relative w-full m-auto px-8 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        <div class="md:flex md:gap-32">
                            <div class="flex-grow">
                                <div>
                                    @include('Events.Show.header-old')
                                    @include('Events.Show.about')
                                    @include('Events.Show.details')
                                </div>         
                            </div>
                            
                            <div class="w-full relative inline-block md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem]">
                                <vue-show-purchase
                                    :tickets="{{ $event->shows->first()?->tickets ?? '[]' }}"
                                    :event="{{ $event }}"
                                    :single-image="true"
                                    :user="user"
                                ></vue-show-purchase>
                            </div>
                        </div>
                    </div>
                @elseif(count($event->images) === 1)
                    {{-- Single image layout --}}
                    
                    <div class="relative w-full m-auto px-8 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        <div class="md:flex md:gap-40">
                            <div class="flex-grow">
                            {{-- Title section spans full width for both layouts --}}
                                <div class="relative w-full m-auto mt-16 md:h-[40rem] lg:h-[50rem] border-b border-neutral-200">
                                    <div class="flex flex-col bg-white h-full justify-center">
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
                                <div class="">
                                    @include('Events.Show.about')
                                    @include('Events.Show.details')
                                </div>
                            </div>
                            
                            <div class="w-full relative inline-block md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem] pt-16">
                                @include('Events.Show.header')
                                <vue-show-purchase
                                    :tickets="{{ $event->shows->first()?->tickets ?? '[]' }}"
                                    :event="{{ $event }}"
                                    :single-image="true"
                                    :user="user"
                                ></vue-show-purchase>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Multiple images layout --}}
                    <div class="relative w-full m-auto px-8 mt-12 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        <div class="relative w-full m-auto mt-16">
                            <div class="flex flex-row justify-between items-center bg-white">
                                <h1 class="text-4xl font-medium text-black leading-tight">{{ $event->name }}</h1>
                                <div class="flex items-center gap-8 mt-4">
                                    @include('Events.Show.share-buttons')
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-12">
                        <div id="headerContent">
                            @include('Events.Show.header-multiple')
                        </div>
                    </div>
                    <div class="relative w-full m-auto p-8 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                        <div class="md:flex md:gap-20 lg:gap-36 border-b">
                            <div class="flex-1 min-w-0">
                            {{-- Tag Line --}}
                                @if($event->tag_line)
                                    <p class="text-1xl mt-4 text-neutral-700 font-medium border-b border-neutral-200 pb-12">{{ $event->tag_line }}</p>
                                @endif
                                @include('Events.Show.about')
                                @include('Events.Show.details')
                            </div>

                            <div class="w-full relative shrink-0 md:min-w-[30rem] lg:min-w-[37rem] md:w-[37rem]">
                                <vue-show-purchase
                                    :tickets="{{ $event->shows->first()?->tickets ?? '[]' }}"
                                    :event="{{ $event }}"
                                    :single-image="false"
                                    :user="user"
                                ></vue-show-purchase>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Rest of your content --}}
                <div class="relative w-full m-auto px-8 lg-air:px-16 2xl-air:px-32 max-w-screen-xl">
                    @if ($event->eventreviews && count($event->eventreviews) > 0)
                        @include('Events.Show.reviews')
                    @endif
                    <vue-show-map :event="{{ $event }}"></vue-show-map>
                    @include('Events.Show.organizer')
                </div>
            </div>
        </div>
    </div>

    @if(count($event->images) > 1)
        <script>
            window.addEventListener('showAllPhotos', function(e) {
                const headerContent = document.getElementById('headerContent');
                headerContent.innerHTML = `@include('Events.Show.photo-gallery')`;
                setTimeout(checkScrollPosition, 0);
            });

            function closePhotoGallery() {
                const headerContent = document.getElementById('headerContent');
                headerContent.innerHTML = `@include('Events.Show.header-multiple')`;
            }

            function checkScrollPosition() {
                const container = document.getElementById('photo-scroll-container');
                const leftArrow = document.getElementById('leftArrow');
                const rightArrow = document.getElementById('rightArrow');

                if (container) {
                    // Show/hide left arrow based on scroll position
                    leftArrow.classList.toggle('hidden', container.scrollLeft <= 0);

                    // Show/hide right arrow based on whether we can scroll further right
                    const canScrollRight = container.scrollWidth > (container.scrollLeft + container.clientWidth + 50);
                    rightArrow.classList.toggle('hidden', !canScrollRight);
                }
            }

            function scrollPhotoLeft() {
                const container = document.getElementById('photo-scroll-container');
                if (container) {
                    container.scrollBy({
                        left: -container.offsetWidth,
                        behavior: 'smooth'
                    });
                }
            }

            function scrollPhotoRight() {
                const container = document.getElementById('photo-scroll-container');
                if (container) {
                    container.scrollBy({
                        left: container.offsetWidth,
                        behavior: 'smooth'
                    });
                }
            }

            // Initial check for arrow visibility
            document.addEventListener('DOMContentLoaded', checkScrollPosition);

            // Check arrow visibility after any window resize
            window.addEventListener('resize', checkScrollPosition);
        </script>
    @endif
@endsection

@section('footer')
    
@endsection 