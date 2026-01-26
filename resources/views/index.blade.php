@extends('layouts.master-container')

@section('meta')
    <title>Everything Immersive</title>
    <meta name="description" content="Explore our database of curated events, containing everything from immersive theatre to installation art to escape rooms and beyond.">
    <meta name="keywords" content="immersive events, interactive theatre, immersive art, escape rooms, immersive experiences">
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Everything Immersive" />
    <meta property="og:description" content="Explore our database of curated events, containing everything from immersive theatre to installation art to escape rooms and beyond." />
    <meta property="og:url" content="{{url()->current()}}" />
    <meta property="og:site_name" content="Everything Immersive" />
    <meta property="article:publisher" content="https://www.facebook.com/EverythingImmersive/" />
    <meta property="article:section" content="Immersive" />
    
    @php
        // Try to get the first image from the first dock
        $dockImage = null;
        $imageUrl = config('app.image_url');
        
        if (isset($docks) && count($docks) > 0) {
            $firstDock = $docks[0];
            
            // Try to get image from cards first
            if ($firstDock->cards && count($firstDock->cards) > 0) {
                $firstCard = $firstDock->cards[0];
                if ($firstCard->images && count($firstCard->images) > 0) {
                    $dockImage = $firstCard->images[0]->large_image_path;
                } elseif ($firstCard->event && $firstCard->event->largeImagePath) {
                    $dockImage = $firstCard->event->largeImagePath;
                }
            }
            
            // If no card image, try posts
            if (!$dockImage && $firstDock->posts && count($firstDock->posts) > 0) {
                $firstPost = $firstDock->posts[0];
                if ($firstPost->images && count($firstPost->images) > 0) {
                    $dockImage = $firstPost->images[0]->large_image_path;
                } elseif ($firstPost->featuredEventImage && $firstPost->featuredEventImage->largeImagePath) {
                    $dockImage = $firstPost->featuredEventImage->largeImagePath;
                } elseif ($firstPost->largeImagePath) {
                    $dockImage = $firstPost->largeImagePath;
                }
            }
            
            // If no post image, try shelves
            if (!$dockImage && $firstDock->shelves && count($firstDock->shelves) > 0) {
                foreach ($firstDock->shelves as $shelf) {
                    if ($shelf->dockPosts && count($shelf->dockPosts) > 0) {
                        $firstShelfPost = $shelf->dockPosts[0];
                        if ($firstShelfPost->images && count($firstShelfPost->images) > 0) {
                            $dockImage = $firstShelfPost->images[0]->large_image_path;
                            break;
                        } elseif ($firstShelfPost->featuredEventImage && $firstShelfPost->featuredEventImage->largeImagePath) {
                            $dockImage = $firstShelfPost->featuredEventImage->largeImagePath;
                            break;
                        } elseif ($firstShelfPost->largeImagePath) {
                            $dockImage = $firstShelfPost->largeImagePath;
                            break;
                        }
                    }
                }
            }
        }
        
        // Fallback to logo if no dock image found
        $metaImage = $dockImage ? ($imageUrl . $dockImage) : (url('/') . '/storage/website-files/Everything_Immersive_logo_Short.png');
        $metaImageAlt = $dockImage ? 'Featured immersive experience' : 'Everything Immersive logo';
    @endphp
    
    <meta property="og:image" content="{{ $metaImage }}" />
    <meta property="og:image:secure_url" content="{{ $metaImage }}" />
    @if(!$dockImage)
        <meta property="og:image:width" content="321" />
        <meta property="og:image:height" content="277" />
    @endif
    <meta property="og:image:alt" content="{{ $metaImageAlt }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:description" content="Explore our database of curated events, containing everything from immersive theatre to installation art to escape rooms and beyond." />
    <meta name="twitter:title" content="Everything Immersive" />
    <meta name="twitter:site" content="@everythingimmersive" />
    <meta name="twitter:image" content="{{ $metaImage }}" />
    <meta name="twitter:creator" content="@everythingimmersive" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black" />
    <meta name="format-detection" content="telephone=no" />
    <link rel="canonical" href="{{ url('/') }}" />
    <script>
        window.scrollDockLeft = function() {
            const container = document.getElementById('dock-scroll-container');
            if (container) {
                container.scrollBy({
                    left: -container.offsetWidth,
                    behavior: 'smooth'
                });
            }
        }

        window.scrollDockRight = function() {
            const container = document.getElementById('dock-scroll-container');
            if (container) {
                container.scrollBy({
                    left: container.offsetWidth,
                    behavior: 'smooth'
                });
            }
        }
    </script>

    <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "Organization",
        "name": "Everything Immersive",
        "url": "https://everythingimmersive.com",
        "logo": "https://everythingimmersive.com/storage/website-files/Everything_Immersive_logo_Short.png",
        "description": "Explore our database of curated events, containing everything from immersive theatre to installation art to escape rooms and beyond.",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Petaluma",
            "addressRegion": "SF",
            "postalCode": "94952",
            "streetAddress": "600 East D St"
        },
        "sameAs": [
            "https://www.facebook.com/EverythingImmersive/",
            "https://www.linkedin.com/company/everythingimmersive",
            "https://www.instagram.com/everythingimmersive/",
            "https://twitter.com/everythingimmersive"
        ],
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://everythingimmersive.com/index/search?q={search_term}",
            "query-input": "required name=search_term"
        }
    }
    </script>


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

