@extends('layouts.master-container')

@section('meta')
    <title>What's new - {{ config('app.name') }}</title>
    <meta name="description" content="New features and changes on Everything Immersive.">
    <link rel="canonical" href="{{ route('changelog') }}">
@endsection

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
@endif
@endsection

@section('content')
<div class="mx-auto max-w-screen-5xl px-8 lg-air:px-16 2xl-air:px-32 pt-16 md:pt-24 pb-16">
    <h1 class="text-[3.2rem] md:text-[4rem] leading-tight font-semibold text-[#222222]">What's new</h1>
    <p class="mt-4 text-[1.6rem] text-neutral-600 max-w-[60rem]">New features and changes on Everything Immersive.</p>

    <div class="border-t border-neutral-200 mt-12 pt-12">
        @if($posts->isEmpty())
            <p class="text-[1.6rem] text-neutral-500">Nothing here yet.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-[2.4rem] gap-y-[4.8rem]">
                @foreach($posts as $post)
                    @include('changelog.card', ['post' => $post])
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@section('footer')
    @include('footer.footer-padded')
@endsection
