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
     via Tailwind breakpoints, not a server-side nav swap. --}}
@section('nav')
    @include('nav.nav-limited')
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
