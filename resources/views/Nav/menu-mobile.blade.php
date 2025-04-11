@extends('layouts.master-container')

@section('nav')
    <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
@endsection

@section('content')
<div class="min-h-screen bg-white px-8">
    <div class="flex items-center justify-between mb-12 mt-20">
        <h1 class="text-5xl font-medium">Menu</h1>
    </div>

    <nav class="flex flex-col gap-8">


        <a href="/users/{{ auth()->id() }}/edit" class="flex items-center justify-between text-2xl py-4">
            User Settings
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

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

        @if(auth()->user()->isCommunityMember)
            <a href="/communities" class="flex items-center justify-between text-2xl py-4">
                Communities
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @endif

        @if(auth()->user()->isCurator)
            <a href="/admin/dashboard" class="flex items-center justify-between text-2xl py-4">
                Admin Dashboard
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-8" onsubmit="window.location.reload(true); return true;">
            @csrf
            <button type="submit" class="w-full text-left text-2xl text-red-500 py-4">
                Sign Out
            </button>
        </form>
    </nav>
</div>
@endsection