<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Sen&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Secular+One|Sen&display=swap" rel="stylesheet">
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
                    @if($isAdmin)
                        Name Change Request
                    @elseif($status === 'rejected')
                        Your request to change "{{ $currentName }}" was not approved at this time.
                    @else
                        {{ $type }} name is now {{ $newName }}
                    @endif
                </h2>
            </div>
            
            <div style="font-family: 'Sen', sans-serif;color: #374151;border-top: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; padding: 32px 0; margin: 32px 0;">
                <div>
                    @if($status === 'approved')
                        <p style="margin-bottom: 0rem;"><strong>Previous Name:</strong> {{ $currentName }}</p>
                        <p style="margin-bottom: 0rem;"><strong>New Name:</strong> {{ $newName }}</p>
                    @else
                        <p style="margin-bottom: 0rem;"><strong>Current Name:</strong> {{ $currentName }}</p>
                        <p style="margin-bottom: 0rem;"><strong>Requested Name:</strong> {{ $newName }}</p>
                    @endif
                    @if($reason)
                        <p style="margin-bottom: 0rem;"><strong>Reason:</strong> {{ $reason }}</p>
                    @endif
                </div>
            </div>
            
            @if($isAdmin)
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">Please review this request at your earliest convenience.</p>
                    </span>
                    <a href="{{ config('app.url') }}/admin?view=approve-requests" 
                    style="
                        display: inline-block;
                        font-family: 'Sen', sans-serif;
                        font-size: 1.2rem;
                        margin: 1rem 0;
                        margin-bottom: 1rem;
                        color: white;
                        font-weight: bold;
                        background: linear-gradient(to right, #E41E53, #FF4E85);
                        border: none;
                        padding: 1rem 2rem;
                        border-radius: 9999px;
                        text-decoration: none;
                    ">
                        Review Request
                    </a>
                </div>
            @elseif($status === 'rejected')
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">If you have any questions about this decision, please contact <a href="mailto:support@everythingimmersive.com" style="color: #FF385C; text-decoration: none;">support@everythingimmersive.com</a></p>
                    </span>
                </div>
            @else
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">The name change has been processed and is now active across the platform.</p>
                    </span>
                </div>
            @endif
            
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
