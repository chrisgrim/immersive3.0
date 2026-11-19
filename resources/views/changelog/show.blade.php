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
{{-- One post, laid out as Airbnb's newsroom articles: a narrow column of
     text (about 67rem), a medium-weight title, a small "By ... · date ·
     What's new" line, Key takeaways, one picture a little wider than the
     text, then the post. Pictures inside the post stay as wide as the text,
     framed and captioned, so a screenshot reads as an example, not a page. --}}
<article class="pt-16 md:pt-24 text-[#222222]">
    <header class="mx-auto max-w-[66.8rem] px-8 md:px-0">
        <h1 class="text-[3.4rem] leading-[4rem] md:text-[4.6rem] md:leading-[5.2rem] font-medium tracking-tight">{{ $post['title'] }}</h1>
        <p class="mt-[2.4rem] text-[1.4rem] leading-[2rem]">
            By Everything Immersive · {{ $post['date']->format('F j, Y') }} ·
            <a href="{{ route('changelog') }}" class="underline font-medium">What's new</a>
        </p>

        @if($post['takeaways'])
            <div class="mt-[4rem]">
                <p class="text-[1.8rem] leading-[2.8rem] font-medium">Key takeaways</p>
                <ul class="mt-[1.2rem] list-disc pl-[2.4rem] text-[1.8rem] leading-[2.8rem] space-y-[1.2rem]">
                    @foreach($post['takeaways'] as $takeaway)
                        <li>{{ $takeaway }}</li>
                    @endforeach
                </ul>
            </div>
        @elseif($post['summary'])
            <p class="mt-[4rem] text-[1.8rem] leading-[2.8rem]">{{ $post['summary'] }}</p>
        @endif
    </header>

    {{-- The cover is the card's picture. A post with its own captioned
         screenshots shows those instead: the cover would only repeat them. --}}
    @if($post['image'] && ! str_contains($post['html'], '<figure'))
        <div class="mx-auto max-w-[66.8rem] px-8 md:px-0 mt-[5.6rem]">
            <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" class="w-full h-auto rounded-[1.6rem] border border-[#dddddd]">
        </div>
    @endif

    <div class="mx-auto max-w-[66.8rem] px-8 md:px-0 mt-[5.6rem] text-[1.8rem] leading-[2.8rem]
                [&_p]:mb-[2.4rem] [&_h2]:text-[2.6rem] [&_h2]:leading-[3.2rem] md:[&_h2]:text-[3.2rem] md:[&_h2]:leading-[3.6rem] [&_h2]:font-medium [&_h2]:mt-[4.8rem] [&_h2]:mb-[2.4rem]
                [&_h3]:text-[2.2rem] [&_h3]:leading-[2.8rem] [&_h3]:font-medium [&_h3]:mt-[3.6rem] [&_h3]:mb-[1.6rem]
                [&_ul]:list-disc [&_ul]:pl-[2.4rem] [&_ul]:mb-[2.4rem] [&_ol]:list-decimal [&_ol]:pl-[2.4rem] [&_ol]:mb-[2.4rem] [&_li]:mb-[1.2rem]
                [&_a]:underline [&_strong]:font-medium [&_p_img]:rounded-[1.6rem] [&_p_img]:border [&_p_img]:border-[#dddddd] [&_p_img]:my-[2.4rem]
                [&_figure]:my-[3.2rem] [&_figure_img]:w-full [&_figure_img]:h-auto [&_figure_img]:rounded-[1.6rem] [&_figure_img]:border [&_figure_img]:border-[#dddddd]
                [&_figcaption]:mt-[1.2rem] [&_figcaption]:text-[1.4rem] [&_figcaption]:leading-[2rem] [&_figcaption]:text-[#6a6a6a]
                [&_blockquote]:border-l-4 [&_blockquote]:border-neutral-300 [&_blockquote]:pl-[2rem] [&_blockquote]:text-neutral-600">
        {!! \App\Support\VueSafe::html($post['html']) !!}
    </div>
</article>

@if($more->isNotEmpty())
    <div class="mx-auto max-w-screen-5xl px-8 lg-air:px-16 2xl-air:px-32 mt-24">
        <div class="border-t border-neutral-200 pt-12">
            <h2 class="text-[2.6rem] font-medium text-[#222222] mb-10">More updates</h2>
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
