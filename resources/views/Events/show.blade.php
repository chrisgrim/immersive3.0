@extends('layouts.master-container')

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
    <meta property="og:image" content="{{ env('VITE_IMAGE_URL') }}{{$event->largeImagePath}}" />
    <meta property="og:image:secure_url" content="{{ env('VITE_IMAGE_URL') }}{{$event->largeImagePath}}" />
    <meta property="og:image:width" content="1280" />
    <meta property="og:image:height" content="720" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:description" content="{{$event->tag_line ? $event->tag_line : $event->description}}" />
    <meta name="twitter:title" content="{{$event->name}}" />
    <meta name="twitter:site" content="@everythingimmersive" />
    <meta name="twitter:image" content="{{ env('VITE_IMAGE_URL') }}{{$event->largeImagePath}}" />
    <meta name="twitter:creator" content="@everythingimmersive" />
    @if($event->hasLocation)
        <script type="application/ld+json">{"@context": "https://schema.org", "@type": "Event", "name": "{{$event->name}}{{$event->tag_line ? '- ' . \Illuminate\Support\Str::limit($event->tag_line, 80) : '- ' . \Illuminate\Support\Str::limit($event->description, 80)}}", @if($event->shows->isEmpty()) "startDate":{{\Carbon\Carbon::parse($event->created_at)->toIso8601String()}}, @else "startDate":"{{\Carbon\Carbon::parse($event->shows[0]->date)->toIso8601String()}}", @endif "endDate": "{{\Carbon\Carbon::parse($event->closingDate)->toIso8601String()}}", "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode", "eventStatus": "https://schema.org/EventScheduled", "location":{"@type": "Place", "name": "{{$event->location->venue ? $event->location->venue : $event->name}}", "address":{"@type": "PostalAddress", "streetAddress": "{{$event->location->home . ' ' . $event->location->street}}", "addressLocality": "{{$event->location->city}}", "postalCode": "{{$event->location->postal_code}}", "addressRegion": "{{$event->location->region}}", "addressCountry": "{{$event->location->country}}"}}, "image":"{{ env('VITE_IMAGE_URL') }}{{$event->largeImagePath}}", "description": "{{$event->tag_line ? $event->tag_line : $event->description}}", "offers":{"@type": "Offer", "url": "{{$event->ticketUrl}}", "price": "{{$event->priceranges[0]->price}}", "priceCurrency": "USD", "availability": "https://schema.org/InStock", "validFrom": "{{$event->priceranges[0]->created_at}}"}, "organizer":{"@type": "Organization", "name": "{{$event->organizer->name}}", "url": "{{$event->organizer->website ? $event->organizer->website : Request::root() .'/organizer/' . $event->organizer->slug}}"}}</script>
    @else
        <script type="application/ld+json">{"@context": "https://schema.org", "@type": "Event", "name": "{{$event->name}}{{$event->tag_line ? '- ' . \Illuminate\Support\Str::limit($event->tag_line, 80) : '- ' . \Illuminate\Support\Str::limit($event->description, 80)}}", @if($event->shows->isEmpty()) "startDate":{{\Carbon\Carbon::parse($event->created_at)->toIso8601String()}}, @else "startDate":"{{\Carbon\Carbon::parse($event->shows[0]->date)->toIso8601String()}}", @endif "endDate": "{{\Carbon\Carbon::parse($event->closingDate)->toIso8601String()}}", "eventStatus": "https://schema.org/EventScheduled", "eventAttendanceMode": "https://schema.org/OnlineEventAttendanceMode", "location":{"@type": "VirtualLocation", "url": "{{$event->websiteUrl ? $event->websiteUrl : ($event->ticketUrl ? $event->ticketUrl : Request::url())}}"}, "image":"{{ env('VITE_IMAGE_URL') }}{{$event->largeImagePath}}", "description": "{{$event->tag_line ? $event->tag_line : $event->description}}", "offers":{"@type": "Offer", "url": "{{$event->ticketUrl ? $event->ticketUrl : ($event->websiteUrl ? $event->websiteUrl : Request::url())}}", "price": "{{$event->priceranges[0]->price}}", "priceCurrency": "USD", "availability": "https://schema.org/InStock", "validFrom": "{{$event->priceranges[0]->created_at}}"}, "organizer":{"@type": "Organization", "name": "{{$event->organizer->name}}", "url": "{{$event->organizer->website ? $event->organizer->website : Request::root() .'/organizer/' . $event->organizer->slug}}"}}</script>
    @endif
    @if (Browser::isMobile())
        <link rel="preload" as="image" type="image/webp" imagesrcset="{{ env('VITE_IMAGE_URL') }}{{$event->thumbImagePath}}">
    @else
        <link rel="preload" as="image" type="image/webp" imagesrcset="{{ env('VITE_IMAGE_URL') }}{{$event->largeImagePath}}">
    @endif
    @vite(['resources/css/flatpickr.css'])
@endsection

@section('nav')
	<nav class="nav w-full m-auto h-32 z-[1001] relative shadow-light bg-white">
		<div class="nav_bar m-auto relative h-full items-center grid gap-0 grid-cols-5 md:px-12 lg:px-32">
			<!-- Home Button Section -->
            <div class="inline-block relative leading-none col-span-1 z-40">
                <a 
                    aria-label="Home Button"
                    href="/">
                    <svg 
                        class="w-10 h-10 inline-block" 
                        viewBox="0 0 256 256">
                        <path 
                            id="EI"
                            d="M149.256,186.943H80.406V144.275h63.908V104.057H80.406V67.443h66.983V27.369H34.506V227.161h114.75V186.943ZM226.121,27.369h-45.9V227.161h45.9V27.369Z" />
                    </svg>
                </a>
            </div>
        	<vue-nav-search></vue-nav-search>
            <vue-nav-profile :user= "{{ auth()->user() ? auth()->user() : 'null' }}"></vue-nav-profile>
        </div>
    </nav>
    
@endsection

@section('content')
    <div id="bodyArea" class="show">
        <div class="show-content">
            @include('Events.Show.header')

            <div class="relative w-full m-auto p-0 md:px-12 lg:px-32 lg:max-w-screen-xl">
                <div class="md:flex md:gap-20 lg:gap-36 border-b">
                    <div class="relative inline-block">
                        @include('Events.Show.about')
<!-- 
                        @if (Browser::isMobile())
                            <div class="min-h-[18rem]">
                                <event-show-dates :event="{{ $event }}"></event-show-dates>
                            </div>
                        @endif

                        @if ($event->staffpick)
                            @include('events.components.staffpick')
                        @endif
 -->

                        @include('Events.Show.details')
                    </div>

                    <div class="w-full relative inline-block md:min-w-[30rem] lg:min-w-[37rem]">
                        <vue-show-purchase
                          :mobile="{{ Browser::isMobile() ? 'true' : 'false' }}"
                          :tickets="{{ $tickets }}"
                          :event="{{ $event }}"
                          ></vue-show-purchase>
                    </div>
                </div>
            </div>

            <div class="relative w-full m-auto p-0 md:px-12 lg:px-32 lg:max-w-screen-xl">
                @if ($event->eventreviews && count($event->eventreviews) > 0)
                    @include('Events.Show.reviews')
                @endif

                <vue-show-map :event="{{ $event }}"></vue-show-map>
                
                @include('Events.Show.organizer')
            </div>
        </div>
    </div>
@endsection

@section('footer')
    
@endsection 