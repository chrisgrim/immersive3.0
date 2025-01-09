<nav 
    style="width:100%; margin:auto; height:8rem; z-index: 49; position: relative;">
	<div 
        style="margin: auto; position: relative; height: 100%; display: grid; grid-template-columns: repeat(5, 1fr); gap: 0; align-items: center; max-width: 1280px; padding: 0 8rem;">
        <div class="inline-block relative leading-none col-span-1 z-40">
            <a 
                aria-label="Home Button"
                href="/">
                <svg 
                    style="width: 2.5rem; height: 2.5rem; display: inline-block;"
                    viewBox="0 0 256 256">
                    <path 
                        id="EI"
                        d="M149.256,186.943H80.406V144.275h63.908V104.057H80.406V67.443h66.983V27.369H34.506V227.161h114.75V186.943ZM226.121,27.369h-45.9V227.161h45.9V27.369Z" />
                </svg>
            </a>
        </div>
    	<vue-nav-search></vue-nav-search>
        <vue-nav-profile :user="user"></vue-nav-profile>
    </div>
</nav>