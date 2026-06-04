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
                        New Ownership Claim
                    @elseif($status === 'approved')
                        You're now the owner of {{ $organizerName }}
                    @else
                        Your ownership claim was not approved
                    @endif
                </h2>
            </div>

            <div style="font-family: 'Sen', sans-serif;color: #374151;border-top: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; padding: 32px 0; margin: 32px 0;">
                <div>
                    <p style="margin-bottom: 0rem;"><strong>Organization:</strong> {{ $organizerName }}</p>
                    @if($isAdmin)
                        <p style="margin-bottom: 0rem;"><strong>Requested by:</strong> {{ $claimantName }} ({{ $claimantEmail }})</p>
                        @if($claimMessage)
                            <p style="margin-bottom: 0rem;"><strong>Their message:</strong> {{ $claimMessage }}</p>
                        @endif
                    @elseif($status === 'rejected' && $adminNotes)
                        <p style="margin-bottom: 0rem;"><strong>Reason:</strong> {{ $adminNotes }}</p>
                    @endif
                </div>
            </div>

            @if($isAdmin)
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">Please review this claim and reach out to the requester to verify them before approving.</p>
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
                        Review Claim
                    </a>
                </div>
            @elseif($status === 'approved')
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">You now have full ownership of this organization and all of its events. You can manage everything from your hosting dashboard.</p>
                    </span>
                </div>
            @else
                <div>
                    <span style="font-family: 'Sen', sans-serif;color: #374151;">
                        <p style="margin-bottom: 0rem;">If you have any questions about this decision, please contact <a href="mailto:support@everythingimmersive.com" style="color: #FF385C; text-decoration: none;">support@everythingimmersive.com</a></p>
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
