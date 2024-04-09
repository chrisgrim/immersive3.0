<div class="m-auto w-full px-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
    <div class="flex flex-col md:flex-row">
        <div class="w-full inline-block md:w-2/6 md:px-8 lg:p-20">
            <div class="sticky top-16 items-center flex flex-col">
                <!-- Clickable Image/Upload Area -->
                <div class="w-full relative">
                    <div class="aspect-square w-full rounded-full overflow-hidden">
                        <div class="absolute inset-0 bg-[#717171] rounded-full flex justify-center items-center">
                            <div class="overflow-hidden flex w-full h-full rounded-full">
                                @if(isset($user))
                                    @if($user->largeImagePath)
                                        <picture>
                                            <source type="image/webp" srcset="{{ env('VITE_IMAGE_URL') }}{{ $user->thumbImagePath }}"> 
                                            <img class="w-full h-full" src="{{ env('VITE_IMAGE_URL') }}{{ substr($user->thumbImagePath, 0, -4) }}jpg?timestamp={{ time() }}" alt="{{ $user->name }}'s account">
                                        </picture>
                                    @elseif($user->gravatar)
                                        <img src="{{ $user->gravatar }}" class="w-full h-full" alt="{{ $user->name }}'s account">
                                    @else
                                        <svg class="w-full h-full fill-white p-12">
                                            <use xlink:href="/storage/website-files/icons.svg#ri-user-line" />
                                        </svg>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- User information Section -->
        <div class="w-full flex flex-col gap-16">  
        	@if(auth()->check() && auth()->user()->can('update', $user) && auth()->user()->email_verified_at === null)
            	<div class="p-4 sm:p-8 bg-blue-400 shadow sm:rounded-lg w-full">
            		<h2 class="text-white mb-10">Please verify email</h2>
            		<form method="POST" action="{{ route('verification.send') }}">
					    @csrf
					    <button type="submit">Resend Verification Email</button>
					</form>
            	</div>
        	@endif       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg w-full">
                <section>
                    <header>
                        <h2 class="text-3xl font-medium text-gray-900">User Information</h2>
                    </header>
                    <div class="mt-6 space-y-6">
                        <div>
                            <h4>Name</h4>
                            <p>{{ $user->name }}</p> <!-- Assuming $user->name is where the user's name is stored -->
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>