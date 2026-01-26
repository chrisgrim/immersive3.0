<div class="m-auto w-full px-8 py-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
    <div class="flex flex-col md:flex-row gap-16">
        <div class="md:w-auto">
            <div class="flex flex-col items-center shadow-custom-6 w-full md:w-[30rem] p-8 py-16 rounded-3xl">
                <!-- Profile Image Section -->
                <div class="w-44 flex-shrink-0">
                    <div class="relative w-full">
                        <div class="relative w-full aspect-square">
                            <div class="absolute inset-0 flex justify-center items-center">
                                <div class="w-full h-full rounded-full overflow-hidden hover:border-neutral-300 transition-all shadow-sm bg-[#717171]">
                                    @if(isset($user))
                                        @if($user->largeImagePath)
                                            <picture>
                                                <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $user->thumbImagePath }}"> 
                                                <img class="w-full h-full object-cover" 
                                                     src="{{ config('app.image_url') }}{{ substr($user->thumbImagePath, 0, -4) }}jpg?timestamp={{ time() }}" 
                                                     alt="{{ $user->name }}'s account">
                                            </picture>
                                        @elseif($user->gravatar)
                                            <img src="{{ $user->gravatar }}" 
                                                 class="w-full h-full object-cover" 
                                                 alt="{{ $user->name }}'s account">
                                        @else
                                            <svg class="w-full h-full p-6 text-neutral-500" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="presentation" focusable="false" style="display: block; fill: currentcolor;">
                                                <path d="m16 .7c-8.437 0-15.3 6.863-15.3 15.3s6.863 15.3 15.3 15.3 15.3-6.863 15.3-15.3-6.863-15.3-15.3-15.3zm0 28c-4.021 0-7.605-1.884-9.933-4.81a12.425 12.425 0 0 1 6.451-4.4 6.507 6.507 0 0 1 -3.018-5.49c0-3.584 2.916-6.5 6.5-6.5s6.5 2.916 6.5 6.5a6.513 6.513 0 0 1 -3.019 5.491 12.42 12.42 0 0 1 6.452 4.4c-2.328 2.925-5.912 4.809-9.933 4.809z"></path>
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Info Section -->
                <div class="flex-grow">
                    <div class="flex justify-between mt-8">
                        <div>
                            <h1 class="text-4xl font-medium leading-tight text-center">{{ $user->name }}</h1>
                            <p class="mt-4 font-medium text-1xl text-center">
                                @if($user->isAdmin)
                                    Admin
                                @elseif($user->isCurator)
                                    Curator
                                @elseif($user->isModerator)
                                    Moderator
                                @else
                                    Guest
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User information Section -->
        <div class="flex-1 flex flex-col gap-16 md:px-8">
            @if(auth()->check() && auth()->user()->can('update', $user) && auth()->user()->email_verified_at === null)
                <div class="pb-16 bg-white w-full border-b border-neutral-200">
                    <section>
                        <header>
                            <h2 class="text-3xl font-medium text-gray-900">Email Verification</h2>
                            <p class="mt-1 text-xl text-gray-600">Please verify your email address to access all features.</p>
                        </header>
                        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                            @csrf
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Resend Verification Email
                            </button>
                        </form>
                    </section>
                </div>
            @endif
        </div>
    </div>
</div>