@extends('layouts.master-container')

@php
    $description = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $project->description)), 157);
    $url = url($project->publicPath());
    $image = $page['image'] ? config('app.image_url').$page['image'] : null;
    $sections = ['Now running' => $page['now'], 'Coming up' => $page['coming'], 'Past' => $page['past']];
@endphp

@section('meta')
    <title>{{ $project->name }}</title>
    <link rel="canonical" href="{{ $url }}" />
    @if($description)
        <meta name="description" content="{{ $description }}" />
        <meta property="og:description" content="{{ $description }}" />
    @endif
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $project->name }}" />
    <meta property="og:url" content="{{ $url }}" />
    <meta property="og:site_name" content="Everything Immersive" />
    @if($image)
        <meta property="og:image" content="{{ $image }}" />
        <meta name="twitter:image" content="{{ $image }}" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $project->name }}" />
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "EventSeries",
        "name": @json($project->name),
        @if($project->description)
        "description": @json($project->description),
        @endif
        @if($image)
        "image": @json($image),
        @endif
        @if($project->organizer)
        "organizer": {
            "@type": "Organization",
            "name": @json($project->organizer->name),
            "url": @json(url($project->organizer->publicPath()))
        },
        @endif
        "url": @json($url)
    }
    </script>
@endsection

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
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden bg-neutral-100">
                        <img src="{{ $image }}" alt="{{ $project->name }}" class="w-full h-full object-cover" fetchpriority="high">
                    </div>
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <h1 class="text-5xl md:text-6xl font-medium text-black leading-tight break-words">{{ $project->name }}</h1>

                @if($project->organizer)
                    <p class="text-2xl text-neutral-700 mt-4">
                        Made by
                        @if($project->organizer->status === 'p')
                            <a href="{{ $project->organizer->publicPath() }}" class="underline hover:text-black">{{ $project->organizer->name }}</a>
                        @else
                            {{ $project->organizer->name }}
                        @endif
                    </p>
                @endif

                @if($page['from'])
                    <p class="text-3xl font-medium text-black mt-6" data-from-price>{{ implode(' · ', $page['from']) }}</p>
                @endif

                <dl class="mt-8 space-y-4 text-xl">
                    <div>
                        <dt class="font-semibold">First presented</dt>
                        <dd class="text-neutral-600">{{ implode(', ', array_filter([$page['first']['place'], \Carbon\Carbon::parse($page['first']['start'])->format('Y')])) }}</dd>
                    </div>
                    @if($page['latest'] && $page['latest']['id'] !== $page['first']['id'])
                        <div>
                            <dt class="font-semibold">{{ $page['latest']['bucket'] === 'now' ? 'Current' : 'Latest' }}</dt>
                            <dd class="text-neutral-600">{{ implode(', ', array_filter([$page['latest']['place'], $page['latest']['dates']])) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="font-semibold">Runs</dt>
                        <dd class="text-neutral-600" data-run-count>{{ $page['run_count'] }}</dd>
                    </div>
                </dl>

                @if($project->description)
                    <p class="text-2xl text-neutral-800 mt-10 whitespace-pre-line break-words">{{ $project->description }}</p>
                @endif
            </div>
        </div>

        @foreach($sections as $heading => $runs)
            @if($runs->isNotEmpty())
                <section class="mt-20">
                    <h2 class="text-4xl font-medium text-black mb-4">{{ $heading }}</h2>
                    @foreach($runs as $run)
                        @include('projects.partials.run-row')
                    @endforeach
                </section>
            @endif
        @endforeach
    </div>
@endsection

@section('footer')
    @include('footer.footer-limited')
@endsection
