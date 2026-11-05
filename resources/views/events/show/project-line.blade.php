@if($projectLine ?? null)
    <p class="text-lg text-neutral-600 mt-2" data-project-line>Part of <a href="{{ $projectLine['project']->publicPath() }}" class="underline hover:text-black">{{ $projectLine['project']->name }}</a>.@if($projectLine['also']) {{ $projectLine['also'] }}@endif</p>
@endif
