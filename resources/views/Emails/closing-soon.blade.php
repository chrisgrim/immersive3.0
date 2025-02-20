<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">

<body style="margin:0;margin-top: 5%;margin-bottom: 5%;">
    <div style="width: 100%;">
        <div style="max-width: 640px;margin: auto;padding: 3rem;border: 1px solid #e5e7eb;border-radius: 1rem;">
            <div style="margin: auto;">
                <div style="text-align: center;">
                    <h2 style="font-family: 'Secular One', sans-serif;font-size: 2.2rem;margin-bottom: 1rem;">EI</h2>
                </div>
            </div>
            <div style="text-align: center;margin: auto;">
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 2rem;">
                    Your Event Is Ending Soon
                </h2>

                @php
                    $imagePath = '';
                    if ($event->images && $event->images->count() >= 2) {
                        // Try to get image 2
                        $imagePath = $event->images->get(1)->large_image_path;
                    } elseif ($event->images && $event->images->count() >= 1) {
                        // Fall back to image 1
                        $imagePath = $event->images->first()->large_image_path;
                    } else {
                        // Last resort: use largeImagePath
                        $imagePath = $event->largeImagePath;
                    }
                    // Remove any leading slash to prevent double slash
                    $imagePath = ltrim($imagePath, '/');
                @endphp
                
                <div style="width: 100%; padding-bottom: 66.67%; position: relative; margin: 2rem auto; border-radius: 0.5rem; overflow: hidden;">
                    <img src="{{ rtrim(config('app.image_url'), '/') . '/' . $imagePath }}" 
                         alt="{{ $event->name }}" 
                         style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                </div>
            </div>
            <div>
                <div style="text-align: center;margin: auto;">
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p>Your event "<strong>{{ $event->name }}</strong>" only has a few days left before its last listing date on EI.</p>
                        <p>If this is accurate, no action is needed. Otherwise, use the button below to edit your event and add more dates.</p>
                    </span>
                </div>
            </div>
            <div style="text-align: center;margin: auto;">
                <a href="{{ url("/hosting/event/{$event->slug}/edit") }}">
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
                        ">Update Event</button>
                </a>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 5rem;font-size: 0.875rem;">
                    Too many emails? 
                    <a href="{{ url("/users/{$user->id}/edit") }}" style="color: #ff385c;text-decoration: underline;">
                        Click here to change
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
