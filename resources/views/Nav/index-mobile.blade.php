<nav style="height: 8rem;">
    <div 
        style="width:100%; margin:auto; height:8rem; z-index: 50; position: fixed; top: 0; left: 0; right: 0; background-color: white;border-bottom: 1px solid #e0e0e0;">
        <div 
            style="margin: auto; position: relative; height: 100%; display:flex; gap: 0; align-items: center; padding: 0 2rem;">
            <vue-nav-search-mobile
                :searched-events='@json($searchedEvents ?? (object)[])'
                :max-price="{{ $maxprice ?? null }}"
            ></vue-nav-search-mobile>
        </div>
    </div>
    <vue-nav-bar-mobile :user="user"></vue-nav-bar-mobile>
</nav>