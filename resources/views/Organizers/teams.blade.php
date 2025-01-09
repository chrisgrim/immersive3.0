@extends('Layouts.master-container')

@section('meta')
@endsection 

@section('nav')
    @include('Nav.creation-desktop')
@endsection

@section('content')
    <div class="flex justify-end">
        <div class="px-8 md:px-32 w-full ml-[-2rem] mt-20 md:mt-12">
            <!-- Title and Add Button -->
            <div class="w-full flex items-center justify-between mb-8">
                <h2 class="font-medium">Select Organizer</h2>
                <a href="/hosting/getting-started" class="cursor-pointer">
                    <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center text-5xl font-light hover:bg-gray-200">
                        +
                    </div>
                </a>
            </div>

            <div class="w-full">
                <!-- Header Row -->
                <div class="grid gap-8 py-4 items-center grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto]">
                    <div>
                        <h5 class="font-medium">Teams</h5>
                    </div>
                    <div class="hidden md:block">
                        <h5 class="font-medium">Name</h5>
                    </div>
                    <div class="hidden md:block">
                        <h5 class="font-medium">Events</h5>
                    </div>
                    <div class="hidden md:block">
                        <h5 class="font-medium">Created</h5>
                    </div>
                </div>
            </div>

            <!-- Teams List -->
            <div class="w-full">
                @foreach($teams->sortByDesc(fn($team) => $team->id === auth()->user()->current_team_id) as $team)
                    <div class="group relative grid grid-cols-2 md:grid-cols-4 gap-8 p-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto] {{ $team->id === auth()->user()->current_team_id ? 'bg-gray-50 ring-1 ring-gray-200' : '' }}">
                        <!-- Team Image -->
                        <div>
                            @if($team->largeImagePath)
                                <picture>
                                    <source srcset="{{ env('VITE_IMAGE_URL') }}{{ $team->largeImagePath }}" type="image/webp">
                                    <img src="{{ env('VITE_IMAGE_URL') }}{{ $team->largeImagePath }}"
                                         alt="{{ $team->name }} logo"
                                         class="h-16 w-full object-cover rounded-2xl">
                                </picture>
                            @else
                                <div class="h-16 w-full rounded-2xl bg-gray-300"></div>
                            @endif
                        </div>

                        <!-- Team Name -->
                        <div>
                            <form action="{{ route('team.switch', $team->slug) }}" method="POST">
                                @csrf
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="text-2xl font-medium hover:underline">
                                        {{ $team->name }}
                                    </button>
                                    @if($team->id === auth()->user()->current_team_id)
                                        <span class="text-sm px-2 py-1 bg-gray-100 text-gray-600 rounded-full">Current</span>
                                    @endif
                                </div>
                            </form>
                            <p class="text-md leading-4 text-gray-500 mt-1">
                                {{ $team->owner->name }}
                            </p>
                        </div>

                        <!-- Event Count -->
                        <div class="hidden md:block">
                            <div class="flex flex-col">
                                <p class="text-lg text-gray-500">
                                    {{ $team->events_count }} total
                                </p>
                                <p class="text-sm text-gray-400 mt-1">
                                    {{ $team->published_events_count }} published
                                </p>
                            </div>
                        </div>

                        <!-- Created Date -->
                        <div class="hidden md:block">
                            <p class="text-lg text-gray-500">{{ $team->created_at->format('M d, Y') }}</p>
                        </div>

                        <!-- Arrow Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             class="hidden md:absolute right-6 top-1/2 transform -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100"
                             stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@section('footer')
@endsection 