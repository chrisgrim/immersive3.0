@extends('layouts.master-container')

@section('meta')
    <title>Help Center - {{config('app.name')}}</title>
    <meta name="description" content="Get help using Everything Immersive.">
@endsection

@section('nav')
@if (Browser::isMobile())
    @include('nav.index-mobile')
@else
    @include('nav.index-desktop')
@endif
@endsection

@section('content')
<div class="mx-auto pt-16 pb-32 relative h-full max-w-screen-4xl px-8 lg-air:px-16 2xl-air:px-32">
    <h1 class="text-4xl font-bold mb-4">Help Center</h1>
    <p class="text-lg text-neutral-600 mb-12">
        We're still building out a full help center. In the meantime, here are some quick answers and a way to reach us directly.
    </p>

    <div class="grid gap-8 md:grid-cols-2 max-w-4xl">
        <div class="border border-neutral-200 rounded-2xl p-8">
            <h2 class="text-2xl font-semibold mb-3">Saving events &amp; following organizers</h2>
            <p class="text-neutral-600">
                Tap the heart on any event to save it, or follow an organizer's page to hear about new events they post.
                Everything you've saved lives on your <a href="{{ auth()->check() ? '/users/'.auth()->id() : '/login' }}" class="underline hover:text-black">Profile</a>.
            </p>
        </div>
        <div class="border border-neutral-200 rounded-2xl p-8">
            <h2 class="text-2xl font-semibold mb-3">Managing your account</h2>
            <p class="text-neutral-600">
                Update your name, email, and other details from
                <a href="/account-settings" class="underline hover:text-black">Account settings</a>.
            </p>
        </div>
        <div class="border border-neutral-200 rounded-2xl p-8">
            <h2 class="text-2xl font-semibold mb-3">Hosting an event</h2>
            <p class="text-neutral-600">
                Get started listing your own immersive experience from
                <a href="/hosting/getting-started" class="underline hover:text-black">List Your Event</a>.
            </p>
        </div>
        <div class="border border-neutral-200 rounded-2xl p-8">
            <h2 class="text-2xl font-semibold mb-3">Still stuck?</h2>
            <p class="text-neutral-600">
                Email us at
                <a href="mailto:support@everythingimmersive.com" class="underline hover:text-black">support@everythingimmersive.com</a>
                and we'll get back to you.
            </p>
        </div>
    </div>
</div>
@endsection
