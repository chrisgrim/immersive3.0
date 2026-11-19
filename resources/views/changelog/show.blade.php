@extends('layouts.master-container')

@section('meta')
    <title>{{ $post['title'] }} - {{ config('app.name') }}</title>
    @if($post['summary'])
        <meta name="description" content="{{ $post['summary'] }}">
        <meta property="og:description" content="{{ $post['summary'] }}">
    @endif
    <meta property="og:title" content="{{ $post['title'] }}">
    @if($post['image'])
        <meta property="og:image" content="{{ url($post['image']) }}">
    @endif
    <link rel="canonical" href="{{ route('changelog.show', $post['slug']) }}">
@endsection

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
@endif
@endsection

@section('content')
{{-- One post, as Airbnb's newsroom article: date, big title, picture, text. --}}
<article class="mx-auto max-w-[76rem] px-8 pt-16 md:pt-24">
    <a href="{{ route('changelog') }}" class="text-[1.4rem] font-semibold text-[#222222] underline">What's new</a>
    <p class="mt-8 text-[1.4rem] text-neutral-500">{{ $post['date']->format('F j, Y') }}</p>
    <h1 class="mt-3 text-[3.2rem] md:text-[4.4rem] leading-[1.15] font-semibold text-[#222222]">{{ $post['title'] }}</h1>
    @if($post['summary'])
        <p class="mt-6 text-[1.8rem] md:text-[2rem] leading-relaxed text-neutral-600">{{ $post['summary'] }}</p>
    @endif
</article>

@if($post['image'])
    <div class="mx-auto max-w-[108rem] px-8 mt-12">
        <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" class="w-full h-auto rounded-[2rem]">
    </div>
@endif

<div class="mx-auto max-w-[76rem] px-8 mt-12 text-[1.7rem] leading-[1.7] text-[#222222]
            [&_p]:mb-[2rem] [&_h2]:text-[2.6rem] [&_h2]:leading-tight [&_h2]:font-semibold [&_h2]:mt-[4rem] [&_h2]:mb-[1.6rem]
            [&_h3]:text-[2rem] [&_h3]:font-semibold [&_h3]:mt-[3.2rem] [&_h3]:mb-[1.2rem]
            [&_ul]:list-disc [&_ul]:pl-[2.4rem] [&_ul]:mb-[2rem] [&_ol]:list-decimal [&_ol]:pl-[2.4rem] [&_ol]:mb-[2rem] [&_li]:mb-[0.8rem]
            [&_a]:underline [&_strong]:font-semibold [&_img]:rounded-[1.6rem] [&_img]:my-[2.4rem]
            [&_blockquote]:border-l-4 [&_blockquote]:border-neutral-300 [&_blockquote]:pl-[2rem] [&_blockquote]:text-neutral-600">
    {!! \App\Support\VueSafe::html($post['html']) !!}
</div>

@if($more->isNotEmpty())
    <div class="mx-auto max-w-screen-5xl px-8 lg-air:px-16 2xl-air:px-32 mt-20">
        <div class="border-t border-neutral-200 pt-12">
            <h2 class="text-[2.6rem] font-semibold text-[#222222] mb-10">Latest updates</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-[2.4rem] gap-y-[4.8rem]">
                @foreach($more as $other)
                    @include('changelog.card', ['post' => $other])
                @endforeach
            </div>
        </div>
    </div>
@endif
@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection
