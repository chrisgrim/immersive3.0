@extends('layouts.master-container')

@php
    // Where the browser goes after approval. Shown so a client that calls
    // itself "Claude" but sends the code somewhere odd is visible for what it
    // is. Desktop editors use a private scheme rather than a host.
    $redirect = (string) $request->redirect_uri;
    $destination = parse_url($redirect, PHP_URL_HOST) ?: (parse_url($redirect, PHP_URL_SCHEME) ?: 'the app');
@endphp

@section('meta')
    <title>Connect {{ $client->name }} · Everything Immersive</title>
    <meta name="robots" content="noindex,nofollow">
@endsection

@section('nav')
    @include('nav.nav-limited')
@endsection

@section('content')
<div class="flex justify-center px-6 py-16 md:py-24">
    <div class="w-full max-w-[64rem]">
        <h1 class="text-4.5xl font-semibold mb-4">Connect {{ $client->name }}?</h1>

        <p class="text-2xl text-neutral-700 mb-2">
            <strong class="font-semibold text-black">{{ $client->name }}</strong> wants to use Everything Immersive as
            <strong class="font-semibold text-black">{{ $user->email }}</strong>.
        </p>
        {{-- Its own block, not inside the paragraph: a form is block-level, and
             browsers close a <p> around it, which broke the layout. --}}
        <form method="POST" action="{{ route('logout') }}" class="mb-10">
            @csrf
            <button type="submit" class="text-1xl text-neutral-500 underline hover:no-underline">Not you? Sign out.</button>
        </form>

        <div class="border border-neutral-200 rounded-2xl p-8 mb-8">
            <h2 class="text-2.5xl font-semibold mb-4">It will be able to</h2>
            <ul class="list-disc pl-8 space-y-2 text-1xl text-neutral-700">
                <li>See your organizers, and their draft and published events</li>
                <li>Create event drafts, edit events and add images for your organizers</li>
                <li>Submit your events for review</li>
            </ul>

            <h2 class="text-2.5xl font-semibold mt-8 mb-4">It will not be able to</h2>
            <ul class="list-disc pl-8 space-y-2 text-1xl text-neutral-700">
                <li>Reach any organizer you don't belong to, or anyone else's drafts</li>
                <li>Change your email, password or sign-in methods</li>
                <li>Act as a moderator, even if you are one</li>
            </ul>
        </div>

        <p class="text-1xl text-neutral-500 mb-10">
            After you approve, you'll be sent back to <strong class="font-medium text-neutral-700">{{ $destination }}</strong>.
            Access lasts until you disconnect it, which you can do at any time from
            <a href="{{ route('account-settings.index', ['tab' => 'api-keys']) }}" class="underline hover:no-underline">Account Settings</a>.
        </p>

        <div class="flex flex-wrap items-center gap-4">
            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="rounded-full bg-black text-white px-10 h-16 text-1xl font-medium hover:bg-neutral-800">
                    Approve
                </button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="rounded-full border border-neutral-300 px-10 h-16 text-1xl font-medium hover:border-black">
                    Cancel
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer')
@endsection
