<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #222222;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 40px;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
        }
        .logo {
            margin-bottom: 32px;
        }
        .header {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #222222;
            line-height: 1.3;
        }
        .details p {
            margin: 12px 0;
            font-size: 16px;
        }
        .details strong {
            color: #222222;
        }
        .message {
            font-size: 16px;
            color: #484848;
            margin: 24px 0;
        }
        .button {
            display: inline-block;
            background: #FF385C;
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin: 24px 0;
        }
        .footer {
            margin-top: 40px;
            color: #767676;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">        <div class="logo">
        <svg viewBox="0 0 256 256" style="width: 2rem; height: 2rem; display: inline-block;">
            <path id="EI" d="M149.256,186.943H80.406V144.275h63.908V104.057H80.406V67.443h66.983V27.369H34.506V227.161h114.75V186.943ZM226.121,27.369h-45.9V227.161h45.9V27.369Z"></path>
        </svg>
        </div>

        <h1 class="header">
            @if($isAdmin)
                Name Change Request
            @elseif($status === 'rejected')
                Your request to change "{{ $currentName }}" was not approved at this time.
            @else
                {{ $type }} name is now {{ $newName }}
            @endif
        </h1>
        
        <div style="border-top: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; padding: 32px 0; margin: 32px 0;">
            <div class="details">
                @if($status === 'approved')
                    <p><strong>Previous Name:</strong> {{ $currentName }}</p>
                    <p><strong>New Name:</strong> {{ $newName }}</p>
                @else
                    <p><strong>Current Name:</strong> {{ $currentName }}</p>
                    <p><strong>Requested Name:</strong> {{ $newName }}</p>
                @endif
                @if($reason)
                    <p><strong>Reason:</strong> {{ $reason }}</p>
                @endif
            </div>
        </div>
        
        @if($isAdmin)
            <p class="message">Please review this request at your earliest convenience.</p>
            <a href="{{ config('app.url') }}/admin?view=approve-requests" class="button">
                Review Request
            </a>
        @elseif($status === 'rejected')
            <p class="message">If you have any questions about this decision, please contact <a href="mailto:support@everythingimmersive.com" style="color: #FF385C; text-decoration: none;">support@everythingimmersive.com</a></p>
        @else
            <p class="message">The name change has been processed and is now active across the platform.</p>
        @endif
        
        <div class="footer">
            <p>Best regards,<br>Everything Immersive Team</p>
        </div>
    </div>
</body>
</html>
