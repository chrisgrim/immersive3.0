<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
</head>
<body style="margin:0;margin-top: 5%;">
    <div style="width: 100%;">
        <div style="max-width: 640px;margin: auto;padding: 3rem;border: 1px solid #e5e7eb;border-radius: 1rem;">
            <div style="margin: auto;">
                <a href="{{ config('app.url') }}" style="display: block; text-decoration: none;margin-bottom: 2rem;">
                    <img src="{{ config('app.url') }}/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" style="width: 40px; height: 40px;" />
                </a>
            </div>
            <div>
                <h2 style="font-family: 'Sen', sans-serif;color: #ff385c;font-size: 1.5rem;margin-bottom: 2rem;margin-top: 0;">
                    New organizer submitted for review
                </h2>
            </div>

            <div style="font-family: 'Sen', sans-serif;color: #374151;border-top: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; padding: 32px 0; margin: 32px 0;">
                <p style="margin-bottom: 0rem;"><strong>Organization:</strong> {{ $organizerName }}</p>
                @if($submittedByName)
                    <p style="margin-bottom: 0rem;"><strong>Submitted by:</strong> {{ $submittedByName }} ({{ $submittedByEmail }})</p>
                @endif
            </div>

            <div>
                <a href="{{ config('app.url') }}/admin?view=approve-organizers"
                style="
                    display: inline-block;
                    font-family: 'Sen', sans-serif;
                    font-size: 1.2rem;
                    margin: 1rem 0;
                    color: white;
                    font-weight: bold;
                    background: linear-gradient(to right, #E41E53, #FF4E85);
                    border: none;
                    padding: 1rem 2rem;
                    border-radius: 9999px;
                    text-decoration: none;
                ">
                    Review Organizers
                </a>
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
</html>
