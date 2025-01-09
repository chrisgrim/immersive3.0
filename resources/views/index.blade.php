@extends('Layouts.master-container')

@section('meta')
    <title>{{config('app.name')}}</title>
    <meta name="description" content="Your resource for immersive and interactive theatre, art, virtual reality, escape rooms, dance and more.">
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{config('app.name')}}" />
    <meta property="og:description" content="Your resource for immersive and interactive theatre, art, virtual reality, escape rooms, dance and more." />
    <meta property="og:url" content="{{url()->current()}}" />
    <meta property="og:site_name" content="{{config('app.name')}}" />
    <meta property="article:publisher" content="https://www.facebook.com/webfxinc" />
    <meta property="article:section" content="Immersive" />
    <meta property="og:image" content="{{ url('/') }}/storage/website-files/ei-logo.png" />
    <meta property="og:image:secure_url" content="{{ url('/') }}/storage/website-files/ei-logo.png" />
    <meta property="og:image:width" content="1280" />
    <meta property="og:image:height" content="720" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:description" content="Your resource for immersive and interactive theatre, art, virtual reality, escape rooms, dance and more." />
    <meta name="twitter:title" content="{{config('app.name')}}" />
    <meta name="twitter:site" content="@everythingimmersive" />
    <meta name="twitter:image" content="{{ url('/') }}/storage/website-files/ei-logo.png" />
    <meta name="twitter:creator" content="@everythingimmersive" />
    <script>
        // Define functions in global scope
        window.scrollDockRight = function() {
            const scrollContainer = document.getElementById('dock-scroll-container');
            if (scrollContainer) {
                scrollContainer.scrollBy({ left: 500, behavior: 'smooth' });
            }
        };
        
        window.scrollDockLeft = function() {
            const scrollContainer = document.getElementById('dock-scroll-container');
            if (scrollContainer) {
                scrollContainer.scrollBy({ left: -500, behavior: 'smooth' });
            }
        };
    </script>

    <script type="application/ld+json">{"@context":"http://schema.org", "@type":"Organization", "address":{"@type":"PostalAddress", "addressLocality":"Petaluma", "addressRegion":"SF", "postalCode":"94952", "streetAddress":"600 East D St"}, "description": "Your resource for immersive and interactive theatre, art, virtual reality, escape rooms, dance and more.", "logo":"https://everythingimmersive.com/storage/website-files/ei-logo.png", "name":"Everything Immersive", "sameAs":[ "https://www.facebook.com/EverythingImmersive/", "https://www.linkedin.com/company/everythingimmersive", "https://www.instagram.com/everythingimmersive/", "https://twitter.com/everythingimmersive", "https://plus.google.com/+everythingimmersive", "https://en.wikipedia.org/wiki/everythingimmersive"], "url":"https://everythingimmersive.com"}</script>


@endsection 

@section('nav')
@if (Browser::isMobile())
    @include('Nav.index-mobile')
@else
    @include('Nav.index-desktop')
@endif
@endsection

@section('content')

@if(request('verified') == 1)
    <input type="checkbox" id="hideVerification" class="hidden">
    <div class="bg-blue-300 rounded text-white text-center p-8 z-50 relative animation-fadeOut">
        <label for="hideVerification" class="absolute top-0 right-0 cursor-pointer p-2">x</label>
        <div>You have been verified!</div>
    </div>

@endif

<div>
    @if($docks && count($docks))
        @foreach($docks as $dock)
            @if($dock->type === 'h')
                <div class="max-w-screen-2xl relative m-auto">
                    @include('Curated.hero')
                </div>
            @endif

            <div class="max-w-screen-2xl relative h-full m-auto px-8 lg-air:px-16 xl-air:px-32">
                @if($dock->type === 'i')
                    @include('Curated.icon')
                @endif

                @if($dock->type === 'f')
                    @include('Curated.album', [
                        'dock' => $dock,
                        'number' => 'four'
                    ])
                @endif

                @if($dock->type === 't')
                    @include('Curated.album', [
                        'dock' => $dock,
                        'number' => 'three'
                    ])
                @endif

                @if($dock->type === 's')
                    @include('Curated.spotlight', ['dock' => $dock])
                @endif
            </div>
        @endforeach
    @endif

    <div>
        @if(isset($staffpicks) && count($staffpicks))
            <section id="staffpicks" class="max-w-screen-2xl relative h-full m-auto px-8 lg-air:px-16 xl-air:px-32">
                <vue-staff-picks :staffpicks="{{ json_encode($staffpicks) }}"></vue-staff-picks>
            </section>
        @endif

        <section id="partners" class="max-w-screen-2xl relative h-full m-auto px-8 lg-air:px-16 xl-air:px-32">
            <div class="my-8 md:mt-16 md:mb-24">
                <vue-partners></vue-partners>
            </div>
        </section>

        <section class="max-w-screen-2xl relative h-full m-auto px-8 lg-air:px-16 xl-air:px-32">
            <div class="my-8 md:mt-16 md:mb-24">
                <div class="flex flex-col items-center min-h-[26rem] justify-center">
                    <h3>Read The 2020 Immersive Entertainment Industry Annual Report</h3>
                    <p>Discover The Strength of Immersive Entertainment!</p>
                    <br>
                    <a href="/storage/website-files/documents/2020 Immersive Entertainment Industry Annual Report.pdf">
                        <button class="p-4 rounded-full bg-black border-black text-white hover:text-black hover:bg-white">
                            Check out the report here
                        </button>
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>

@endsection

@section('footer')
    
@endsection 