@extends('Layouts.master-container')

@section('meta')
    

@endsection 

@section('nav')
	<nav class="nav w-full m-auto h-32 z-[1001] fixed shadow-light bg-white">
		<div class="nav_bar m-auto relative h-full items-center grid gap-0 grid-cols-5 md:px-12 lg:px-32">
			<!-- Home Button Section -->
            <div class="inline-block relative leading-none col-span-1 z-40">
                <a 
                    aria-label="Home Button"
                    href="/">
                    <svg 
                        class="w-10 h-10 inline-block" 
                        viewBox="0 0 256 256">
                        <path 
                            id="EI"
                            d="M149.256,186.943H80.406V144.275h63.908V104.057H80.406V67.443h66.983V27.369H34.506V227.161h114.75V186.943ZM226.121,27.369h-45.9V227.161h45.9V27.369Z" />
                    </svg>
                </a>
            </div>
        	<vue-nav-search></vue-nav-search>
            <vue-nav-profile :user= "{{ auth()->user() ? auth()->user() : 'null' }}"></vue-nav-profile>
        </div>
    </nav>
    
@endsection

@section('content')
    
    <vue-event-listings-location 
        :user="user"
        :searched-events="{{ json_encode($searchedEvents) }}" 
        :in-Person-Categories="{{ $inPersonCategories }}"
        :categories="{{ $categories }}"
        :searched-Categories="{{ json_encode($searchedCategories) }}"
        :tags="{{ $tags }}" 
        :searched-Tags="{{ json_encode($searchedTags) }}">

@endsection

@section('footer')
    
@endsection 