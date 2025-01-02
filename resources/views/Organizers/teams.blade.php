@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')

    @include('Nav.creation-desktop')
    
@endsection

@section('content')
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Switch Teams Here</h1>
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($teams as $team)
                    <div class="bg-gray-100 p-4 rounded-lg flex items-center">
                        @if($team->largeImagePath)
                            <img 
                                src="{{ env('VITE_IMAGE_URL') }}{{ $team->largeImagePath }}"
                                alt="{{ $team->name }} logo" 
                                class="w-16 h-16 rounded-full mr-4"
                            >
                        @endif
                        <form action="{{ route('team.switch', $team->slug) }}" method="POST" class="flex-grow">
                            @csrf
                            <button type="submit" class="text-blue-600 hover:underline">
                                {{ $team->name }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">
                <a href="/hosting/getting-started" class="text-blue-600 hover:underline text-lg">
                    Create another organizer
                </a>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    
@endsection 