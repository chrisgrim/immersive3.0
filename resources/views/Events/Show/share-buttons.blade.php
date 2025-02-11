{{-- Facebook Share --}}
<a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" 
    target="_blank"
    class="text-neutral-700 hover:text-blue-600 transition-colors">
    <svg class="w-10 h-10 fill-current" viewBox="0 0 24 24">
        <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
    </svg>
</a>

{{-- X (Twitter) Share --}}
<a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($event->name) }}" 
    target="_blank"
    class="text-neutral-700 hover:text-black transition-colors">
    <svg class="w-10 h-10 fill-current" viewBox="0 0 24 24">
        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
    </svg>
</a>

{{-- Copy Link --}}
<button 
    onclick="navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))"
    class="text-neutral-700 hover:text-gray-900 transition-colors">
    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
    </svg>
</button>