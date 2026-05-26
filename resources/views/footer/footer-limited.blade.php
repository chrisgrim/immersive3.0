<footer id="footer" class="bg-neutral-50 border-t border-neutral-200 mt-24">
    <div class="mx-auto max-w-screen-xl px-8 py-16 lg-air:px-16 2xl-air:px-32">
        {{-- 6-col grid: brand spans 2, four single-col link sections (Discover/Create/About/IEI) --}}
        <div class="grid grid-cols-2 md:grid-cols-6 gap-10 md:gap-10">
            {{-- Brand (2 cols on desktop) --}}
            <div class="col-span-2">
                <a href="/" class="inline-block mb-6">
                    <img
                        src="{{ asset('storage/website-files/Everything_Immersive_logo.png') }}"
                        alt="Everything Immersive"
                        class="h-14 w-auto">
                </a>
                <p class="text-neutral-600 text-xl leading-relaxed max-w-sm">
                    The ultimate resource for discovering immersive experiences.
                </p>
            </div>

            {{-- Discover --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-base uppercase tracking-wider mb-5">Discover</h3>
                <ul class="list-none pl-0 space-y-3 text-lg">
                    <li><a href="/index/search" class="text-neutral-600 hover:text-black transition-colors">Search Events</a></li>
                    <li><a href="/communities" class="text-neutral-600 hover:text-black transition-colors">Communities</a></li>
                </ul>
            </div>

            {{-- Create --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-base uppercase tracking-wider mb-5">Create</h3>
                <ul class="list-none pl-0 space-y-3 text-lg">
                    <li><a href="/hosting/getting-started" class="text-neutral-600 hover:text-black transition-colors">Submit Your Experience</a></li>
                    <li><a href="/teams" class="text-neutral-600 hover:text-black transition-colors">My Teams</a></li>
                </ul>
            </div>

            {{-- About --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-base uppercase tracking-wider mb-5">About</h3>
                <ul class="list-none pl-0 space-y-3 text-lg">
                    <li><a href="/sitemap" class="text-neutral-600 hover:text-black transition-colors">Sitemap</a></li>
                    <li><a href="/terms" class="text-neutral-600 hover:text-black transition-colors">Terms</a></li>
                    <li><a href="/privacy" class="text-neutral-600 hover:text-black transition-colors">Privacy</a></li>
                </ul>
            </div>

            {{-- IEI partner attribution as its own column --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-base uppercase tracking-wider mb-5">Brought To You By</h3>
                <a
                    href="https://immersiveexperience.org"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-block hover:opacity-80 transition-opacity"
                    aria-label="Immersive Experience Institute (opens in new tab)">
                    <img
                        src="{{ asset('images/partners/iei-logo-black.png') }}"
                        alt="Immersive Experience Institute"
                        class="h-16 w-auto"
                        loading="lazy">
                </a>
            </div>
        </div>

        {{-- Copyright --}}
        <div class="border-t border-neutral-200 mt-12 pt-6 text-neutral-500 text-base">
            © {{ date('Y') }} Everything Immersive, Inc. All rights reserved.
        </div>
    </div>
</footer>
