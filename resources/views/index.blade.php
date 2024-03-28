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
    <script type="application/ld+json">{"@context":"http://schema.org", "@type":"Organization", "address":{"@type":"PostalAddress", "addressLocality":"Petaluma", "addressRegion":"SF", "postalCode":"94952", "streetAddress":"600 East D St"}, "description": "Your resource for immersive and interactive theatre, art, virtual reality, escape rooms, dance and more.", "logo":"https://everythingimmersive.com/storage/website-files/ei-logo.png", "name":"Everything Immersive", "sameAs":[ "https://www.facebook.com/EverythingImmersive/", "https://www.linkedin.com/company/everythingimmersive", "https://www.instagram.com/everythingimmersive/", "https://twitter.com/everythingimmersive", "https://plus.google.com/+everythingimmersive", "https://en.wikipedia.org/wiki/everythingimmersive"], "url":"https://everythingimmersive.com"}</script>


@endsection 

@section('nav')
	<nav class="nav w-full m-auto h-32 z-[1001] relative shadow-light">
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

    

@endsection

@section('footer')
    
@endsection 