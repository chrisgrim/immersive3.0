@props([
    'dock'
])

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

<div class="my-8 md:mt-16 md:mb-24 px-8 md:px-32 py-24 border-y border-slate-200">
    <div class="w-full relative block overflow-hidden mb-8 rounded-xl md:flex">
        <div class="flex items-center justify-center md:justify-start md:w-2/5">
            <div class="w-4/5">
                <div>
                    <p class="text-gray-500">{{ $name }}: </p>
                    <h2 class="text-6xl leading-[4.5rem] mt-8 font-medium text-black">{{ $element->name ?? '' }}</h2>
                </div>
                <a href="{{ $url }}">
                    <button class="bg-[#ff385c] text-white border-none p-6 mt-8 rounded-2xl font-bold text-xl">
                        Check it out
                    </button>
                </a>
            </div>
        </div>
        
        @if($element && $element->largeImagePath)
            <div class="rounded-2xl overflow-hidden relative inline-block bg-slate-400 md:w-3/5">
                <div class="aspect-video">
                    <picture class="w-full h-full">
                        <source 
                            type="image/webp" 
                            srcset="{{ $imageUrl }}{{ $element->largeImagePath }}">
                        <img 
                            loading="lazy"
                            class="object-cover w-full h-full"
                            src="{{ $imageUrl }}{{ Str::replaceLast('webp', 'jpg', $element->largeImagePath) }}"
                            alt="{{ $element->name ?? '' }}">
                    </picture>
                </div>
            </div>
        @endif
    </div>
</div>
