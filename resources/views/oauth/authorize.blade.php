@extends('layouts.master-container')

@php
    // Where the browser goes after approval. A real domain is named so a
    // client that calls itself "Claude" but sends the code somewhere odd is
    // visible for what it is; a loopback address or a desktop app's private
    // scheme is "this computer" (see RedirectDestination).
    $destinationHost = \App\Support\RedirectDestination::host((string) $request->redirect_uri);
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
                <li>Act as a moderator{{ $user->isModerator() ? ', unless you say so below' : ', even if you are one' }}</li>
            </ul>
        </div>

        <p class="text-1xl text-neutral-500 mb-10">
            After you approve, you'll be sent back to
            <strong class="font-medium text-neutral-700">{{ $client->name }}</strong>
            @if ($destinationHost === null)
                on this computer.
            @else
                at <strong class="font-medium text-neutral-700">{{ $destinationHost }}</strong>.
            @endif
            Access lasts until you disconnect it, which you can do at any time under
            <a href="{{ route('account-settings.index', ['tab' => 'api-keys']) }}" class="underline hover:no-underline">AI &amp; API access</a>
            in your account settings.
        </p>

        {{-- Cancel is a separate form (DELETE), referenced from the button below
             by id so both buttons can sit on one row. --}}
        <form id="deny-form" method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            @if ($user->isModerator())
                {{-- The one way a connection gets moderator powers: the moderator
                     says so, here, per connection. The client cannot ask for it. --}}
                <label class="flex items-start gap-3 border border-red-200 rounded-2xl p-6 mb-8 cursor-pointer" data-test="moderate-option">
                    <input type="checkbox" name="moderate" value="1" class="mt-1">
                    <span class="text-1xl">
                        <strong class="font-semibold">Include moderator powers for this connection.</strong>
                        <span class="text-neutral-600">
                            {{ $client->name }} will then be able to read and edit any event or organizer on the site, as you.
                            Leave this off unless this assistant needs it; you can disconnect it at any time.
                        </span>
                    </span>
                </label>
            @endif

            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" class="rounded-full bg-black text-white px-10 h-16 text-1xl font-medium hover:bg-neutral-800">
                    Approve
                </button>
                <button type="submit" form="deny-form" class="rounded-full border border-neutral-300 px-10 h-16 text-1xl font-medium hover:border-black">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('footer')
@endsection
