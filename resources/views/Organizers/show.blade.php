@extends('Layouts.master-container')

@section('meta')

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:description" content="{{$organizer->description}}" />
    <meta name="twitter:title" content="{{$organizer->name}}" />
    @if ($organizer->twitterHandle) 
        <meta name="twitter:site" content="@{{$organizer->twitterHandle}}" />
        <meta name="twitter:creator" content="@{{$organizer->twitterHandle}}" />
    @endif
    @if ($organizer->largeImagePath) 
        <meta name="twitter:image" content="{{ env('VITE_IMAGE_URL') }}{{$organizer->largeImagePath}}" />
    @endif

    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{$organizer->name}}" />
    <meta property="og:description" content="{{$organizer->description}}" />
    <meta property="og:url" content="{{ url('/') }}/organizer/{{$organizer->slug}}" />
    <meta property="og:site_name" content="{{$organizer->name}}" />
    @if ($organizer->largeImagePath) 
        <meta property="og:image" content="{{ env('VITE_IMAGE_URL') }}{{$organizer->largeImagePath}}" />
        <meta property="og:image:secure_url" content="{{ env('VITE_IMAGE_URL') }}{{$organizer->largeImagePath}}" />
        <meta name="twitter:image" content="{{ env('VITE_IMAGE_URL') }}{{$organizer->largeImagePath}}" />
    @endif
    <title>{{$organizer->name}}</title>
    <script type="application/ld+json">{"@context":"http://schema.org", "@type":"Organization", "description": "{{$organizer->description}}", "name": "{{$organizer->name}}{{'- ' . \Illuminate\Support\Str::limit($organizer->description, 80)}}", "sameAs": @json($organizer->getHandles()), @if ($organizer->website) "url":"{{$organizer->website}}", @else "url":"{{url('/')}}/organizer/{{$organizer->slug}}", @endif @if ($organizer->largeImagePath) "logo":"{{ env('VITE_IMAGE_URL') }}{{$organizer->largeImagePath}}"}@else "logo":"{{url('/')}}/storage/website-files/schema-organizer.png"}@endif </script>


@endsection

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-limited-search')
    @endif
    
@endsection

@section('content')
    <vue-organizer
        :organizer="{{ $organizer }}"
        :user="user"
    ></vue-organizer>
@endsection

@section('footer')
    <vue-footer></vue-footer>
@endsection 
