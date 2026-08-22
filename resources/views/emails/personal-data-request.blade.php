<link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">

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
                    We received your personal data request
                </h2>
            </div>
            <div>
                <span style="font-family: 'Sen', sans-serif;color: #374151;">
                    <p>Hi {{ $name }},</p>
                    <p>We received your request for a copy of your personal data on Everything Immersive. Our team will follow up at this email address with your data or any questions, usually within a few business days.</p>
                </span>
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
