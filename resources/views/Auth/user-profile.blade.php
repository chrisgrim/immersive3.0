@extends('Layouts.master-container')

@section('meta')
    


@endsection 

@section('nav')
    @if (Browser::isMobile())
        <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
    @else
        @include('Nav.nav-limited-search')
    @endif
@endsection

@section('content')
<div class="m-auto w-full px-8 py-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
    <div class="flex flex-col md:flex-row md:gap-16">
        <!-- Left Column -->
        <div class="md:w-[36rem] space-y-14 mb-16 md:mb-20">
            <!-- Profile Card -->
            <div class="flex flex-row shadow-custom-6 w-full p-8 py-16 rounded-3xl gap-8">
                <!-- Left Column - Image -->
                <div class="flex flex-col items-center w-2/3">
                    <div class="w-44 flex-shrink-0">
                        <div class="relative w-full">
                            <div class="relative w-full aspect-square">
                                <div class="w-full h-full rounded-full overflow-hidden shadow-sm">
                                    @if($user->largeImagePath)
                                        <picture>
                                            <source type="image/webp" srcset="{{ env('VITE_IMAGE_URL') }}{{ $user->thumbImagePath }}"> 
                                            <img class="w-full h-full object-cover" 
                                                 src="{{ env('VITE_IMAGE_URL') }}{{ substr($user->thumbImagePath, 0, -4) }}jpg?timestamp={{ time() }}" 
                                                 alt="{{ $user->name }}'s account">
                                        </picture>
                                    @elseif($user->gravatar)
                                        <img src="{{ $user->gravatar }}" 
                                             class="w-full h-full object-cover" 
                                             alt="{{ $user->name }}'s account">
                                    @else
                                        <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-6xl font-bold text-gray-400">
                                                {{ substr($user->name, 0, 1) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Name -->
                    <div class="w-full flex justify-center px-4">
                        <h1 class="text-3xl text-black font-medium leading-tight mt-8 text-center break-words hyphens-auto md:max-w-[25rem] overflow-hidden">
                            {{ $user->name }}
                        </h1>
                    </div>
                </div>

                <!-- Right Column - Info -->
                <div class="flex-1 flex flex-col space-y-8 m-auto">
                    <div class="flex flex-col items-start">
                        <p class="text-5xl font-semibold text-gray-900">
                            {{ ceil(\Carbon\Carbon::parse($user->created_at)->floatDiffInYears()) }}
                        </p>
                        <p class="text-md font-bold text-gray-600">
                            Years on EI
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Content -->
        <div class="flex-1 leading-none">
            <div class="whitespace-pre-wrap mb-8">
                <div class="flex items-center gap-8 mb-8">
                    <h3 class="text-black text-5xl font-bold leading-tight break-words hyphens-auto">
                        About {{ $user->name }}
                    </h3>
                </div>
                
                @if(auth()->check() && auth()->user()->can('update', $user))
                    <div class="flex items-center gap-4 mb-8">
                        <!-- Edit Button -->
                        <a href="{{ route('users.edit', $user) }}" class="cursor-pointer">
                            <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center hover:bg-gray-200">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" 
                                          stroke-linejoin="round" 
                                          stroke-width="2" 
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                        </a>
                    </div>
                @endif

                @if(auth()->check() && auth()->user()->can('update', $user) && auth()->user()->email_verified_at === null)
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
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
    
@endsection 