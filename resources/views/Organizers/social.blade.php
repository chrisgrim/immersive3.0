<div class="w-full p-4 md:p-0">
    <div class="flex flex-col items-start mb-8 w-full truncate">
        @if($organizer->twitterHandle)
        <a 
            class="flex items-center mb-2 truncate w-full" 
            rel="noreferrer noopener" 
            target="_blank"
            href="https://www.twitter.com/{{ $organizer->twitterHandle }}">
            <svg class="m-2 w-10 h-10">
                <use xlink:href="/storage/website-files/icons.svg#ri-twitter-line" />
            </svg>
            <p class="text-xl">{{ $organizer->twitterHandle }}</p>
        </a>
        @endif
        
        @if($organizer->facebookHandle)
        <a 
            class="flex items-center mb-2" 
            rel="noreferrer noopener" 
            target="_blank" 
            href="https://www.facebook.com/{{ $organizer->facebookHandle }}">
            <svg class="m-2 w-10 h-10">
                <use xlink:href="/storage/website-files/icons.svg#ri-facebook-line" />
            </svg>
            <p class="text-xl">{{ $organizer->facebookHandle }}</p>
        </a>
        @endif
        
        @if($organizer->instagramHandle)
        <a 
            class="flex items-center mb-2" 
            rel="noreferrer noopener" 
            target="_blank" 
            href="https://www.instagram.com/{{ $organizer->instagramHandle }}">
            <svg class="m-2 w-10 h-10">
                <use xlink:href="/storage/website-files/icons.svg#ri-instagram-line" />
            </svg>
            <p class="text-xl">{{ $organizer->instagramHandle }}</p>
        </a>
        @endif
        
        @if($organizer->website)
        <a 
            class="flex items-center mb-2" 
            rel="noreferrer noopener" 
            target="_blank" 
            href="{{ $organizer->website }}">
            <svg class="m-2 w-10 h-10">
                <use xlink:href="/storage/website-files/icons.svg#ri-global-line" />
            </svg>
            <p class="text-xl">{{ $organizer->name }}</p>
        </a>
        @endif
    </div>
    
    @if($organizer->patreon)
    <a 
        class="flex items-center mb-2" 
        target="_blank" 
        rel="noreferrer noopener"
        href="https://www.patreon.com/{{ $organizer->patreon }}">
        <button class="uppercase text-lg font-bold bg-default-red border-none max-w-xs text-white">
            Back {{ $organizer->name }} on Patreon
        </button>
    </a>
    @endif
</div>