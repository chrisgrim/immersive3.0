{{-- Floating "Missing an event?" button, desktop only (the footer link covers
     mobile, where a fixed button would sit over content; the isMobile check
     also keeps it off landscape phones wider than md). Home page only for now.
     z-30: above page content, below the nav (z-40) and every z-50 modal. --}}
@unless(Browser::isMobile())
<vue-suggest-event class="hidden md:flex fixed bottom-16 right-16 z-30 items-center rounded-full border-2 border-black bg-white px-10 py-5 text-xl font-semibold text-black shadow-custom-7 transition-transform hover:scale-105 hover:bg-neutral-50">
    Missing an event?
</vue-suggest-event>
@endunless
