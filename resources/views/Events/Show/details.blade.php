<section class="py-16 md:px-0 border-b border-neutral-200">
    {{-- Audience Role Section --}}
    <div class="mb-16">
        <h3 class="text-5xl md:text-2xl font-medium text-black mb-8">Audience Role</h3>
        <div class="p-8 border rounded-2xl border-neutral-200 hover:bg-neutral-50 transition-all duration-200">
            <div>
                <vue-show-more text="{{ $event->advisories['audience']}}" :limit="80" />
            </div>
            <div class="mt-4">
                <p class="font-medium text-2xl md:text-lg text-black">Ages: {{ $event->age_limits ? $event->age_limits['name'] : $event->advisories['ageRestriction'] }}</p>
            </div>
            {{-- Sexual Advisories (Conditional) --}}
            @if($event->advisories['sexual'])
                <div class="mt-8 inline-flex border-2 border-[#222222] p-6 rounded-2xl hover:bg-neutral-50 transition-all duration-200">
                    
                    <p class="block text-[#222222]">{{ $event->advisories['sexualDescription'] }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Combined Advisories Section --}}
    <div class="mb-16">
        <h3 class="text-5xl md:text-2xl font-medium text-black mt-16 mb-8 md:mt-8 md:mb-4">Content Advisories</h3>
        <div class="grid">
            {{-- Content Advisories --}}
            <div class="border border-neutral-200 p-6 rounded-2xl hover:bg-neutral-50 transition-all duration-200">
                <div class="flex flex-col gap-4">
                    @foreach($event->contentAdvisories as $item)
                        <div class="flex">
                            <span class="text-3xl md:text-1xl mr-2">•</span>
                            <span class="text-3xl md:text-1xl">{{ $item['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <h3 class="text-5xl md:text-2xl font-medium text-black mt-16 mb-8 md:mt-8 md:mb-4">Interaction Advisories</h3>
            {{-- Interaction Advisories --}}
            <div class="border border-neutral-200 p-6 rounded-2xl hover:bg-neutral-50 transition-all duration-200">
                <div class="flex flex-col gap-4">
                    @foreach($event->contactLevels as $item)
                        <div class="flex">
                            <span class="text-3xl md:text-1xl mr-2">•</span>
                            <span class="text-3xl md:text-1xl">{{ $item['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <h3 class="text-5xl md:text-2xl font-medium text-black mt-16 mb-8 md:mt-8 md:mb-4">Mobility Advisories</h3>
            {{-- Mobility Advisories --}}
            <div class="border border-neutral-200 p-6 rounded-2xl hover:bg-neutral-50 transition-all duration-200">
                <div class="flex flex-col gap-4">
                    <div class="flex">
                        <span class="text-3xl md:text-1xl mr-2">•</span>
                        <span class="text-3xl md:text-1xl">
                            Event is <span>@if(!$event->advisories['wheelchairReady']) not @endif</span> wheelchair accessible
                        </span>
                    </div>
                    @foreach($event->mobilityAdvisories as $item)
                        <div class="flex">
                            <span class="text-2xl md:text-1xl mr-2">•</span>
                            <span class="text-2xl md:text-1xl">{{ $item['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    {{-- Tags Section --}}
    <div>
        <h3 class="text-5xl md:text-2xl font-medium text-black mb-8">Tags</h3>
        <div class="flex flex-wrap gap-4">
            @foreach($event->genres as $item)
                <div class="border-2 {{ $item['admin'] == 1 ? 'border-[#222222]' : 'border-neutral-200' }} px-6 py-4 rounded-2xl hover:bg-neutral-50 transition-all duration-200 
                    {{ $item['admin'] == 1 ? 'cursor-pointer' : '' }}">
                    @if($item['admin'] == 1)
                        <a href="/index/search?tag={{ $item['id'] }}&searchType=allEvents" class="text-[#222222]">
                            <span class="font-medium">{{ $item['name'] }}</span>
                        </a>
                    @else
                        <span class="text-neutral-600">{{ $item['name'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>