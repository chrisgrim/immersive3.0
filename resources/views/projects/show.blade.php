@extends('layouts.master-container')

@php
    $description = \App\Support\Seo\PageMeta::projectDescription($project, $page);
    $url = url($project->publicPath());
    $image = $page['image'] ? config('app.image_url').$page['image'] : null;
    // The runs on now or coming up, for the series block Google reads.
    $subEvents = collect($page['now'])->concat($page['coming'])->take(20)->map(fn ($run) => array_filter([
        '@type' => 'Event',
        'name' => $run['name'] ?: $project->name,
        'url' => url($run['url']),
        'startDate' => $run['start'],
        'location' => $run['place'] ? ['@type' => 'Place', 'name' => $run['place'], 'address' => $run['place']] : null,
    ]))->values();
    $sections = ['Now running' => $page['now'], 'Coming up' => $page['coming'], 'Past' => $page['past']];
@endphp

@section('meta')
    <title>{{ \App\Support\Seo\PageMeta::projectTitle($project, $page) }}</title>
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
        @if($subEvents->isNotEmpty())
        "subEvent": @json($subEvents),
        @endif
        "url": @json($url)
    }
    </script>
@endsection

{{-- Pinned header on this page: only the runs on the right scroll. --}}
@push('head')
    <style>#header{position:sticky;top:0;z-index:50;background:#fff}</style>
@endpush

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('nav.nav-limited-search', ['scrollAway' => true])
    @endif
@endsection

