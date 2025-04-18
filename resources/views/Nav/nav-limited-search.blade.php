<nav
    style="height: 8rem;"
    class="event-nav">
    <div class="w-full mx-auto h-32 z-40 absolute top-0 left-0 right-0 bg-white">
        <div class="mx-auto relative h-full grid gap-0 items-center max-w-screen-xl
            grid-cols-[auto_minmax(30rem,_1fr)_auto] px-8
            lg-air:grid-cols-[auto_minmax(30rem,_3fr)_auto] lg-air:px-16
            2xl-air:grid-cols-[1fr_3fr_1fr] 2xl-air:px-32">
            
            <div class="col-span-1 inline-block relative leading-none z-30">
                <a aria-label="Home Button" href="/">
                    <img src="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" alt="Everything Immersive" style="width: 3.5rem; height:3.5rem">
                </a>
            </div>
            <vue-nav-search></vue-nav-search>
            <vue-nav-profile class="col-span-1" :user="user"></vue-nav-profile>
        </div>
    </div>
</nav>