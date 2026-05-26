<footer id="footer" class="bg-neutral-50 border-t border-neutral-200 mt-24">
    <div class="mx-auto px-8 py-16">
        {{-- 4-column link grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-10 md:gap-12">
            {{-- Brand --}}
            <div class="col-span-2 md:col-span-1">
                <a href="/" class="inline-block mb-6">
                    <img
                        src="{{ asset('storage/website-files/Everything_Immersive_logo.png') }}"
                        alt="Everything Immersive"
                        class="h-12 w-auto">
                </a>
                <p class="text-neutral-600 text-base leading-relaxed pr-4">
                    The ultimate resource for discovering immersive experiences.
                </p>
            </div>

            {{-- Discover --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-sm uppercase tracking-wider mb-5">Discover</h3>
                <ul class="space-y-3 text-base">
                    <li><a href="/index/search" class="text-neutral-600 hover:text-black transition-colors">Search Events</a></li>
                    <li><a href="/communities" class="text-neutral-600 hover:text-black transition-colors">Communities</a></li>
                </ul>
            </div>

            {{-- Create --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-sm uppercase tracking-wider mb-5">Create</h3>
                <ul class="space-y-3 text-base">
                    <li><a href="/hosting/getting-started" class="text-neutral-600 hover:text-black transition-colors">Submit Your Experience</a></li>
                    <li><a href="/teams" class="text-neutral-600 hover:text-black transition-colors">My Teams</a></li>
                </ul>
            </div>

            {{-- About --}}
            <div>
                <h3 class="text-neutral-900 font-semibold text-sm uppercase tracking-wider mb-5">About</h3>
                <ul class="space-y-3 text-base">
                    <li><a href="/sitemap" class="text-neutral-600 hover:text-black transition-colors">Sitemap</a></li>
                    <li><a href="/terms" class="text-neutral-600 hover:text-black transition-colors">Terms</a></li>
                    <li><a href="/privacy" class="text-neutral-600 hover:text-black transition-colors">Privacy</a></li>
                </ul>
            </div>
        </div>

        {{-- IEI partner attribution (per Kathryn's request — above copyright on every page) --}}
        <div class="border-t border-neutral-200 mt-12 pt-8">
            <a
                href="https://immersiveexperience.org"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-4 hover:opacity-80 transition-opacity"
                aria-label="Immersive Experience Institute (opens in new tab)">
                <span class="text-neutral-500 text-sm uppercase tracking-wider">Brought to you by</span>
                <img
                    src="{{ asset('images/partners/iei-logo-black.png') }}"
                    alt="Immersive Experience Institute"
                    class="h-12 w-auto"
                    loading="lazy">
            </a>
        </div>

        {{-- Copyright --}}
        <div class="border-t border-neutral-200 mt-8 pt-6 text-neutral-500 text-sm">
            © {{ date('Y') }} Everything Immersive, Inc. All rights reserved.
        </div>
    </div>
</footer>
