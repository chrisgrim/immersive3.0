{{-- Compact footer for the full-screen search/map pages (only ever included
     by search/all.blade.php and search/location.blade.php — the Profile
     page renders no footer at all via its own empty @section('footer')).
     The full multi-column marketing footer (footer-padded) is too tall here
     — its height collides with the fixed map. Restored to the pre-"IEI
     footer" (e2239fe) single-line version. mt-16 is desktop-only (mobile
     uses mt-6) — on a mobile search results page, the full 40px read as an
     unexplained gap between the results grid and this footer. --}}
<div id="footer" class="border-t border-neutral-200 mt-6 md:mt-16">
    <div class="mx-auto p-4 px-8">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
            <!-- Copyright and Main Links -->
            <div class="mb-6 md:mb-0">
                <div class="flex flex-wrap items-center text-neutral-600 text-xl">
                    <span>© {{ date('Y') }} Everything Immersive, Inc.</span>
                    <span class="mx-2">·</span>
                    <a href="/terms" class="hover:underline">Terms</a>
                    <span class="mx-2">·</span>
                    <a href="/sitemap" class="hover:underline">Sitemap</a>
                    <span class="mx-2">·</span>
                    <a href="/privacy" class="hover:underline">Privacy</a>
                </div>
            </div>
        </div>
    </div>
</div>
