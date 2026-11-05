{{-- One run: where, when, who presents it. Never a street: see RunRow. --}}
<a href="{{ $run['url'] }}" class="flex items-center gap-6 py-6 border-b border-neutral-200 group">
    <div class="w-20 h-28 flex-shrink-0 rounded-xl overflow-hidden bg-neutral-100">
        @if($run['image'])
            <img src="{{ config('app.image_url') }}{{ $run['image'] }}" alt="" loading="lazy" class="w-full h-full object-cover">
        @endif
    </div>
    <div class="min-w-0">
        <p class="text-2xl font-medium text-black group-hover:underline break-words">{{ $run['place'] ?: ($run['name'] ?? '') }}</p>
        @if($run['name'] && $run['place'])
            <p class="text-xl text-neutral-700 break-words">{{ $run['name'] }}</p>
        @endif
        <p class="text-xl text-neutral-600">{{ $run['dates'] }}</p>
        @if($run['presenter'])
            <p class="text-xl text-neutral-600 break-words">Presented by {{ $run['presenter']->name }}</p>
        @endif
        @if($run['run_type'] || $run['festival'])
            <p class="text-lg text-neutral-600 mt-2 flex flex-wrap gap-2">
                @if($run['run_type'])
                    <span class="px-3 py-1 rounded-full border border-neutral-300">{{ $run['run_type'] }}</span>
                @endif
                @if($run['festival'])
                    <span class="px-3 py-1 rounded-full border border-neutral-300">{{ $run['festival'] }}</span>
                @endif
            </p>
        @endif
    </div>
</a>