<!-- <div class="w-full bg-black">
    <div class="max-w-screen-5xl relative m-auto px-10 lg-air:px-16 2xl-air:px-32">
        <div class="flex flex-col md:flex-row">
            <div class="w-full md:w-1/2 flex flex-col justify-center py-16 md:py-0">
                <h2 class="text-5xl text-white mb-8 text-left">Discover Immersive Experiences</h2>
                <p class="text-xl text-gray-100 mb-12 text-left leading-relaxed">Explore our database of curated events, containing everything from immersive theatre to installation art to escape rooms and beyond.</p>
                <div class="text-left">
                    <a href="/index/search?searchType=null&page=1">
                        <button class="bg-transparent text-white border-2 border-[#f7653b] px-8 py-4 text-lg rounded-full font-semibold transition-colors duration-300 hover:bg-[#f7653b]">
                            EXPLORE NOW
                        </button>
                    </a>
                </div>
            </div>
            <div class="w-full md:w-1/2 bg-cover bg-center min-h-[250px] md:min-h-[600px]" style="background-image: url('{{ asset('storage/website-files/ei_background_01.jpg') }}');"></div>
        </div>
    </div>
</div> -->

<div>
    @if($docks && count($docks))
        @foreach($docks as $dock)
            @if($dock->type === 'h')
                <div class="max-w-screen-5xl relative m-auto">
                    @include('curated.hero', ['dock' => $dock])
                </div>
            @endif
            @if($dock->type === 'p')
                <div class="max-w-screen-5xl relative m-auto bg-black text-white">
                    @include('curated.primary', ['dock' => $dock])
                </div>
             @endif
            @if($dock->type === 's')
                <div class="max-w-screen-5xl relative m-auto">
                    @include('curated.spotlight', ['dock' => $dock])
                </div>
            @endif

            <div class="max-w-screen-5xl relative h-full m-auto px-10 lg-air:px-16 2xl-air:px-32">
                @if($dock->type === 'i')
                    @include('curated.icon', ['dock' => $dock])
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
                <div>
                    <h2>Check out our partners</h2>
                    <div class="whitespace-nowrap overflow-y-hidden overflow-x-auto mt-8 md:w-full md:m-auto md:whitespace-normal md:overflow-visible">
                        <div 
                            style="scroll-snap-type: x mandatory;" 
                            class="flex scroll-p-4 scroll-smooth overflow-auto w-full mt-8">
                            <div class="flex w-full relative snap-start snap-always px-4 flex-[1_0_calc(100%-6rem)] md:flex-[0_1_33.3333333333%] md:w-4/12 lg:flex-[0_1_25%] lg:w-3/12">
                                <div class="flex w-full flex-col overflow-hidden relative">
                                    <a 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        href="http://immersiveexperience.org/">
                                        <button
                                            aria-label="Contributer Button"
                                            style="background: url('/storage/website-files/next-stage.jpg') center center / cover no-repeat;" 
                                            class="w-full border border-white rounded-5xl text-white min-h-[20rem] hover:border-black">
                                        </button>
                                    </a>
                                </div>
                            </div>
                            <div class="flex w-full relative snap-start snap-always px-4 flex-[1_0_calc(100%-6rem)] md:flex-[0_1_33.3333333333%] md:w-4/12 lg:flex-[0_1_25%] lg:w-3/12">
                                <div class="flex w-full flex-col overflow-hidden relative">
                                    <a 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        href="https://noproscenium.com/">
                                        <button
                                            aria-label="Contributer Button" 
                                            style="background: url('/storage/website-files/nopro-logo.jpg') center center / cover no-repeat;" 
                                            class="w-full border border-white rounded-5xl text-white min-h-[20rem] hover:border-black">
                                        </button>
                                    </a>
                                </div>
                            </div>
                            <div class="flex w-full relative snap-start snap-always px-4 flex-[1_0_calc(100%-6rem)] md:flex-[0_1_33.3333333333%] md:w-4/12 lg:flex-[0_1_25%] lg:w-3/12">
                                <div class="flex w-full flex-col overflow-hidden relative">
                                    <a 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        href="https://www.argn.com/">
                                        <button
                                            aria-label="Contributer Button" 
                                            style="background: url('/storage/website-files/argn-logo.jpg') center center / contain no-repeat;" 
                                            class="w-full border border-white rounded-5xl text-white min-h-[20rem] hover:border-black">
                                        </button>
                                    </a>
                                </div>
                            </div>
                            <div class="flex w-full relative snap-start snap-always px-4 flex-[1_0_calc(100%-6rem)] md:flex-[0_1_33.3333333333%] md:w-4/12 lg:flex-[0_1_25%] lg:w-3/12">
                                <div class="flex w-full flex-col overflow-hidden relative">
                                    <a 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        href="https://roomescapeartist.com/">
                                        <button
                                            aria-label="Contributer Button" 
                                            style="background: url('/storage/website-files/rea-logo.png') center center / contain no-repeat;" 
                                            class="w-full border border-white rounded-5xl text-white min-h-[20rem] hover:border-black">
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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