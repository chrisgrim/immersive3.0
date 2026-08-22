<nav
    {{-- 8rem, not 19.95rem — this partial is never used on the homepage, so
         nav-search.vue's collapsed = ref(!isHomePage()) always starts
         collapsed here; a mismatched fallback flashes tall-then-short on
         every load until Vue mounts and overwrites --nav-bar-height. --}}
    style="height: var(--nav-bar-height, 8rem); transition: height 320ms cubic-bezier(0.2, 0.8, 0.2, 1); overflow-anchor: none;"
    class="search-nav">
    <div class="w-full mx-auto h-32 z-40 fixed top-0 left-0 right-0 bg-white">
        <div class="mx-auto relative h-full grid gap-0 items-center
            grid-cols-[auto_minmax(30rem,_1fr)_auto] px-8
            lg-air:grid-cols-[auto_minmax(30rem,_3fr)_auto]
            2xl-air:grid-cols-[1fr_3fr_1fr]">
            
            <div class="col-span-1 inline-block relative leading-none z-30 justify-self-start">
                <a aria-label="Home Button" href="/">
                    <img src="{{ asset('storage/website-files/Everything_Immersive_logo.png') }}" alt="Everything Immersive" style="width: 100%; max-width: 260px; margin-top: 0.75rem;" class="hidden lg:block">
                    <img src="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" alt="EI" style="width: 3.5rem; height: 3.5rem;" class="block lg:hidden">
                </a>
            </div>
            <vue-nav-search 
                :searched-events='@json($searchedEvents ?? (object)[])'
                :max-price="{{ $maxprice ?? null }}">
            </vue-nav-search>
            <vue-nav-profile class="col-span-1 justify-self-end" :user="user"></vue-nav-profile>
        </div>
    </div>
</nav>