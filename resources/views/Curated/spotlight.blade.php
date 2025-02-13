@php
    $element = null;
    $name = null;
    $url = '#';
    $imageUrl = env('VITE_IMAGE_URL', 'https://ei-test.sfo3.digitaloceanspaces.com/public/');

    // Determine element and name based on dock type
    if (count($dock->shelves)) {
        $element = $dock->shelves[0]->publishedPosts[0] ?? null;
        $name = $dock->shelves[0]->name ?? null;
        if ($element) {
            $url = "/communities/{$element->community->slug}/posts/{$element->slug}";
        }
    } elseif (count($dock->communities)) {
        $element = $dock->communities[0] ?? null;
        if ($element) {
            $url = "/communities/{$element->slug}";
        }
    } elseif (count($dock->posts)) {
        $element = $dock->posts;
    }

    // Override name if dock name exists
    $name = $dock->name ?? $name;
@endphp

<div class="my-8 md:mt-16 md:mb-24">
    <div class="w-full relative block overflow-hidden mb-8 rounded-xl md:flex md:h-[45rem]">
        <div class="flex items-center justify-center p-8 bg-black md:justify-start md:w-2/5 md:px-24 md:py-0">
            <div class="w-full">
                <div>
                    <p class="text-white mb-2">Spotlight: </p>
                    <h2 class="text-5xl text-white">{{ $name }}</h2>
                </div>
                <div class="h-full mt-4">
                    <p class="text-2xl text-white">{{ $element->blurb ?? '' }}</p>
                </div>
                <a href="{{ $url }}">
                    <button class="bg-white border-none p-4 mt-12 rounded-2xl font-bold text-xl">
                        Check it out
                    </button>
                </a>
            </div>
        </div>
        
        @if($element && $element->largeImagePath)
            <div class="relative inline-block bg-slate-400 md:w-3/5 md:h-[45rem] after:absolute after:left-0 after:top-0 after:inline-block after:h-full after:w-full after:bg-gradient-to-r from-black via-transparent to-transparent">
                <picture>
                    <source type="image/webp" 
                            srcset="{{ $imageUrl }}{{ $element->largeImagePath }}">
                    <img loading="lazy"
                         class="object-cover align-bottom w-full h-full"
                         src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $element->largeImagePath) }}"
                         alt="{{ $element->name ?? '' }} Community">
                </picture>
            </div>
        @endif
    </div>
</div>
