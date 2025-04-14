@extends('layouts.master-container')

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
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
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

<div class="w-full bg-black">
    <div class="max-w-screen-5xl relative h-full m-auto px-10 lg-air:px-16 2xl-air:px-32 flex">
        <div style="width: 50%; display: flex; flex-direction: column; justify-content: center;">
            <h2 style="font-size: 3rem; color: white; margin-bottom: 2rem; text-align: left;">Discover Immersive Experiences</h2>
            <p style="font-size: 1.6rem; color: #f0f0f0; margin-bottom: 3rem; text-align: left; line-height: 1.6;">Explore curated immersive events that challenge boundaries and create unforgettable moments. From interactive theatre to cutting-edge mixed reality.</p>
            <div style="text-align: left;">
                <a href="/index/search?searchType=null&page=1">
                    <button style="background-color: transparent; color: white; border: 2px solid #f7653b; padding: 1rem 2rem; font-size: 1.2rem; border-radius: 50px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f7653b'; this.style.color='white';" onmouseout="this.style.backgroundColor='transparent'; this.style.color='white';">EXPLORE NOW</button>
                </a>
            </div>
        </div>
        <div style="width: 50%; background-image: url('{{ asset('storage/website-files/ei_background_01.jpg') }}'); background-size: cover; background-position: center; min-height: 600px;"></div>
    </div>
</div>

<div>
    @if($docks && count($docks))
        @foreach($docks as $dock)
            @if($dock->type === 'h')
                <div class="max-w-screen-5xl relative m-auto">
                    @include('curated.hero')
                </div>
            @endif

            @if($dock->type === 's')
                <div class="max-w-screen-5xl relative m-auto">
                    @include('curated.spotlight', ['dock' => $dock])
                </div>
            @endif

            <div class="max-w-screen-5xl relative h-full m-auto px-10 lg-air:px-16 2xl-air:px-32">
                @if($dock->type === 'i')
                    @include('curated.icon')
                @endif

                @if($dock->type === 'f')
                    @include('curated.album', [
                        'dock' => $dock,
                        'number' => 'four'
                    ])
                @endif

                @if($dock->type === 't')
                    @include('curated.album', [
                        'dock' => $dock,
                        'number' => 'three'
                    ])
                @endif

            </div>
        @endforeach
    @endif

    <div>
        @if(isset($staffpicks) && count($staffpicks))
            <section id="staffpicks" class="max-w-screen-5xl relative h-full m-auto px-10 lg-air:px-16 2xl-air:px-32">
                <vue-staff-picks :staffpicks="{{ json_encode($staffpicks) }}"></vue-staff-picks>
            </section>
        @endif

        <section id="partners" class="max-w-screen-5xl relative h-full m-auto px-10 lg-air:px-16 2xl-air:px-32">
            <div class="my-8 md:mt-16 md:mb-24">
                <vue-partners></vue-partners>
            </div>
        </section>

        <section class="max-w-screen-5xl relative h-full m-auto px-10 lg-air:px-16 2xl-air:px-32">
            <div class="my-8 md:mt-16 md:mb-24">
                <div class="flex flex-col items-center min-h-[26rem] justify-center">
                    <h3>Evolving Immersive: The 2025 Immersive Entertainment & Culture Industry Report</h3>
                    <br>
                    <a target="_blank" href="https://www.gensler.com/gri/immersive-industry-report-2025">
                        <button class="p-4 rounded-full bg-black border-black text-white hover:text-black hover:bg-white">
                            Get your free copy here
                        </button>
                    </a>
                    <div class="mt-16 w-full flex flex-col items-center text-center gap-2">
                        <p>Looking for the 2020 Immersive Entertainment Industry Report? </p>
                        <a  target="_blank" href="/storage/website-files/documents/2020%20Immersive%20Entertainment%20Industry%20Annual%20Report.pdf">
                            <span class="underline">
                                Download the PDF
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection 