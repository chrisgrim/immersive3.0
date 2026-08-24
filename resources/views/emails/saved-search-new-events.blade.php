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
                    New Matches For "{{ $savedSearch->name }}"
                </h2>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-bottom: 2rem;">
                    {{ $events->count() === 1 ? '1 new event' : $events->count().' new events' }} showed up in this saved search.
                </p>
            </div>

            <div>
                @foreach ($events->take(5) as $event)
                    @php
                        $imagePath = '';
                        if ($event->images && $event->images->count() >= 1) {
                            $imagePath = $event->images->first()->large_image_path;
                        } else {
                            $imagePath = $event->largeImagePath;
                        }
                        $imagePath = ltrim($imagePath, '/');
                    @endphp
                    <a href="{{ url("/events/{$event->slug}") }}" style="display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
                        <div style="width: 64px; height: 64px; border-radius: 0.5rem; overflow: hidden; flex-shrink: 0; background: #f3f4f6;">
                            @if ($imagePath)
                                <img src="{{ rtrim(config('app.image_url'), '/') . '/' . $imagePath }}" alt="{{ $event->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                            @endif
                        </div>
                        <span style="font-family: 'Sen', sans-serif;color: #374151;font-size: 1rem;">{{ $event->name }}</span>
                    </a>
                @endforeach

                @if ($events->count() > 5)
                    <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;font-size: 0.875rem;margin-top: 1rem;">
                        and {{ $events->count() - 5 }} more
                    </p>
                @endif
            </div>

            <div style="text-align: center;margin: auto;">
                <a href="{{ url($searchUrl) }}">
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
                        margin-top: 2rem;
                        ">View All Matches</button>
                </a>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 5rem;font-size: 0.875rem;">
                    Too many emails?
                    <a href="{{ url('/account-settings/notifications') }}" style="color: #ff385c;text-decoration: underline;">
                        Manage your notification settings
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
