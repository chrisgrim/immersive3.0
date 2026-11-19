{{-- One changelog post as a card: picture, title, date (Airbnb's "Latest news"). --}}
<a href="{{ route('changelog.show', $post['slug']) }}" class="group block">
    <div class="aspect-[3/2] rounded-[1.6rem] overflow-hidden bg-neutral-100">
        @if($post['image'])
            <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" loading="lazy"
                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
        @else
            <div class="w-full h-full flex items-center justify-center px-10">
                <img src="{{ asset('storage/website-files/Everything_Immersive_logo.png') }}" alt="" class="w-3/4 max-w-[26rem] h-auto opacity-90">
            </div>
        @endif
    </div>
    <h3 class="mt-[1.6rem] text-[2rem] leading-[2.6rem] font-medium text-[#222222] group-hover:underline">{{ $post['title'] }}</h3>
    <p class="mt-[0.6rem] text-[1.4rem] text-neutral-500">{{ $post['date']->format('F j, Y') }}</p>
</a>
