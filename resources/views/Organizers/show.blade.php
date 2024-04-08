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
            <<vue-nav-profile :user="user"></vue-nav-profile>
        </div>
    </nav>
    
@endsection

@section('content')
    <div class="org">

        <section class="m-auto w-full md:px-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
            <div class="flex flex-col md:flex-row">     
                <div class="w-full inline-block md:w-2/6 md:px-8 lg:p-8">
                    <div class="sticky top-16 items-center flex flex-col">
                        @if(!empty($organizer->largeImagePath))
                            <div class="p-8 w-1/4 mb-8 inline-block align-top w-full md:p-0 md:flex md:justify-center md:w-full">
                                <picture>
                                    <source type="image/webp" srcset="{{ env('VITE_IMAGE_URL') . $organizer->thumbImagePath }}"> 
                                    <img class="w-full h-full rounded-full md:h-80 md:w-80"
                                         src="{{ env('VITE_IMAGE_URL') . substr($organizer->thumbImagePath, 0, -4) . '.jpg' }}"
                                         alt="{{ $organizer->name }} organizer">
                                </picture>
                            </div>
                        @endif
                        @include('Organizers.social', ['organizer' => $organizer])
                    </div>
                </div>
                <div class="w-full inline-block md:w-4/6">
                    <div class="w-3/4 inline-block p-8 float-right md:float-left md:w-full md:px-0">
                        <div class="name">
                            <h2>{{ $organizer->name }}</h2>
                        </div>
                        <div class="joined">
                            <p>Joined EI in {{ $organizer->created_at }}</p>
                        </div>
                    </div>
                    @if (Browser::isMobile())
                        @if(!empty($organizer->largeImagePath))
                            <div class="logo">
                                <picture>
                                    <source type="image/webp" srcset="{{ env('VITE_IMAGE_URL') . $organizer->thumbImagePath }}"> 
                                    <img 
                                        class="w-full h-full"
                                        src="{{ env('VITE_IMAGE_URL') . substr($organizer->thumbImagePath, 0, -4) . '.jpg' }}"
                                        alt="{{ $organizer->name }} organizer">
                                </picture>
                            </div>
                        @endif
                        @include('Organizers.social', ['organizer' => $organizer])
                    @endif
                    <div class="whitespace-pre-wrap mb-8 px-8 md:px-0">
                        <p>{{ $organizer->description }}</p>
                    </div>
                    <vue-organizer-show
                        :organizer="{{ $organizer }}"
                        :user="user">
                    </vue-organizer-show>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('footer')
    <vue-footer></vue-footer>
@endsection 
