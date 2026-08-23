@extends('layouts.master-container')

@section('meta')



@endsection

{{-- Same nav as Account Settings/Dashboard (its sibling shells — all three
     are the same sidebar+content pattern): the plain max-w-screen-5xl,
     fixed-80px nav-limited, not nav-limited-search (the max-w-screen-xl,
     up-to-200px-tall expandable search hero meant for the homepage/search
     results). nav-limited-search made this page's header both the wrong
     width and needlessly tall. No separate mobile nav here either, matching
     those siblings — the page's own two-column shell handles small screens
     via Tailwind breakpoints, not a server-side nav swap.

     hidden md:block below — Profile's own mobile layout (see profile-index.vue)
     already has its own "Profile" heading up top, matching Airbnb's mobile
     profile screen having no persistent site header at all above it. This
     wrapper is local to this page, not a change to nav.nav-limited itself,
     which several other pages (Account Settings, Creation, Notifications,
     Communities) still render normally on mobile.

     vue-nav-bar-mobile below (md:hidden) is the same bottom tab bar the
     homepage/search/etc. show on mobile (see nav.index-mobile) — Airbnb's
     own mobile profile screen keeps its bottom tab bar visible too, it's not
     something only full-screen pages get. --}}
@section('nav')
    <div class="hidden md:block">
        @include('nav.nav-limited')
    </div>
    <div class="md:hidden">
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    </div>
@endsection

@section('content')
    <vue-profile-index
        :user="{{ $user }}"
        :is-owner="{{ $isOwner ? 'true' : 'false' }}"
    ></vue-profile-index>

    @if(auth()->check() && auth()->user()->can('update', $user) && auth()->user()->email_verified_at === null)
        <div class="mx-auto w-full max-w-screen-xl px-8 lg:px-16 pb-16">
            <div class="bg-white w-full rounded-xl p-8 border border-neutral-200">
                <h2 class="text-3xl font-medium text-gray-900">Email Verification</h2>
                <p class="mt-4 text-xl text-gray-600">Please verify your email address to access all features.</p>
                <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                    @csrf
                    <button type="submit"
                            class="px-8 py-4 bg-gradient-to-r from-[#E41E53] to-[#FF4E85] text-white text-xl font-medium rounded-full hover:from-[#FF2E63] hover:to-[#FF5E95] transition-all">
                        Resend Verification Email
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('footer')

@endsection
