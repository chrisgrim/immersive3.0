@extends('layouts.master-container')

@section('nav')
    <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
@endsection

@section('content')
<div class="min-h-screen bg-white px-8 pb-36">
    <div class="flex items-center justify-between mb-12 mt-20">
        <h1 class="text-5xl font-medium">Menu</h1>
    </div>

    <nav class="flex flex-col gap-8">

        {{-- Same item set, order, and visibility conditions as the desktop
             dropdown (nav-profile.vue) — this used to be its own shorter list
             and had drifted out of sync with it as Dashboard, Notifications,
             and Account settings were added there. Messages is left out here
             (unlike the desktop dropdown) — the mobile bottom bar already has
             its own Inbox tab to the same /inbox destination, so it'd just be
             a second way to reach something one tap away already. Profile
             (formerly Dashboard) still needs its own entry, though — it's the
             only way to reach Liked Events / Saved Searches, which moved here
             when Dashboard was retired. --}}
        <a href="/users/{{ auth()->user()->id }}" class="flex items-center justify-between text-2xl py-4">
            Profile
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

        <div class="border-t border-neutral-200"></div>

        <a href="/notifications" class="flex items-center justify-between text-2xl py-4">
            Notifications
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

        <a href="/account-settings" class="flex items-center justify-between text-2xl py-4">
            Account settings
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

        <div class="border-t border-neutral-200"></div>

        @if(!auth()->user()->organizer)
            <a href="/hosting/getting-started" class="flex items-center justify-between text-2xl py-4">
                List Your Event
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @else
            <a href="/teams" class="flex items-center justify-between text-2xl py-4">
                Organizations
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @endif

        <a href="/communities" class="flex items-center justify-between text-2xl py-4">
            Communities
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

        @if(auth()->user()->isCurator)
            <a href="/admin/dashboard" class="flex items-center justify-between text-2xl py-4">
                Admin Dashboard
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @endif

        <div class="border-t border-neutral-200"></div>

        <form method="POST" action="{{ route('logout') }}" onsubmit="window.location.reload(true); return true;">
            @csrf
            <button type="submit" class="w-full text-left text-2xl text-red-500 py-4">
                Log out
            </button>
        </form>
    </nav>
</div>
@endsection