@section('content')
    @php($maker = $project->organizer)
    {{-- Airbnb's services page: 1110px wide, a 406px column, a 64px gap, the rest for the runs. --}}
    {{-- As Airbnb does it: the page scrolls as usual, and the left side is pinned under
         the header at exactly the screen's height, so a scroll anywhere moves the runs. --}}
    {{-- Phone (Airbnb's mobile services page): a wide picture across the top, a white
         panel with rounded top corners sliding over it, the maker's circle on its edge,
         then the price, then the runs. --}}
    @php($tabBar = Browser::isMobile())
    <div class="mx-auto w-full max-w-[111rem] px-[2.4rem] {{ $tabBar ? 'pb-[12rem]' : 'pb-[4rem]' }} lg:px-0 lg:py-0">
        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-[6.4rem]">
            {{-- Left: picture with the maker's circle on its bottom edge, then the show itself.
                 Still unless an opened description is too long to fit; then it scrolls on its own. --}}
            <div class="w-full lg:w-[40.6rem] flex-shrink-0 lg:pt-16 lg:pb-8 lg:px-4 lg:-mx-4 lg:sticky lg:top-[var(--nav-bar-height,8rem)] lg:h-[calc(100vh-var(--nav-bar-height,8rem))] lg:overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                {{-- Picture: edge to edge on a phone, a small tall card on a computer. --}}
                <div class="relative -mx-[2.4rem] lg:mx-0">
                    <div class="relative h-[20rem] w-full overflow-hidden bg-neutral-100 lg:h-auto lg:w-[20rem] lg:mx-auto lg:aspect-[3/4] lg:rounded-3xl">
                        @if($image)
                            <img src="{{ $image }}" alt="{{ $project->name }}" class="w-full h-full object-cover" fetchpriority="high">
                        @endif
                        {{-- Edit, for whoever keeps the page and staff: a small round button on the picture, out of the title's way. --}}
                        @can('edit', $project)
                            <a href="{{ route('hosting.projects.edit', $project) }}" aria-label="Edit this show run" class="absolute top-[1.2rem] right-[1.2rem] w-[3.6rem] h-[3.6rem] rounded-full bg-white/90 backdrop-blur shadow-[0_2px_8px_rgba(0,0,0,0.18)] flex items-center justify-center hover:bg-white hover:scale-105 transition">
                                <svg width="18" height="18" class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </a>
                        @endcan
                    </div>
                    <button type="button" aria-label="Go back"
                        onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '/'; }"
                        class="lg:hidden absolute top-[1.6rem] left-[1.6rem] w-[3.2rem] h-[3.2rem] rounded-full bg-white/80 backdrop-blur flex items-center justify-center">
                        <svg width="16" height="16" class="w-[1.6rem] h-[1.6rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                </div>

                <div class="relative flow-root bg-white -mx-[2.4rem] px-[2.4rem] -mt-[3.2rem] rounded-t-[3.2rem] lg:mx-0 lg:px-4 lg:mt-0 lg:rounded-none lg:bg-transparent">
                    @if($maker)
                        @php($makerLink = $maker->status === 'p' ? $maker->publicPath() : null)
                        <{{ $makerLink ? 'a' : 'div' }} @if($makerLink) href="{{ $makerLink }}" aria-label="{{ $maker->name }}" @endif class="relative mx-auto -mt-[3.2rem] w-[6.4rem] h-[6.4rem] rounded-full border-4 border-white overflow-hidden bg-gray-200 shadow-custom-3 flex items-center justify-center {{ $makerLink ? 'hover:scale-105 transition-transform' : '' }}">
                            @if($maker->thumbImagePath)
                                <img src="{{ config('app.image_url') }}{{ $maker->thumbImagePath }}" alt="Logo of {{ $maker->name }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-3xl font-bold text-gray-400">{{ Str::upper(Str::substr($maker->name, 0, 1)) }}</span>
                            @endif
                        </{{ $makerLink ? 'a' : 'div' }}>
                    @endif

                    <div class="text-center mt-[1.2rem]">
                        <h1 class="text-[2.6rem] font-semibold text-[#222222] leading-[3rem] tracking-[-0.052rem] break-words">{{ $project->name }}</h1>


                        @if($maker)
                            <p class="text-[1.4rem] leading-[2rem] text-[#222222] mt-3">
                                Made by
                                @if($maker->status === 'p')
                                    <a href="{{ $maker->publicPath() }}" class="font-semibold hover:underline">{{ $maker->name }}</a>
                                @else
                                    {{ $maker->name }}
                                @endif
                            </p>
                        @endif
                        @if($project->description)
                            {{-- Four lines, then a plain toggle (no script) swaps in the whole text.
                                 Two paragraphs, not one that unclamps: Chrome drew an unclamped
                                 one over "Made by". --}}
                            @php($about = \App\Support\VueSafe::html(nl2br(e($project->description), false)))
                            <div class="relative mt-6 text-[1.4rem] leading-[1.8rem] break-words">
                                <input type="checkbox" id="project-about-more" class="peer sr-only">
                                {{-- The site's global p rule resets size and colour, so they go on each p. --}}
                                <p class="text-[1.4rem] leading-[1.8rem] text-[#6c6c6c] transform-none line-clamp-4 peer-checked:hidden">{!! $about !!}</p>
                                <p class="text-[1.4rem] leading-[1.8rem] text-[#6c6c6c] transform-none hidden peer-checked:block">{!! $about !!}</p>
                                @if(mb_strlen($project->description) > 200)
                                    <label for="project-about-more" class="inline-block mt-3 font-semibold text-[#222222] underline cursor-pointer peer-checked:hidden">Show more</label>
                                    <label for="project-about-more" class="hidden mt-3 font-semibold text-[#222222] underline cursor-pointer peer-checked:inline-block">Show less</label>
                                @endif
                            </div>
                        @endif

                        <p class="text-[1.2rem] leading-[1.6rem] text-[#6c6c6c] mt-6">
                            <span data-run-count>{{ $page['run_count'] }}</span> {{ $page['run_count'] === 1 ? 'run' : 'runs' }}
                            · First presented {{ implode(', ', array_filter([$page['first']['place'], \Carbon\Carbon::parse($page['first']['start'])->format('Y')])) }}
                        </p>
                    </div>

                    @if($page['from'])
                        {{-- One price, like Airbnb: dollars when a run sells in them, else the first. --}}
                        @php($headline = preg_replace('/\.00\b/', '', collect($page['from'])->first(fn ($p) => str_starts_with($p, 'From $')) ?? $page['from'][0]))
                        @php($places = $page['now']->pluck('place')->filter()->unique()->count())
                        @php($note = count($page['from']) > 1 ? 'Prices vary by city' : ($places > 1 ? 'On now in '.$places.' places' : null))
                        <div class="mt-10 flex items-center justify-between gap-4 bg-white rounded-[2.4rem] pl-[3.2rem] pr-[2rem] py-[2rem] shadow-[0_0_0_1px_rgba(0,0,0,0.02),0_8px_24px_rgba(0,0,0,0.1)]">
                            <div class="min-w-0">
                                <p class="text-[1.6rem] lg:text-[1.4rem] leading-[2rem] lg:leading-[1.8rem] font-medium text-[#222222]" data-from-price>{{ $headline }}</p>
                                @if($note)
                                    <p class="text-[1.2rem] leading-[1.6rem] font-medium text-[#6c6c6c]">{{ $note }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right: the runs, two cards across. --}}
            <div id="runs" class="flex-1 min-w-0 mt-[3.2rem] lg:mt-0 lg:pt-16 lg:pb-16">
                {{-- Nothing on or coming: say so first, so the old runs below are not mistaken for current ones. --}}
                @if($page['now']->isEmpty() && $page['coming']->isEmpty())
                    <div class="mb-16 rounded-[2.4rem] bg-[#f7f7f7] px-10 py-8">
                        <p class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222] transform-none">No current events</p>
                        <p class="mt-2 text-[1.6rem] leading-[2rem] text-[#6a6a6a] transform-none">This show is not running right now. Its past runs are below.</p>
                    </div>
                @endif
                @foreach($sections as $heading => $runs)
                    @if($runs->isNotEmpty())
                        <section class="{{ $loop->first ? '' : 'mt-16' }}">
                            {{-- The runs on now need no heading: they lead the column. --}}
                            @if($heading !== 'Now running')
                                <h2 class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222] mb-6">{{ $heading }}</h2>
                            @endif
                            {{-- Past runs are faded, each one coming back to full on its own hover. --}}
                            <div class="flex flex-col gap-4 {{ $heading === 'Past' ? '[&>*]:opacity-60 [&>*:hover]:opacity-100 [&>*]:transition-opacity' : '' }}">
                                @foreach($runs as $run)
                                    @include('projects.partials.run-tile', ['makerId' => $maker?->id])
                                @endforeach
                            </div>
                            @if($runs instanceof \Illuminate\Pagination\LengthAwarePaginator && $runs->hasPages())
                                @include('projects.partials.pager', ['paginator' => $runs])
                            @endif
                        </section>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endsection

{{-- No footer here: the left side never scrolls, only the runs do. --}}
