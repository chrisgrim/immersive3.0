<div class="fixed inset-0 bg-white z-49 overflow-y-auto">
    <!-- Header with back and share buttons -->
    <div class="sticky top-0 bg-white px-10 py-6 flex justify-between items-center">
        <!-- Back Button -->
        <button 
            onclick="closePhotoGallery()"
            class="w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-lg"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>

        <h1 class="text-2xl font-semibold">Photos</h1>

        <!-- Share Button -->
        <button 
            onclick="handleShare()"
            class="w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-lg"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12"/>
            </svg>
        </button>
    </div>

    <!-- Photos Grid -->
    <div class="p-10 space-y-10">
        @foreach($event->images->slice(1) as $index => $image)
            <div class="w-full">
                <picture>
                    <source type="image/webp" srcset="{{ config('app.image_url') }}{{ $image->large_image_path }}">
                    <img 
                        class="w-full object-cover rounded-lg"
                        src="{{ config('app.image_url') }}{{ substr($image->large_image_path, 0, -4) }}jpg"
                        alt="{{ $event->name }} Immersive Event - Photo {{ $index + 2 }}"
                    >
                </picture>
            </div>
        @endforeach
    </div>
</div>