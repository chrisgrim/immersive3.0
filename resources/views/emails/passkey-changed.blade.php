<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">

<body style="margin:0;margin-top: 5%;">
    <div style="width: 100%;">
        <div style="max-width: 640px;margin: auto;padding: 3rem;border: 1px solid #e5e7eb;border-radius: 1rem;">
            <div style="margin: auto;">
                <a href="{{ config('app.url') }}" style="display: block; text-decoration: none;margin-bottom: 2rem;">
                    <img src="{{ config('app.url') }}/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" style="width: 40px; height: 40px;" />
                </a>
            </div>
            <div>
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 2rem;">
                    {{ $subject }}
                </h2>
            </div>
            <div style="font-family: 'Sen', sans-serif;color: #374151;">
                @if ($change === \App\Mail\PasskeyChanged::ADDED)
                    <p>The passkey <strong>{{ $passkeyName }}</strong> can now sign in to your Everything Immersive account.</p>
                @else
                    <p>The passkey <strong>{{ $passkeyName }}</strong> can no longer sign in to your Everything Immersive account.</p>
                @endif
                <p>If this was you, there is nothing to do.</p>
                <p>If it was not, open <a href="{{ $settingsUrl }}" style="color: #ff385c;">Login &amp; security</a>, remove any passkey you do not recognize and log out the devices you do not know.</p>
            </div>
            <div style="text-align: center;margin: auto;">
                <p style="font-family: 'Sen', sans-serif;color: #6d6d6d;margin-top: 2rem;">
                    Thanks,<br>
                    The EI team
                </p>
            </div>
        </div>
    </div>
</body>
