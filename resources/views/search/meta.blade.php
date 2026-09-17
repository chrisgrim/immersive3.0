{{-- Head tags for the search page (search/all and search/location).

     Every filter combination renders this same page shell, and until
     2026-09-17 it shipped with no <title> and no canonical, so Google was
     picking a canonical for the category pages on its own and changing its
     mind between crawls (Search Console "Duplicate without user-selected
     canonical" on /index/search?category=29&searchType=allEvents).

     The category pages are the only search URLs robots.txt lets Google
     crawl, and the site links to them as ?category={id}&searchType=allEvents
     (event page, event cards, quick bar), so that exact URL is the canonical
     for any single-category search, whatever else is in the query string
     (a slug instead of the id, page=, a different searchType). Everything
     else canonicalises to the bare search page. --}}
@php
    $canonicalCategory = collect($searchedCategories ?? [])->count() === 1
        ? collect($searchedCategories)->first()
        : null;
@endphp
<title>{{ $canonicalCategory ? $canonicalCategory->name : 'Search Events' }} - Everything Immersive</title>
<link rel="canonical" href="{{ $canonicalCategory ? route('search', ['category' => $canonicalCategory->id, 'searchType' => 'allEvents']) : route('search') }}" />
