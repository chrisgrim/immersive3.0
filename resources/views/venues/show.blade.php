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
        window.Laravel.page = { venue: {!! Js::from($venue->only(['name', 'latitude', 'longitude'])) !!} };
    </script>
@endpush

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited-search', ['scrollAway' => true])
    @endif
@endsection

@section('content')
    <div class="m-auto w-full px-10 py-10 md:py-8 md:px-12 lg:px-32 lg:max-w-screen-xl lg:pt-24">
        <div class="flex flex-col md:flex-row md:gap-16">
            @if($image)
                <div class="w-full md:w-[36rem] flex-shrink-0 mb-12 md:mb-0">
                    <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-neutral-100">
                        <img src="{{ $image }}" alt="{{ $venue->name }}" class="w-full h-full object-cover">
                    </div>
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <h1 class="text-5xl md:text-6xl font-medium text-black leading-tight break-words">{{ $venue->name }}</h1>
                @if($venue->room)
                    <p class="text-2xl text-neutral-700 mt-2">{{ $venue->room }}</p>
                @endif

                @if($street || $locality)
                    <p class="text-2xl text-neutral-700 mt-6">{{ $street }}</p>
                    <p class="text-2xl text-neutral-700">{{ $locality }}</p>
                @endif

                @if($venue->description)
                    {{-- Vue compiles this page and collapses newlines, so they are breaks. --}}
                    <p class="text-2xl text-neutral-800 mt-8 break-words">{!! \App\Support\VueSafe::html(nl2br(e($venue->description), false)) !!}</p>
                @endif

                @if($venue->directions)
                    <section class="mt-10" id="how-to-get-there">
                        <h2 class="text-3xl font-medium text-black mb-2">How to get there</h2>
                        {{-- Vue compiles this page and collapses newlines, so they are breaks. --}}
                    <p class="text-2xl text-neutral-800 break-words">{!! \App\Support\VueSafe::html(nl2br(e($venue->directions), false)) !!}</p>
                    </section>
                @endif
            </div>
        </div>

        @if($venue->latitude && $venue->longitude)
            <div class="mt-12">
                <vue-venue-map :venue="pageData.venue"></vue-venue-map>
            </div>
        @endif

        @if($current->isNotEmpty())
            <section class="mt-20">
                <h2 class="text-4xl font-medium text-black mb-4">On now and coming up</h2>
                @foreach($current as $run)
                    @include('projects.partials.run-row')
                @endforeach
            </section>
        @endif

        @if($before->isNotEmpty())
            <section class="mt-20">
                <h2 class="text-4xl font-medium text-black mb-4">Before</h2>
                @foreach($before as $run)
                    @include('projects.partials.run-row')
                @endforeach
            </section>
        @endif
    </div>
@endsection

@section('footer')
    @include('footer.footer-limited')
@endsection
