<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">

<body style="margin:0;margin-top: 5%;margin-bottom: 5%;">
    <div style="width: 100%;">
        <div style="max-width: 640px;margin: auto;padding: 3rem;border: 1px solid #e5e7eb;border-radius: 1rem;">
            <div style="margin: auto;">
                <a href="{{ config('app.url') }}" style="display: block; text-decoration: none;margin-bottom: 2rem;">
                    <img src="{{ config('app.url') }}/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" style="width: 40px; height: 40px;" />
                </a>
            </div>
            <div style="text-align: center;margin: auto;">
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 0.5rem;">
                    This Week in Immersive
                </h2>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 0;margin-bottom: 2rem;">
                    The latest experiences added to Everything Immersive.
                </p>
            </div>

            @forelse($events as $event)
                <div style="border: 1px solid #e5e7eb;padding: 1.5rem;margin-bottom: 1rem;border-radius: 0.5rem;">
                    <a href="{{ config('app.url') }}/events/{{ $event->slug }}" style="text-decoration: none;">
                        <h3 style="font-family: 'Sen', sans-serif;color: #222222;font-size: 1.15rem;margin: 0 0 0.5rem;">
                            {{ $event->name }}
                        </h3>
                    </a>
                    @if($event->relationLoaded('genres') && $event->genres->isNotEmpty())
                        <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;font-size: 0.85rem;margin: 0;">
                            {{ $event->genres->pluck('name')->implode(' · ') }}
                        </p>
                    @endif
                </div>
            @empty
                <div style="text-align: center;margin: auto;">
                    <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;">
                        No new experiences this week — check back soon.
                    </p>
                </div>
            @endforelse

            <div style="text-align: center;margin: auto;">
                <a href="{{ config('app.url') }}">
                    <button style="
                        border: 1px solid #ff385c;
                        padding: .8rem 1.5rem;
                        font-size: 1rem;
                        color: #ff385c;
                        background: white;
                        border-radius: 8px;
                        font-family: 'Sen', sans-serif;
                        cursor: pointer;
                        transition: all 0.2s;
                        margin-top: 1.5rem;
                        ">Explore More</button>
                </a>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
            </div>
        </div>
    </div>
</body>
