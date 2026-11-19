{{-- The Immersive Experience Institute's nonprofit line, or (part: button)
     its Givebutter Donate button. The widget is Givebutter's own button: it
     needs their script, loaded only while GIVEBUTTER_ACCOUNT is set. Until
     then nothing shows, except a stand-in on a local copy so the layout can
     be seen. v-pre: the Vue app mounts over the page and must leave
     Givebutter's custom element alone. --}}
@if(($part ?? 'text') === 'button')
    @php($givebutter = config('services.givebutter'))
    @if($givebutter['account'] && $givebutter['widget'])
        @once
            @push('head')
                <script async src="https://widgets.givebutter.com/latest.umd.cjs?acct={{ urlencode($givebutter['account']) }}&p=other"></script>
            @endpush
        @endonce
        <div v-pre><givebutter-widget id="{{ $givebutter['widget'] }}"></givebutter-widget></div>
    @elseif(app()->isLocal())
        <span class="inline-block px-10 py-3 rounded-full bg-black text-white text-lg font-semibold" title="Stand-in: set GIVEBUTTER_ACCOUNT to load the real Givebutter button">Donate</span>
    @endif
@else
    <p class="m-0 {{ $class ?? '' }}">
        The Immersive Experience Institute is a 501(c)(3) nonprofit organization. <span class="whitespace-nowrap">EIN: 84-4569232</span>
    </p>
@endif
