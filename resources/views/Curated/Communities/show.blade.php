@extends('layouts.master-container')

@section('meta')
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:description" content="{{ $community->blurb }}" />
    <meta name="twitter:title" content="{{ $community->name }}" />
    @if ($community->images?->first())
        <meta name="twitter:image" content="{{ $community->images->first()->path }}" />
    @elseif ($community->largeImagePath)
        <meta name="twitter:image" content="{{ env('VITE_IMAGE_URL') }}{{ $community->largeImagePath }}" />
    @endif

    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $community->name }}" />
    <meta property="og:description" content="{{ $community->blurb }}" />
    <meta property="og:url" content="{{ url('/') }}/communities/{{ $community->slug }}" />
    <meta property="og:site_name" content="{{ $community->name }}" />
    @if ($community->images?->first())
        <meta property="og:image" content="{{ $community->images->first()->path }}" />
        <meta property="og:image:secure_url" content="{{ $community->images->first()->path }}" />
    @elseif ($community->largeImagePath)
        <meta property="og:image" content="{{ env('VITE_IMAGE_URL') }}{{ $community->largeImagePath }}" />
        <meta property="og:image:secure_url" content="{{ env('VITE_IMAGE_URL') }}{{ $community->largeImagePath }}" />
    @endif

    <title>{{ $community->name }}</title>

    <script type="application/ld+json">
    {
        "@context": "http://schema.org",
        "@type": "Organization",
        "description": "{{ $community->blurb }}",
        "name": "{{ $community->name }}{{ '- ' . \Illuminate\Support\Str::limit($community->blurb, 80) }}",
        "url": "{{ url('/') }}/communities/{{ $community->slug }}",
        @if ($community->images?->first())
            "logo": "{{ $community->images->first()->path }}"
        @elseif ($community->largeImagePath)
            "logo": "{{ env('VITE_IMAGE_URL') }}{{ $community->largeImagePath }}"
        @else
            "logo": "{{ url('/') }}/storage/website-files/schema-community.png"
        @endif
    }
    </script>
@endsection 

@section('nav')

    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited')
    @endif
    
@endsection

@section('content')
    @if($community->status === 'r')
        <div class="bg-amber-50 border-b border-amber-200 mb-8">
            <div class="m-auto w-full px-8 py-4 lg-air:px-16 2xl-air:px-32">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <svg style="width: 20px; height: 20px;" class="text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-lg font-medium text-amber-800">
                            This community is pending review and is only visible to administrators and community members
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="/communities/{{ $community->slug }}/edit" 
                           class="text-lg font-medium text-amber-800 hover:text-amber-600">
                            Edit Community
                            <span aria-hidden="true"> &rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @elseif($community->status === 'n')
        <div class="bg-blue-50 border-b border-blue-200 mb-8">
            <div class="m-auto w-full px-8 py-4 lg-air:px-16 2xl-air:px-32">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <svg style="width: 20px; height: 20px;" class="text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-lg font-medium text-blue-800">
                            Your community has notes that need to be addressed before approval. Please check your email or <a href="/inbox" class="underline hover:text-blue-900">message inbox</a> to learn more.
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="/communities/{{ $community->slug }}/edit" 
                           class="text-lg font-medium text-blue-800 hover:text-blue-600">
                            Edit Community
                            <span aria-hidden="true"> &rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <vue-community-show
        :can-edit="{{ auth()->user() ? auth()->user()->can('update', $community) ? 'true' : 'false' : 'null' }}"
        :loadshelves="{{ json_encode($shelves) }}" 
        :value="{{ $community }}" />

@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection 