@extends('Layouts.master-container')

@php
    $imageUrl = config('app.image_url', 'https://your-default-url.com/');
@endphp

@section('meta')
@endsection 

@section('nav')
    @if (Browser::isMobile())
        @include('Nav.index-mobile')
    @else
        @include('Nav.index-desktop')
    @endif
@endsection

@section('styles')
<style>
    .card-blurb p {
        font-size: 1.8rem;
        line-height: 2.8rem;
        font-weight: 400;
    }
    .card-blurb h3 {
        color: black;
        line-height: 3rem;
    }
    .card-blurb h4 {
        color: black;
        line-height: 2.5rem;
    }
    .card-blurb strong {
        color: black;
    }
    .card-blurb a {
        color: rgb(37, 99, 235);  /* text-blue-600 equivalent */
        text-decoration: none;
    }
    .card-blurb a strong {
        color: rgb(37, 99, 235);  /* text-blue-600 equivalent */
    }
    .card-blurb a:hover {
        text-decoration: underline;
    }
</style>
@endsection

@section('content')
<div class="max-w-screen-xl-air mx-auto py-36">
    {{-- Header Section - Narrow --}}
    <div class="w-full lg:w-1/2 mx-auto px-8">
        {{-- Post Header --}}
        <div class="mb-8 md:mb-12">
            <h1 class="font-semibold text-4xl md:text-6xl text-black leading-[3rem] md:leading-[5rem]">{{ $post->name }}</h1>
        </div>
        <div class="mb-8 md:mb-12">
            <p class="text-1xl">
                by <span class="font-semibold">{{ $post->user->name }}</span> 
                <span class="mx-2">·</span> 
                {{ \Carbon\Carbon::parse($post->updated_at)->format('F j, Y') }}
            </p>
        </div>

        {{-- Post Blurb --}}
        @if($post->blurb)
        <div class="mt-4 mb-12 md:mb-16">
            <div class="card-blurb leading-relaxed">
                {!! $post->blurb !!}
            </div>
        </div>
        @endif
    </div>

    {{-- Featured Image Section - Wide --}}
    @if(($post->thumbImagePath || ($post->images && $post->images->isNotEmpty()) || ($post->event_id && $post->featured_event_image)) && $post->type !== 'h')
    <div class="m-auto w-full lg:w-9/12 px-8 my-16">
        <div class="relative aspect-[16/9]">
            @php
                $imagePath = '';
                if ($post->event_id && $post->featured_event_image) {
                    $imagePath = $post->featured_event_image->thumbImagePath;
                } elseif ($post->images && $post->images->isNotEmpty()) {
                    $imagePath = $post->images[0]->thumb_image_path ?? $post->images[0]->large_image_path;
                } else {
                    $imagePath = $post->thumbImagePath;
                }
            @endphp
            <img 
                src="{{ $imageUrl . $imagePath }}" 
                class="w-full h-full object-cover rounded-2xl" 
                alt="{{ $post->name }}"
            />
        </div>
    </div>
    @endif

    {{-- Cards Section - Narrow --}}
    <div class="w-full lg:w-1/2 mx-auto px-8">
        <div class="relative space-y-4">
            @foreach($post->cards as $card)
                <div class="card-wrapper mb-4">
                    @if($card->type === 't')
                        {{-- Text Card --}}
                        <div class="mt-4 mb-12 md:mb-16">
                            <div class="card-blurb leading-relaxed">
                                {!! $card->blurb !!}
                            </div>
                        </div>

                    @elseif($card->type === 'i')
                        {{-- Image Card --}}
                        <div class="relative aspect-[16/9] mb-12 md:mb-16">
                            <img 
                                src="{{ $imageUrl . ($card->images->first()?->large_image_path ?? $card->thumbImagePath) }}" 
                                class="w-full h-full object-cover rounded-2xl" 
                                alt="{{ $card->name ?? '' }}"
                            />
                        </div>

                    @elseif($card->type === 'e')
                        {{-- Event Card --}}
                        <div class="border rounded-2xl mb-12 md:mb-16 p-12 rounded-2xl overflow-hidden">
                            @if($card->event)
                                <div class="flex flex-col md:flex-row gap-16 items-center">
                                    {{-- Event Image --}}
                                    @if($card->event->thumbImagePath)
                                        <div class="md:w-[35%] overflow-hidden rounded-2xl">
                                            <div class="aspect-[3/4] w-full">
                                                <img 
                                                    src="{{ $imageUrl . $card->event->thumbImagePath }}" 
                                                    class="w-full h-full object-cover" 
                                                    alt="{{ $card->event->name }}"
                                                />
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Event Content --}}
                                    <div class="space-y-6 md:w-[65%] my-auto">
                                        {{-- Event Title --}}
                                        <h3 class="text-4xl font-bold mt-0">{{ $card->event->name }}</h3>

                                        {{-- Event Dates --}}
                                        <p class="text-gray-600 text-xl">
                                            Booking Through: {{ \Carbon\Carbon::parse($card->event->end_date)->format('l, F j Y') }}
                                        </p>

                                        {{-- Event Blurb --}}
                                        <p class="text-gray-800">{{ $card->event->blurb }}</p>

                                        {{-- Check it out Button --}}
                                        <div>
                                            <a 
                                                href="/events/{{ $card->event->slug }}" 
                                                class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                                Check it out
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('footer')
@endsection 