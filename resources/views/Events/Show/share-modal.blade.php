<div id="shareModal" class="fixed inset-0 bg-black/50 z-50 hidden">
    <div class="fixed inset-x-0 bottom-0 bg-white rounded-t-3xl p-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold">Share</h2>
            <button onclick="closeShareModal()" class="p-2">
                <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <div class="grid grid-cols-5 gap-4">
            <!-- Copy Link -->
            <button onclick="copyLink()" class="flex flex-col items-center gap-2">
                <div class="w-14 h-14 bg-neutral-100 rounded-full flex items-center justify-center">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-1xl">Copy Link</span>
            </button>

            <!-- WhatsApp -->
            <a href="https://wa.me/?text={{ urlencode($event->name . ' - ' . ($event->tag_line ?? $event->description) . ' ' . url()->current()) }}" 
               target="_blank"
               class="flex flex-col items-center gap-2">
                <div class="w-14 h-14 bg-[#25D366] rounded-full flex items-center justify-center">
                    <svg style="width: 24px; height: 24px;" class="text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm.029 18.88c-1.161 0-2.305-.292-3.318-.844l-3.677.964.984-3.595c-.607-1.052-.927-2.246-.926-3.468.001-3.825 3.113-6.937 6.937-6.937 1.856.001 3.598.723 4.907 2.034 1.31 1.311 2.031 3.054 2.03 4.908-.001 3.825-3.113 6.938-6.937 6.938z"/>
                    </svg>
                </div>
                <span class="text-1xl">WhatsApp</span>
            </a>

            <!-- Bluesky -->
            <a href="https://bsky.app/intent/compose?text={{ urlencode($event->name . ' - ' . ($event->tag_line ?? $event->description) . ' ' . url()->current()) }}" 
               target="_blank"
               class="flex flex-col items-center gap-2">
                <div class="w-14 h-14 bg-[#0085FF] rounded-full flex items-center justify-center">
                    <svg style="width: 24px; height: 24px;" class="text-white" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.25c-5.384 0-9.75 4.366-9.75 9.75s4.366 9.75 9.75 9.75 9.75-4.366 9.75-9.75S17.384 2.25 12 2.25zm2.658 3.686l2.085 3.616c.293.507.13 1.156-.377 1.45-.198.114-.414.168-.627.168-.37 0-.73-.189-.932-.527l-.682-1.183h-4.25l-.682 1.183a1.083 1.083 0 0 1-1.45.376 1.083 1.083 0 0 1-.377-1.45l4.25-7.364c.293-.507.942-.67 1.45-.377.507.293.67.942.377 1.45l-1.554 2.694h2.428l.341-.592a1.084 1.084 0 0 1 1.45-.377c.508.293.67.943.378 1.45l-.34.59-.04.074zm-2.96 5.879a.899.899 0 0 0-.809-.5H8.013c-.496 0-.898.402-.898.898v3.92c0 .496.402.898.898.898h2.876c.496 0 .898-.402.898-.898v-.964h-2.86v-.98h2.86v-.98h-2.86v-.996h2.86v-.398zm4.503 3.92v-4.818H14.31v5.716h2.79c.496 0 .898-.402.898-.898z"/>
                    </svg>
                </div>
                <span class="text-1xl">Bluesky</span>
            </a>

            <!-- Threads -->
            <a href="https://www.threads.net/intent/post?text={{ urlencode($event->name . ' - ' . ($event->tag_line ?? $event->description) . ' ' . url()->current()) }}" 
               target="_blank"
               class="flex flex-col items-center gap-2">
                <div class="w-14 h-14 bg-black rounded-full flex items-center justify-center">
                    <svg style="width: 24px; height: 24px;" class="text-white" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.5 12.06c0-3.304.986-5.892 2.948-7.697C6.231 2.523 8.632 1.5 11.663 1.5c3.014 0 5.434 1.014 7.192 3.01 1.951 2.096 2.911 5.074 2.855 8.622-.04 4.578-1.657 7.343-2.86 8.58-1.941 2.137-4.814 2.288-6.332 2.288h-.332Zm-4.42-3.219c1.352 1.595 3.706 2.423 6.689 2.423h.33c1.088 0 3.306-.056 4.887-1.8.977-1.08 2.31-3.443 2.346-7.51.046-3.051-.717-5.556-2.276-7.241-1.433-1.656-3.365-2.357-5.929-2.357-2.455 0-4.357.764-5.902 2.276-1.547 1.399-2.314 3.448-2.314 6.121 0 2.953.707 5.251 2.169 6.938Z"/>
                        <path d="M19.237 12.476c-.159-3.577-2.408-5.595-6.395-5.595a6.664 6.664 0 0 0-1.668.214l.161.918a5.642 5.642 0 0 1 1.482-.219c3.345 0 5.209 1.493 5.409 4.342.023.192.028.530.028.84v.146c0 1.261-.512 2.421-1.45 3.273-.828.757-1.954 1.148-3.251 1.148-1.463 0-2.644-.456-3.429-1.316-.776-.85-1.163-2.078-1.163-3.658 0-1.625.418-2.891 1.24-3.766.788-.842 1.899-1.269 3.302-1.269 1.993 0 3.296.943 3.855 2.788l.916-.292c-.731-2.324-2.533-3.428-4.771-3.428-1.7 0-3.09.54-4.124 1.603-1.073 1.112-1.617 2.677-1.617 4.661 0 1.921.517 3.453 1.5 4.437.96.955 2.348 1.442 4.092 1.442 1.638 0 3.039-.481 4.111-1.442 1.177-1.067 1.802-2.595 1.802-4.406v-.145c0-.278-.004-.616-.03-.83Z"/>
                    </svg>
                </div>
                <span class="text-1xl">Threads</span>
            </a>

            <!-- LinkedIn -->
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}&title={{ urlencode($event->name) }}&summary={{ urlencode($event->tag_line ?? $event->description) }}" 
               target="_blank"
               class="flex flex-col items-center gap-2">
                <div class="w-14 h-14 bg-[#0077B5] rounded-full flex items-center justify-center">
                    <svg style="width: 24px; height: 24px;" class="text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                </div>
                <span class="text-1xl">LinkedIn</span>
            </a>
        </div>
    </div>
</div>