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
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 2rem;">
                    Curator Invitation
                </h2>

                @php
                    $imagePath = '';
                    if ($community->images && $community->images->count() > 0) {
                        $imagePath = $community->images->first()->large_image_path;
                    } else {
                        $imagePath = $community->largeImagePath;
                    }
                    $imagePath = ltrim($imagePath, '/');
                @endphp
                
                <div style="width: 100%; padding-bottom: 66.67%; position: relative; margin: 2rem auto; border-radius: 0.5rem; overflow: hidden;">
                    <img src="{{ rtrim(config('app.image_url'), '/') . '/' . $imagePath }}" 
                         alt="{{ $community->name }}" 
                         style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                </div>
            </div>
            <div>
                <div style="text-align: center;margin: auto;">
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p>You've been invited to be a curator for <strong>{{ $community->name }}</strong>.</p>
                        <p>This invitation will expire in 7 days.</p>
                    </span>
                </div>
            </div>
            <div style="text-align: center;margin: auto;">
                <a href="{{ url("/communities/curator-invitations/{$invitation->token}") }}">
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
                        ">Accept Invitation</button>
                </a>
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
            </div>
        </div>
    </div>
</body>