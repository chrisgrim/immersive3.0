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

	@include('layouts.nav-container')
    
@endsection

@section('content')

	<vue-reset-password/>
	
@endsection

@section('footer')
    
@endsection 