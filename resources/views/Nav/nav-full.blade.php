<nav 
    style="width:100%; margin:auto; height:8rem; z-index: 49; position: relative; border-bottom: 1px solid #e5e5e5; box-sizing: border-box;">
	<div 
        style="margin: auto; position: relative; height: 100%; display: grid; grid-template-columns: 1fr minmax(auto, 400px) 1fr; gap: 0; align-items: center; padding-left: 2rem; padding-right: 2rem;"
        class="nav_bar">
        <div class="inline-block relative leading-none col-span-1 z-40">
            <a aria-label="Home Button" href="/">
                <img src="{{ asset('storage/website-files/Everything_Immersive_logo.png') }}" alt="Everything Immersive" style="width: 100%; max-width: 260px; margin-top: 0.75rem;" class="hidden lg:block">
                <img src="{{ asset('storage/website-files/Everything_Immersive_logo_Short.png') }}" alt="EI" style="width: 3.5rem; height: 3.5rem;" class="block lg:hidden">
            </a>
        </div>
        <div></div>
        <div><vue-nav-profile :user="user"></vue-nav-profile></div>
    </div>
</nav>