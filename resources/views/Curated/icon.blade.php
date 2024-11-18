@php
    $elements = collect();
    $name = null;

    if (count($dock->shelves)) {
        $elements = $dock->shelves[0]->publishedPosts;
        $name = $dock->shelves[0]->name;
    } elseif (count($dock->communities)) {
        $elements = $dock->communities;
    } elseif (count($dock->posts)) {
        $elements = $dock->posts;
    }

    $name = $dock->name ?? $name;
@endphp

<div class="mt-8 mb-0 md:mt-16 md:mb-24">
    @if($name)
        <div>
            <h2>{{ $name }}</h2>
        </div>
    @endif

    <div class="whitespace-nowrap overflow-y-hidden overflow-x-auto m-auto w-full">
        <div style="grid-template-columns: 1fr 1fr;" 
             class="w-full whitespace-nowrap overflow-visible my-8 grid md:inline-block gap-8">
            @foreach($elements as $element)
                <div class="w-10/12 relative inline-block md:w-3/12">
                    <div class="w-[70vw] items-center relative flex md:w-full">
                        <a href="{{ count($dock->shelves) 
                                ? '/communities/' . $element->community->slug . '/' . $element->slug 
                                : (count($dock->communities) 
                                    ? '/communities/' . $element->slug 
                                    : '#') }}" 
                           class="block h-full absolute left-0 top-0 w-full rounded-4 z-10">
                        </a>
                        
                        {{-- Replace the SpotlightImage include with: --}}
                        @include('Curated.Components.grid-image', [
                            'element' => $element,
                            'community' => $element->community ?? null
                        ])

                        <div class="flex overflow-x-hidden ml-8 flex-wrap">
                            <div>
                                <p class="truncate font-medium text-2xl">{{ $element->name }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
